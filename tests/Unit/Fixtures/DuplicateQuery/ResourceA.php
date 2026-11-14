<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures\DuplicateQuery;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\UserResolver;

#[ApiResource(graphQLOperations: [new Query(resolver: UserResolver::class, name: 'duplicated', args: ['id' => ['type' => 'Int!']])])]
class ResourceA
{
    public int $id;
}
