<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Upload\UploadMiddleware;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ValidationRule;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RequestHandler
{
    /**
     * @param array{debug?: bool|string, error_http_status_code?: int, batching?: bool, introspection?: bool, max_query_depth?: int, max_query_complexity?: int} $options
     */
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly ?ResolverProvider $resolverProvider = null,
        private array $options = [],
    ) {
        $this->options += [
            'debug' => false,
            'error_http_status_code' => 500,
            'batching' => true,
            'introspection' => true,
            'max_query_depth' => 0,
            'max_query_complexity' => 0,
        ];
    }

    public function handleRequest(Request $request): JsonResponse
    {
        $debugFlag = $this->getDebugFlag();

        try {
            $schemaName = $request->attributes->get('_apitopia')['schema'];
            $psr17Factory = new Psr17Factory();
            $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
            $psrRequest = $psrHttpFactory->createRequest($request);
            $contentType = strtolower(trim(explode(';', $psrRequest->getHeaderLine('content-type'))[0]));

            $psrRequest = match ($contentType) {
                'application/graphql' => $this->handleApplicationGraphql($psrRequest),
                'multipart/form-data' => $this->handleMultipartFormData($psrRequest),
                'application/json' => $this->handleApplicationJson($psrRequest),
                default => $psrRequest,
            };

            if ($psrRequest instanceof ResponseInterface) {
                // The upload middleware rejected the request (e.g. payload too large)
                return new JsonResponse((string) $psrRequest->getBody(), $psrRequest->getStatusCode(), [], true);
            }

            $config = ServerConfig::create()
                ->setSchema($this->schemaBuilder->getSchema($schemaName))
                ->setQueryBatching((bool) $this->options['batching'])
                ->setDebugFlag($debugFlag)
                ->setValidationRules($this->getValidationRules())
            ;

            $server = new StandardServer($config);

            try {
                /** @var ExecutionResult|ExecutionResult[] $result */
                $result = $server->executePsrRequest($psrRequest);
            } finally {
                // Never let deferred state leak from one request to the next (long-running workers)
                $this->resolverProvider?->reset();
            }

            $jsonResult = json_encode($result, JSON_THROW_ON_ERROR);
            $statusCode = $this->hasErrors($result) ? (int) $this->options['error_http_status_code'] : 200;

            return new JsonResponse($jsonResult, $statusCode, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'errors' => [
                    FormattedError::createFromException($e, $debugFlag)
                ]], 500);
        }
    }

    /**
     * @param ExecutionResult|ExecutionResult[] $result
     */
    private function hasErrors(ExecutionResult|array $result): bool
    {
        foreach (is_array($result) ? $result : [$result] as $item) {
            if (!empty($item->errors)) {
                return true;
            }
        }

        return false;
    }

    private function getDebugFlag(): int
    {
        $debug = filter_var($this->options['debug'], FILTER_VALIDATE_BOOLEAN);

        return $debug ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : DebugFlag::NONE;
    }

    /**
     * @return ValidationRule[]
     */
    private function getValidationRules(): array
    {
        $rules = array_values(DocumentValidator::allRules());

        if ($this->options['max_query_depth'] > 0) {
            $rules[] = new QueryDepth((int) $this->options['max_query_depth']);
        }

        if ($this->options['max_query_complexity'] > 0) {
            $rules[] = new QueryComplexity((int) $this->options['max_query_complexity']);
        }

        if (!$this->options['introspection']) {
            $rules[] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        return $rules;
    }

    /**
     * The body of an "application/graphql" request is the raw GraphQL document.
     * Variables and operation name may be passed through the query string.
     *
     * @throws JsonException
     */
    private function handleApplicationGraphql(ServerRequestInterface $request): ServerRequestInterface
    {
        $body = (string) $request->getBody();
        $queryParams = $request->getQueryParams();
        $variables = $queryParams['variables'] ?? null;

        if (is_string($variables) && $variables !== '') {
            $variables = json_decode($variables, true, flags: JSON_THROW_ON_ERROR);
        }

        $parsedBody = ['query' => $body];

        if (!empty($variables)) {
            $parsedBody['variables'] = $variables;
        }

        if (!empty($queryParams['operationName'])) {
            $parsedBody['operationName'] = $queryParams['operationName'];
        }

        // Backward compatibility: earlier versions expected a JSON payload with this content type
        $trimmedBody = ltrim($body);

        if (str_starts_with($trimmedBody, '{') || str_starts_with($trimmedBody, '[')) {
            try {
                $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    $parsedBody = $decoded;
                }
            } catch (JsonException) {
                // Not JSON: an anonymous query like "{ users { id } }", keep the raw document
            }
        }

        return $request
            ->withHeader('content-type', 'application/json')
            ->withParsedBody($parsedBody);
    }

    /**
     * Uses the PSR-15 API of the upload middleware, available in every supported version of ecodev/graphql-upload.
     */
    private function handleMultipartFormData(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new \Nyholm\Psr7\Response();
            }
        };

        $response = (new UploadMiddleware())->process($request, $handler);

        return $handler->request ?? $response;
    }

    /**
     * @throws JsonException
     */
    private function handleApplicationJson(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withParsedBody(json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }
}
