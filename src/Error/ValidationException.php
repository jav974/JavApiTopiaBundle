<?php

namespace Jav\ApiTopiaBundle\Error;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Validation constraints violations, exposed in "extensions.violations".
 */
class ValidationException extends ClientException
{
    /**
     * @param string $pathPrefix Prepended to the property paths, e.g. "input" for mutation inputs
     */
    public function __construct(
        private readonly ConstraintViolationListInterface $violations,
        private readonly string $pathPrefix = '',
        string $message = 'Validation failed',
    ) {
        parent::__construct($message, ErrorCode::BAD_USER_INPUT, ['violations' => self::formatViolations($violations, $pathPrefix)]);
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    /**
     * @return list<array{path: string, message: string, code: string|null}>
     */
    public static function formatViolations(ConstraintViolationListInterface $violations, string $pathPrefix = ''): array
    {
        $formatted = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath();

            $formatted[] = [
                'path' => $pathPrefix !== '' && $path !== '' ? "$pathPrefix.$path" : ($pathPrefix . $path),
                'message' => (string) $violation->getMessage(),
                'code' => $violation->getCode(),
            ];
        }

        return $formatted;
    }
}
