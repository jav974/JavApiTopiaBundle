<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Source;
use Jav\ApiTopiaBundle\Error\ClientException;
use Jav\ApiTopiaBundle\Error\ErrorFormatter;
use Jav\ApiTopiaBundle\Error\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ErrorFormatterTest extends TestCase
{
    private function located(\Throwable $exception): Error
    {
        return new Error($exception->getMessage(), null, null, [], ['field'], $exception);
    }

    public function testSecurityExceptions(): void
    {
        $formatter = new ErrorFormatter();

        $this->assertSame(
            ['message' => 'Access denied', 'path' => ['field'], 'extensions' => ['code' => 'FORBIDDEN']],
            $formatter->format($this->located(new AccessDeniedException('Voter X denied attribute EDIT')))
        );
        $this->assertSame(
            ['message' => 'Authentication required', 'path' => ['field'], 'extensions' => ['code' => 'UNAUTHENTICATED']],
            $formatter->format($this->located(new BadCredentialsException()))
        );
    }

    public function testSyntaxError(): void
    {
        $error = new SyntaxError(new Source('{'), 1, 'Expected Name');

        $this->assertSame('GRAPHQL_PARSE_FAILED', (new ErrorFormatter())->format($error)['extensions']['code']);
        $this->assertSame('GRAPHQL_PARSE_FAILED', (new ErrorFormatter())->format(new Error('wrapped', null, null, [], null, $error))['extensions']['code']);
    }

    public function testExceptionOutsideGraphQLExecution(): void
    {
        $this->assertSame(
            ['message' => 'Not here', 'extensions' => ['code' => 'NOT_FOUND']],
            (new ErrorFormatter())->format(ClientException::notFound('Not here'))
        );
        $this->assertSame(
            ['message' => 'Internal server error', 'extensions' => ['code' => 'INTERNAL_SERVER_ERROR']],
            (new ErrorFormatter())->format(new \LogicException('secret'))
        );
    }

    public function testMappingTakesPrecedenceOverBuiltInRules(): void
    {
        $formatter = new ErrorFormatter([AccessDeniedException::class => ['code' => 'NOPE', 'expose_message' => false]]);

        $formatted = $formatter->format($this->located(new AccessDeniedException('secret voter detail')));

        $this->assertSame('NOPE', $formatted['extensions']['code']);
        $this->assertSame('Internal server error', $formatted['message']);
    }

    public function testFactories(): void
    {
        $this->assertSame('FORBIDDEN', ClientException::forbidden()->getErrorCode());
        $this->assertSame('UNAUTHENTICATED', ClientException::unauthenticated()->getErrorCode());
        $this->assertSame('Authentication required', ClientException::unauthenticated()->getMessage());
    }

    public function testValidationException(): void
    {
        $violations = new ConstraintViolationList([new ConstraintViolation('Required', null, [], null, '', null, null, 'REQUIRED')]);
        $exception = new ValidationException($violations, 'input');

        $this->assertSame($violations, $exception->getViolations());
        $this->assertSame('input', $exception->getPathPrefix());
        // A violation on the root object has an empty property path
        $this->assertSame([['path' => 'input', 'message' => 'Required', 'code' => 'REQUIRED']], $exception->getExtensions()['violations']);
    }

    public function testDebugEntriesAreKept(): void
    {
        $formatted = (new ErrorFormatter())->format($this->located(new \RuntimeException('boom')), DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->assertSame('INTERNAL_SERVER_ERROR', $formatted['extensions']['code']);
        $this->assertSame('boom', $formatted['extensions']['debugMessage']);
    }

    public function testOnlyInternalErrorsAreLogged(): void
    {
        $logger = new BufferingLogger();
        $formatter = new ErrorFormatter([], $logger);

        $formatter->formatAll([$this->located(ClientException::notFound()), $this->located(new \RuntimeException('boom'))]);

        $logs = $logger->cleanLogs();
        $this->assertCount(1, $logs);
        $this->assertSame('boom', $logs[0][2]['message']);
    }
}
