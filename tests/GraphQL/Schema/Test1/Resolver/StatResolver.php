<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;

class StatResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): object|array|null
    {
        return null;
    }
}
