<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Upload\UploadMiddleware;
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

            return new JsonResponse($jsonResult, 200, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    private function handleApplicationGraphql(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request
            ->withHeader('content-type', 'application/json')
            ->withParsedBody(json_decode($request->getBody()->getContents(), true, JSON_THROW_ON_ERROR));
    }

    private function handleMultipartFormData(ServerRequestInterface $request): ServerRequestInterface
    {
        return (new UploadMiddleware())->processRequest($request);
    }
}
