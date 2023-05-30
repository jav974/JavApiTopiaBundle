<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject2;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\PureObject;

class ApiResourceObjectItemResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): object|array|null
    {
        $result = new ApiResourceObject();

        $result->id = $context['args']['id'];
        $result->mandatoryString = 'mandatoryString';
        $result->optionalString = 'optionalString';
        $result->mandatoryBool = true;
        $result->optionalBool = false;
        $result->mandatoryInt = 1;
        $result->optionalInt = 2;
        $result->mandatoryFloat = 1.1;
        $result->optionalFloat = 2.2;
        $result->mandatoryPureObject = new PureObject('mandatoryPureObject');
        $result->optionalPureObject = null;
        $result->mandatoryApiResourceSubObjectAsPureObject = new ApiResourceObject2(42);
        $result->optionalApiResourceSubObjectAsPureObject = null;
        $result->mandatoryArrayOfString = ['mandatoryString1', 'mandatoryString2'];
        $result->optionalArrayOfString = ['optionalString1', 'optionalString2'];
        $result->mandatoryArrayOfInt = [1, 2];
        $result->optionalArrayOfInt = [3, 4];
        $result->mandatoryArrayOfFloat = [1.1, 2.2];
        $result->optionalArrayOfFloat = [3.3, 4.4];
        $result->mandatoryArrayOfBool = [true, false];
        $result->optionalArrayOfBool = [false, true];
        $result->mandatoryArrayOfPureObject = [new PureObject('a'), new PureObject('b')];
        $result->optionalArrayOfPureObject = null;

        return $result;
    }
}
