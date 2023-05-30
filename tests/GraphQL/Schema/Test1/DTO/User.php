<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO;

use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FavoriteProductsResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\StatResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;

#[ApiResource(
    graphQLOperations: [
        new Query(resolver: UserResolver::class, description: "Get a user", args: ['id' => ['type' => 'Int!']])
    ]
)]
class User
{
    public int $id;
    public string $email;
    public ?string $phone;
    public ?Address $address;
    /** @var Address[] */
    public array $otherAddreses;

    #[SubQueryCollection(resolver: FavoriteProductsResolver::class, output: Product::class, description: "List of favorite products (cursor based paginated)")]
    public array $favoriteCursorBasedPaginatedProducts;

    #[SubQueryCollection(resolver: FavoriteProductsResolver::class, paginationType: "offset", output: Product::class, description: "List of favorite products (offset based paginated)")]
    public array $favoriteOffsetBasedPaginatedProducts;

    #[SubQueryCollection(resolver: FavoriteProductsResolver::class, paginationEnabled: false, output: Product::class, description: "List of favorite products (not paginated)")]
    public array $favoriteNotPaginatedProducts;

    #[SubQuery(resolver: StatResolver::class, args: [], output: Stat::class, description: "Compute (heavy) stats")]
    public Stat $userStats;
}
