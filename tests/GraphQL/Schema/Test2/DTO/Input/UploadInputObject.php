<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadInputObject
{
    public string $name;
    public UploadedFile $mandatoryFile;
    public ?UploadedFile $optionalFile = null;
    /** @var UploadedFile[]  */
    public array $mandatoryFiles;
    /** @var UploadedFile[]|null */
    public ?array $optionalFiles = null;
    public InputSubObject $mandatorySubObject;
    public ?InputSubObject $optionalSubObject = null;
    /** @var InputSubObject[]  */
    public array $mandatorySubObjects;
    /** @var InputSubObject[]|null */
    public ?array $optionalSubObjects = null;
}
