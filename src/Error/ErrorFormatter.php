<?php

namespace Jav\ApiTopiaBundle\Error;

use GraphQL\Error\ClientAware;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\SyntaxError;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Formats GraphQL errors: every error gets an "extensions.code" (see ErrorCode), client errors keep their
 * message, internal errors are hidden (outside debug mode) and logged.
 */
class ErrorFormatter
{
    /**
     * @param array<class-string, array{code: string, expose_message?: bool}> $mapping Exception class => code, for application exceptions
     */
    public function __construct(
        private readonly array $mapping = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function format(\Throwable $error, int $debugFlag = DebugFlag::NONE): array
    {
        $formatted = FormattedError::createFromException($error, $debugFlag);
        ['code' => $code, 'message' => $message, 'extensions' => $extensions] = $this->classify($error);

        if ($message !== null) {
            $formatted['message'] = $message;
        }

        $formatted['extensions'] = ['code' => $code] + $extensions + ($formatted['extensions'] ?? []);

        return $formatted;
    }

    /**
     * Formats a list of errors, logging internal ones.
     *
     * @param \Throwable[] $errors
     * @return array<int, array<string, mixed>>
     */
    public function formatAll(array $errors, int $debugFlag = DebugFlag::NONE): array
    {
        $formatted = [];

        foreach ($errors as $error) {
            $formattedError = $this->format($error, $debugFlag);

            if ($formattedError['extensions']['code'] === ErrorCode::INTERNAL_SERVER_ERROR) {
                $this->log($error);
            }

            $formatted[] = $formattedError;
        }

        return $formatted;
    }

    public function log(\Throwable $error): void
    {
        $exception = $error instanceof Error && $error->getPrevious() !== null ? $error->getPrevious() : $error;

        $this->logger?->error('GraphQL internal error: {message}', [
            'message' => $exception->getMessage(),
            'exception' => $exception,
            'path' => $error instanceof Error && $error->path !== null ? implode('.', $error->path) : null,
        ]);
    }

    /**
     * @return array{code: string, message: string|null, extensions: array<string, mixed>}
     */
    private function classify(\Throwable $error): array
    {
        $exception = $error instanceof Error ? $error->getPrevious() : $error;

        // Errors raised by GraphQL itself: syntax, validation against the schema, variables coercion
        if ($exception === null) {
            /** @var Error $error */
            return $this->result($error instanceof SyntaxError || $error->getPrevious() instanceof SyntaxError
                ? ErrorCode::GRAPHQL_PARSE_FAILED
                : ($error->getPath() === null || $error->getPath() === [] ? ErrorCode::GRAPHQL_VALIDATION_FAILED : ErrorCode::BAD_USER_INPUT));
        }

        if ($exception instanceof SyntaxError) {
            return $this->result(ErrorCode::GRAPHQL_PARSE_FAILED);
        }

        if ($exception instanceof ClientException) {
            return $this->result($exception->getErrorCode());
        }

        // The application mapping comes first: it may override the built-in rules below
        foreach ($this->mapping as $class => $config) {
            if ($exception instanceof $class) {
                return $this->result($config['code'], ($config['expose_message'] ?? true) ? $exception->getMessage() : null);
            }
        }

        if (class_exists(ValidationFailedException::class) && $exception instanceof ValidationFailedException) {
            return $this->result(ErrorCode::BAD_USER_INPUT, 'Validation failed', [
                'violations' => ValidationException::formatViolations($exception->getViolations()),
            ]);
        }

        if (class_exists(AuthenticationException::class) && $exception instanceof AuthenticationException) {
            return $this->result(ErrorCode::UNAUTHENTICATED, 'Authentication required');
        }

        if (class_exists(AccessDeniedException::class) && $exception instanceof AccessDeniedException) {
            return $this->result(ErrorCode::FORBIDDEN, 'Access denied');
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return $this->result(match ($exception->getStatusCode()) {
                401 => ErrorCode::UNAUTHENTICATED,
                403 => ErrorCode::FORBIDDEN,
                404 => ErrorCode::NOT_FOUND,
                default => ErrorCode::BAD_USER_INPUT,
            }, $exception->getMessage());
        }

        // UserError and other client safe errors of graphql-php
        if ($exception instanceof ClientAware && $exception->isClientSafe()) {
            return $this->result(ErrorCode::BAD_USER_INPUT);
        }

        return $this->result(ErrorCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * @param array<string, mixed> $extensions
     * @return array{code: string, message: string|null, extensions: array<string, mixed>}
     */
    private function result(string $code, ?string $message = null, array $extensions = []): array
    {
        return ['code' => $code, 'message' => $message, 'extensions' => $extensions];
    }
}
