<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;

class ApiResourceObjectCollectionResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        return [];
    }
}
