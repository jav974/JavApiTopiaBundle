<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures\DuplicateMutation;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\SimpleMutationResolver;

#[ApiResource(graphQLOperations: [new Mutation(name: 'duplicated', resolver: SimpleMutationResolver::class)])]
class ResourceB
{
    public int $id;
}
