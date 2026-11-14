<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class InputSubObject
{
    #[Assert\Positive]
    public int $weight;
    public ?UploadedFile $optionalFileInSubObject = null;
}
