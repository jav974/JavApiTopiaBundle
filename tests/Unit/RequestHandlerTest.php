<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\Error\ErrorFormatter;
use Jav\ApiTopiaBundle\GraphQL\RequestHandler;
use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\HttpFoundation\Request;

class RequestHandlerTest extends TestCase
{
    public function testInternalErrorOutsideExecutionIsHiddenAndLogged(): void
    {
        $schemaBuilder = $this->createStub(SchemaBuilder::class);
        $schemaBuilder->method('getSchema')->willThrowException(new \RuntimeException('cannot read /var/secret'));
        $logger = new BufferingLogger();
        $handler = new RequestHandler($schemaBuilder, null, [], new ErrorFormatter([], $logger));

        $request = Request::create('/graphql', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"query": "{ a }"}');
        $request->attributes->set('_apitopia', ['schema' => 'main']);
        $response = $handler->handleRequest($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['errors' => [['message' => 'Internal server error', 'extensions' => ['code' => 'INTERNAL_SERVER_ERROR']]]],
            json_decode($response->getContent(), true)
        );
        $this->assertSame('cannot read /var/secret', $logger->cleanLogs()[0][2]['message']);
    }
}
