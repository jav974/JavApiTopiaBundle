<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_PARAMETER)]
class Query extends Attribute
{
    /**
     * @param array<string, array<string, string>>|null $args
     */
    public function __construct(string $resolver, ?string $output = null, ?string $name = null, ?array $args = null, ?string $description = null)
    {
        parent::__construct($name, $resolver, $output, $args, $description);
    }
}
