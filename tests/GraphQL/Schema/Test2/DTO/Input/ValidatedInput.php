<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ValidatedInput
{
    #[Assert\Email]
    public string $email;

    #[Assert\Length(min: 3, groups: ['Default', 'strict'])]
    public string $name;

    #[Assert\Range(min: 18, groups: ['strict'])]
    public int $age;

    #[Assert\Valid]
    public ?InputSubObject $child = null;
}
