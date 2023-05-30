<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class InputSubObject
{
    public int $weight;
    public ?UploadedFile $optionalFileInSubObject = null;
}
