<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;

class FavoriteProductsResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        return [];
    }
}
