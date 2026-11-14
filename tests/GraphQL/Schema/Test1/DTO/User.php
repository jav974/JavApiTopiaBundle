<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredAddressResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredCompanyResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredOrdersResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FailingResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\FavoriteProductsResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\StatResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UsersResolver;

#[ApiResource(
    graphQLOperations: [
        new Query(resolver: UserResolver::class, args: ['id' => ['type' => 'Int!']], description: "Get a user"),
        new QueryCollection(resolver: UsersResolver::class, paginationEnabled: false, description: "Get all users"),
        new Query(resolver: FailingResolver::class, name: 'failingUser', description: "Always throws"),
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

    #[SubQuery(resolver: DeferredAddressResolver::class, args: [], deferred: true)]
    public ?Address $billingAddress;

    #[SubQuery(resolver: DeferredCompanyResolver::class, args: [], deferred: true)]
    public ?Company $company;

    /** @var Order[] */
    #[SubQueryCollection(resolver: DeferredOrdersResolver::class, output: Order::class, deferred: true)]
    public array $orders;

    #[SubQuery(resolver: StatResolver::class, args: [], output: Stat::class, description: "Compute (heavy) stats")]
    public Stat $userStats;
}
