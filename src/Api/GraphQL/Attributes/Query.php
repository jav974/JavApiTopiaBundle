<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE|\Attribute::TARGET_CLASS)]
class Query extends Attribute
{
    public function __construct(string $resolver, ?string $output = null, ?string $name = null, ?string $description = null, ?array $args = null)
    {
        parent::__construct($resolver, $output, $name, $description, $args);
    }
}
