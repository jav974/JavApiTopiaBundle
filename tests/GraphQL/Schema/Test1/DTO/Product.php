<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FavoriteProductsResolver;

#[ApiResource(
    graphQLOperations: [
        new QueryCollection(
            resolver: FavoriteProductsResolver::class,
            paginationEnabled: false,
            name: 'customFavoriteProducts',
            args: ['sort' => ['type' => 'Sort!']]
        )
    ]
)]
class Product
{
    public int $id;
    public string $name;
    public ?string $picture;
}
