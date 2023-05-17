<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use Nyholm\Psr7\Factory\Psr17Factory;
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
        $schemaName = $request->attributes->get('_apitopia')['schema'];
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $psrHttpFactory->createRequest($request);

        if (($psrRequest->getHeader('content-type')[0] ?? null) === 'application/graphql') {
            $data = json_decode($psrRequest->getBody()->getContents(), true, JSON_THROW_ON_ERROR);
            $psrRequest = $psrRequest->withHeader('content-type', 'application/json')->withParsedBody($data);
        }

        $config = ServerConfig::create()
            ->setSchema($this->schemaBuilder->getSchema($schemaName))
            ->setQueryBatching(true)
//            ->setErrorFormatter($myFormatter)
//            ->setDebugFlag($debug)
        ;

        $server = new StandardServer($config);

        try {
            /** @var ExecutionResult|ExecutionResult[] $result */
            $result = $server->executePsrRequest($psrRequest);
            $jsonResult = json_encode($result, JSON_THROW_ON_ERROR);

            return new JsonResponse($jsonResult, 200, [], true);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }
}
