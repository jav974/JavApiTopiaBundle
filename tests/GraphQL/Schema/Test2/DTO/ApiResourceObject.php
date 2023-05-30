<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO;

use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\ApiResource;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\Query;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\QueryCollection;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQuery;
use Jav\ApiTopiaBundle\Api\GraphQL\Attributes\SubQueryCollection;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2CollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObject2ItemResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectCollectionResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver\ApiResourceObjectItemResolver;

#[ApiResource(
    graphQLOperations: [
        new Query(
            resolver: ApiResourceObjectItemResolver::class,
            description: "Get a nullable api resource object",
            args: [
                'id' => ['type' => 'Int!'],
            ]
        ),
        new QueryCollection(
            resolver: ApiResourceObjectCollectionResolver::class,
            description: "Get a cursor based api resource object collection"
        ),

    ]
)]
class ApiResourceObject
{
    public int $id;
    public string $mandatoryString;
    public ?string $optionalString = null;
    public bool $mandatoryBool;
    public ?bool $optionalBool = null;
    public int $mandatoryInt;
    public ?int $optionalInt = null;
    public float $mandatoryFloat;
    public ?float $optionalFloat = null;
    public PureObject $mandatoryPureObject;
    public ?PureObject $optionalPureObject = null;
    public ApiResourceObject2 $mandatoryApiResourceSubObjectAsPureObject;
    public ?ApiResourceObject2 $optionalApiResourceSubObjectAsPureObject = null;
    /** @var string[] */
    public array $mandatoryArrayOfString;
    /** @var string[]|null */
    public ?array $optionalArrayOfString = null;
    /** @var int[] */
    public array $mandatoryArrayOfInt;
    /** @var int[]|null */
    public ?array $optionalArrayOfInt = null;
    /** @var float[] */
    public array $mandatoryArrayOfFloat;
    /** @var float[]|null */
    public ?array $optionalArrayOfFloat = null;
    /** @var bool[] */
    public array $mandatoryArrayOfBool;
    /** @var bool[]|null */
    public ?array $optionalArrayOfBool = null;
    /** @var PureObject[] */
    public array $mandatoryArrayOfPureObject;
    /** @var PureObject[]|null */
    public ?array $optionalArrayOfPureObject = null;

    #[SubQuery(resolver: ApiResourceObject2ItemResolver::class, args: ['id' => ['type' => 'Int!']], description: "Get a non nullable api resource sub object 2")]
    public ApiResourceObject2 $mandatoryApiResourceSubObjectAsSubQuery;

    #[SubQuery(resolver: ApiResourceObject2ItemResolver::class, args: ['id' => ['type' => 'Int!']], description: "Get a nullable api resource sub object 2")]
    public ?ApiResourceObject2 $optionalApiResourceSubObjectAsSubQuery = null;

    #[SubQueryCollection(resolver: ApiResourceObject2CollectionResolver::class, output: ApiResourceObject2::class, description: "Get a cursor based api resource sub object 2 collection")]
    public array $apiResourceSubObjectAsCursorBasedSubQueryCollection;

    /** @var ApiResourceObject2[] */
    #[SubQueryCollection(resolver: ApiResourceObject2CollectionResolver::class, paginationType: 'offset', description: "Get an offset based api resource sub object 2 collection")]
    public array $apiResourceSubObjectAsOffsetBasedSubQueryCollection;

    /** @var ApiResourceObject2[] */
    #[SubQueryCollection(resolver: ApiResourceObject2CollectionResolver::class, paginationEnabled: false, description: "Get all api resource sub object 2 collection")]
    public array $apiResourceSubObjectAsNotPaginatedSubQueryCollection;
}
