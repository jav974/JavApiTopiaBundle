<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Jav\ApiTopiaBundle\Tests\Logger\TestLogger;

/**
 * Errors are exposed with a code (extensions.code); internal errors are hidden and logged.
 */
class ErrorsTest extends AbstractApiTestCase
{
    // No debug: what clients see in production
    protected static array $kernelOptions = ['environment' => 'test_lazy', 'debug' => false];

    /**
     * @return array<string, mixed>
     */
    private function fail_(string $kind): array
    {
        $response = $this->graphQL('/test/graphql/test1', 'query ($kind: String) { failingUser(kind: $kind) { _id } }', ['kind' => $kind]);
        $this->assertSame(['failingUser' => null], $response['data']);

        return $response['errors'][0];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function logs(): array
    {
        /** @var TestLogger $logger */
        $logger = static::getContainer()->get('logger');

        $logs = array_map(fn (array $log) => ['level' => $log[0], 'message' => $log[1], 'context' => $log[2]], $logger->cleanLogs());

        // Only errors: the router logs matched routes at info level
        return array_values(array_filter($logs, fn (array $log) => $log['level'] === 'error'));
    }

    /**
     * @return iterable<string, array{string, string, string, array<string, mixed>}>
     */
    public static function clientErrors(): iterable
    {
        // kind, expected code, expected message, other extensions
        yield 'ClientException with custom code and extensions' => ['client', 'QUOTA_EXCEEDED', 'Quota exceeded', ['retryAfter' => 60]];
        yield 'ClientException::notFound()' => ['notFound', 'NOT_FOUND', 'Order 42 not found', []];
        yield 'mapped exception' => ['mapped', 'NOT_FOUND', 'Order 43 not found', []];
        yield 'mapped exception, message hidden' => ['mappedHidden', 'PAYMENT_FAILED', 'Internal server error', []];
        yield 'HTTP 404' => ['httpNotFound', 'NOT_FOUND', 'No such page', []];
        yield 'HTTP 403' => ['httpForbidden', 'FORBIDDEN', 'Admins only', []];
        yield 'HTTP 401' => ['httpUnauthorized', 'UNAUTHENTICATED', 'Token expired', []];
        yield 'HTTP 400' => ['httpBadRequest', 'BAD_USER_INPUT', 'Malformed filter', []];
        yield 'graphql-php UserError' => ['userError', 'BAD_USER_INPUT', 'Bad argument', []];
        yield 'Symfony ValidationFailedException' => ['validation', 'BAD_USER_INPUT', 'Validation failed', [
            'violations' => [['path' => 'name', 'message' => 'Too short', 'code' => 'TOO_SHORT']],
        ]];
    }

    /**
     * @param array<string, mixed> $extensions
     */
    #[DataProvider('clientErrors')]
    public function testClientErrors(string $kind, string $code, string $message, array $extensions): void
    {
        $error = $this->fail_($kind);

        $this->assertSame($message, $error['message']);
        $this->assertSame(['code' => $code] + $extensions, $error['extensions']);
        $this->assertSame(['failingUser'], $error['path']);
        $this->assertSame([], $this->logs(), 'Client errors are not logged');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function internalErrors(): iterable
    {
        yield 'unexpected exception' => ['unexpected', 'boom: secret internal detail'];
        yield 'HTTP 5xx' => ['httpServer', 'database host db-1 down'];
    }

    #[DataProvider('internalErrors')]
    public function testInternalErrorsAreHiddenAndLogged(string $kind, string $secret): void
    {
        $error = $this->fail_($kind);

        $this->assertSame('Internal server error', $error['message']);
        $this->assertSame(['code' => 'INTERNAL_SERVER_ERROR'], $error['extensions']);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('error', $logs[0]['level']);
        $this->assertSame($secret, $logs[0]['context']['message']);
        $this->assertInstanceOf(\Throwable::class, $logs[0]['context']['exception']);
        $this->assertSame('failingUser', $logs[0]['context']['path']);
    }

    public function testQueryErrorsHaveCodes(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ users { doesNotExist } }');
        $this->assertSame('GRAPHQL_VALIDATION_FAILED', $response['errors'][0]['extensions']['code']);

        $response = $this->graphQL('/test/graphql/test1', '{ users { ');
        $this->assertSame('GRAPHQL_PARSE_FAILED', $response['errors'][0]['extensions']['code']);

        // Invalid variable values are user input errors (as in Apollo Server)
        $response = $this->graphQL('/test/graphql/test1', 'query ($id: Int!) { user(id: $id) { _id } }', ['id' => 'abc']);
        $this->assertSame('BAD_USER_INPUT', $response['errors'][0]['extensions']['code']);

        $this->assertSame([], $this->logs());
    }
}
