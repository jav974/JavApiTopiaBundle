<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Product;

class FavoriteProductsResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        $product = new Product();

        $product->id = 1;
        $product->name = 'Product 1';
        $product->picture = null;

        return [$product];
    }
}
