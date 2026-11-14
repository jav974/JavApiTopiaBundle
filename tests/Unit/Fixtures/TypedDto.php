<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Fixtures;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Address;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Enums\AddressType;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\StatResolver;

class TypedDto
{
    public int $id;
    public ?string $name;
    public bool $flag;
    public ?Address $address;
    /** @var Address[] */
    public array $addresses;
    /** @var int[]|null */
    public ?array $numbers;
    public array $untyped;
    public AddressType $type;
    public int|string $union;

    #[SubQuery(resolver: StatResolver::class, args: [], output: Address::class)]
    public $withOutput;

    #[SubQueryCollection(resolver: StatResolver::class, output: Address::class)]
    public $collectionWithOutput;

    #[SubQuery(resolver: StatResolver::class, args: [])]
    public $withoutOutput;
}
