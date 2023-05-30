<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;

#[ApiResource(
    graphQLOperations: []
)]
class ApiResourceObject2
{
    public function __construct(public int $id)
    {
    }
}
