<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input\SimpleInputObject;

class SimpleMutationResolver implements MutationResolverInterface
{

    public function __invoke(array $context): object|array|null
    {
        return match ($context['info']->fieldName) {
            'createSimpleWithInputObjectDeserialized' => $this->fromObject($context['args']['input']),
            'createSimpleWithInputObjectRaw' => $this->fromArray($context['args']['input']),
            'createSimpleWithInputObjectAsArg' => $this->fromArray($context['args']['input']['theObject']),
            default => null
        };
    }

    private function fromObject(SimpleInputObject $simpleInputObject): ApiResourceObject
    {
        $ret = new ApiResourceObject();

        $ret->id = 1;
        $ret->mandatoryString = $simpleInputObject->name;
        $ret->mandatoryInt = $simpleInputObject->age;
        $ret->mandatoryFloat = $simpleInputObject->height;
        $ret->mandatoryBool = $simpleInputObject->isCool;

        return $ret;
    }

    private function fromArray(array $simpleInputObject): ApiResourceObject
    {
        $ret = new ApiResourceObject();

        $ret->id = 1;
        $ret->mandatoryString = $simpleInputObject['name'];
        $ret->mandatoryInt = $simpleInputObject['age'];
        $ret->mandatoryFloat = $simpleInputObject['height'];
        $ret->mandatoryBool = $simpleInputObject['isCool'];

        return $ret;
    }
}
