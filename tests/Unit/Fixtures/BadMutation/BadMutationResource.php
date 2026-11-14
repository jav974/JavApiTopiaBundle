<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures\BadMutation;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Mutation;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\SimpleMutationResolver;

#[ApiResource(mutations: [
    new Mutation(name: 'badMutation', resolver: SimpleMutationResolver::class, input: BadMutationInput::class),
])]
class BadMutationResource
{
    public int $id;
}
