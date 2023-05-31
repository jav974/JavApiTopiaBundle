<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject2;

class ApiResourceObject2ItemResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): object|array|null
    {
        return match ($context['info']->fieldName) {
            'optionalApiResourceSubObjectAsSubQuery' => null,
            default => new ApiResourceObject2(42)
        };
    }
}
