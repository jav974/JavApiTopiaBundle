<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\MutationResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input\UploadInputObject;

class FileUploadMutationResolver implements MutationResolverInterface
{

    public function __invoke(array $context): object|array|null
    {
        return match ($context['info']->fieldName) {
            'createWithFileUploadInputAsArg' => $this->createWithFileUploadInputAsArg($context['args']['input']),
            'createWithFileUploadInputDeserialized' => $this->createWithFileUploadInputDeserialized($context['args']['input']),
            'createWithFileUploadInputRaw' => $this->createWithFileUploadInputRaw($context['args']['input']),
            default => null
        };
    }

    private function createWithFileUploadInputAsArg(array $input): ApiResourceObject
    {
        $ret = new ApiResourceObject();

        $ret->id = 1;
        $ret->mandatoryString = $input['theFile']->getClientOriginalName();

        return $ret;
    }

    private function createWithFileUploadInputDeserialized(UploadInputObject $uploadInputObject): ApiResourceObject
    {
        $ret = new ApiResourceObject();

        $ret->id = 1;
        $ret->mandatoryString = $uploadInputObject->name;
        $ret->optionalString = $uploadInputObject->mandatoryFile->getClientOriginalName();
        $ret->mandatoryArrayOfString = array_map(fn($file) => $file->getClientOriginalName(), $uploadInputObject->mandatoryFiles);

        if ($uploadInputObject->mandatorySubObject->optionalFileInSubObject) {
            $ret->mandatoryArrayOfString[] = $uploadInputObject->mandatorySubObject->optionalFileInSubObject->getClientOriginalName();
        }

        $ret->mandatoryArrayOfFloat = [$uploadInputObject->mandatorySubObject->weight];

        foreach ($uploadInputObject->mandatorySubObjects as $subObject) {
            $ret->mandatoryArrayOfFloat[] = $subObject->weight;

            if ($subObject->optionalFileInSubObject) {
                $ret->mandatoryArrayOfString[] = $subObject->optionalFileInSubObject->getClientOriginalName();
            }
        }

        return $ret;
    }

    private function createWithFileUploadInputRaw(array $uploadInputObject): ApiResourceObject
    {
        $ret = new ApiResourceObject();

        $ret->id = 1;
        $ret->mandatoryString = $uploadInputObject['name'];
        $ret->optionalString = $uploadInputObject['mandatoryFile']->getClientOriginalName();
        $ret->mandatoryArrayOfString = array_map(fn($file) => $file->getClientOriginalName(), $uploadInputObject['mandatoryFiles']);

        if ($uploadInputObject['mandatorySubObject']['optionalFileInSubObject']) {
            $ret->mandatoryArrayOfString[] = $uploadInputObject['mandatorySubObject']['optionalFileInSubObject']->getClientOriginalName();
        }

        $ret->mandatoryArrayOfFloat = [$uploadInputObject['mandatorySubObject']['weight']];

        foreach ($uploadInputObject['mandatorySubObjects'] as $subObject) {
            $ret->mandatoryArrayOfFloat[] = $subObject['weight'];

            if (isset($subObject['optionalFileInSubObject'])) {
                $ret->mandatoryArrayOfString[] = $subObject['optionalFileInSubObject']->getClientOriginalName();
            }
        }

        return $ret;
    }
}
