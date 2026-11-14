<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures\DuplicateSubscription;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Subscription;

#[ApiResource(graphQLOperations: [new Subscription(name: 'duplicated')])]
class ResourceB
{
    public int $id;
}
