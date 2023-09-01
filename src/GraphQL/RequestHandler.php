<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Upload\UploadMiddleware;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RequestHandler
{
    public function __construct(private readonly SchemaBuilder $schemaBuilder)
    {
    }

    public function handleRequest(Request $request): JsonResponse
    {
        try {
            $schemaName = $request->attributes->get('_apitopia')['schema'];
            $psr17Factory = new Psr17Factory();
            $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
            $psrRequest = $psrHttpFactory->createRequest($request);
            $contentType = explode(';', $psrRequest->getHeader('content-type')[0] ?? '')[0];

            $psrRequest = match ($contentType) {
                'application/graphql' => $this->handleApplicationGraphql($psrRequest),
                'multipart/form-data' => $this->handleMultipartFormData($psrRequest),
                'application/json' => $this->handleApplicationJson($psrRequest),
                default => $psrRequest,
            };

            $config = ServerConfig::create()
                ->setSchema($this->schemaBuilder->getSchema($schemaName))
                ->setQueryBatching(true)
                ->setDebugFlag(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE)
            ;

            $server = new StandardServer($config);

            /** @var ExecutionResult|ExecutionResult[] $result */
            $result = $server->executePsrRequest($psrRequest);
            $jsonResult = json_encode($result, JSON_THROW_ON_ERROR);
            $statusCode = !empty($result->errors) ? 500 : 200;

            return new JsonResponse($jsonResult, $statusCode, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'errors' => [
                    FormattedError::createFromException($e, DebugFlag::INCLUDE_DEBUG_MESSAGE|DebugFlag::INCLUDE_TRACE)
                ]], 500);
        }
    }

    /**
     * @throws JsonException
     */
    private function handleApplicationGraphql(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request
            ->withHeader('content-type', 'application/json')
            ->withParsedBody(json_decode($request->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR));
    }

    private function handleMultipartFormData(ServerRequestInterface $request): ServerRequestInterface
    {
        return (new UploadMiddleware())->processRequest($request);
    }

    /**
     * @throws JsonException
     */
    private function handleApplicationJson(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withParsedBody(json_decode($request->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR));
    }
}
