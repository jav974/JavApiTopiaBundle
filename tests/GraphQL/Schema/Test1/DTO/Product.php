<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;

#[ApiResource(
    graphQLOperations: [

    ]
)]
class Product
{
    public int $id;
    public string $name;
    public ?string $picture;
}
