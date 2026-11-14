<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use GraphQL\Error\UserError;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Error\ClientException;
use Jav\ApiTopiaBundle\Error\ErrorCode;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Exception\OrderNotFoundException;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Exception\PaymentGatewayException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class FailingResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): object|array|null
    {
        throw match ($context['args']['kind'] ?? null) {
            'client' => new ClientException('Quota exceeded', 'QUOTA_EXCEEDED', ['retryAfter' => 60]),
            'notFound' => ClientException::notFound('Order 42 not found'),
            'mapped' => new OrderNotFoundException('Order 43 not found'),
            'mappedHidden' => new PaymentGatewayException('gateway token abc123 rejected'),
            'httpNotFound' => new NotFoundHttpException('No such page'),
            'httpForbidden' => new AccessDeniedHttpException('Admins only'),
            'httpServer' => new ServiceUnavailableHttpException(null, 'database host db-1 down'),
            'userError' => new UserError('Bad argument'),
            'validation' => new ValidationFailedException('value', new ConstraintViolationList([
                new ConstraintViolation('Too short', null, [], 'value', 'name', 'x', null, 'TOO_SHORT'),
            ])),
            default => new \RuntimeException('boom: secret internal detail'),
        };
    }
}
