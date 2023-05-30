<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject2;

class ApiResourceObject2CollectionResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        return [
            new ApiResourceObject2(1),
            new ApiResourceObject2(2),
        ];
    }
}
