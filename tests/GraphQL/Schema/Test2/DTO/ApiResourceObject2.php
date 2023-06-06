<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;

#[ApiResource(
    graphQLOperations: [
        new Subscription(name: 'subscribeApiResourceObject2Updated')
    ]
)]
class ApiResourceObject2
{
    public function __construct(public int $id)
    {
    }
}
