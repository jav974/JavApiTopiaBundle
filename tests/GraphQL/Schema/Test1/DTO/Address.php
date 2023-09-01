<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO;

use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Enums\AddressType;

class Address
{
    public string $street;
    public string $city;
    public string $country;
    public ?AddressType $type = null;
}
