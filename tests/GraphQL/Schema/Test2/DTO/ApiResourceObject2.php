<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2ByIdResolver;

#[ApiResource(
    graphQLOperations: [
        new Subscription(name: 'subscribeApiResourceObject2Updated'),
        new Query(resolver: ApiResourceObject2ByIdResolver::class, args: ['id' => ['type' => 'ID!']]),
    ]
)]
class ApiResourceObject2
{
    public function __construct(public int $id)
    {
    }
}
