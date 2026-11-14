<?php

namespace Jav\ApiTopiaBundle\Error;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * An error meant for the API client: its message is always exposed, with a code in "extensions.code".
 *
 * Throw it from resolvers, e.g. `throw new ClientException('Order not found', ErrorCode::NOT_FOUND)`.
 */
class ClientException extends \RuntimeException implements ClientAware, ProvidesExtensions
{
    /**
     * @param array<string, mixed> $extensions Additional entries of "extensions"
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = ErrorCode::BAD_USER_INPUT,
        private readonly array $extensions = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self($message, ErrorCode::NOT_FOUND);
    }

    public static function forbidden(string $message = 'Access denied'): self
    {
        return new self($message, ErrorCode::FORBIDDEN);
    }

    public static function unauthenticated(string $message = 'Authentication required'): self
    {
        return new self($message, ErrorCode::UNAUTHENTICATED);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return ['code' => $this->errorCode] + $this->extensions;
    }
}
