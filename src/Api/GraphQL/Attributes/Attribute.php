<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

use Jav\ApiTopiaBundle\Api\Attributes\Attribute as BaseAttribute;

abstract class Attribute extends BaseAttribute
{
    public function __construct(public string $resolver, ?string $output = null, ?string $name = null, ?string $description = null, public ?array $args = null)
    {
        parent::__construct($output, $name, $description);
    }
}
