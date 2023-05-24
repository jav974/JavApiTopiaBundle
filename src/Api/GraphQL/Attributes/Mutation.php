<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_PARAMETER)]
class Mutation extends Attribute
{
    /**
     * @param array<string, array<string, string>>|null $args
     */
    public function __construct(
        string $name,
        string $resolver,
        public ?string $input = null,
        ?string $output = null,
        ?string $description = null,
        ?array $args = null,
        public bool $deserialize = true
    ) {
        parent::__construct($resolver, $output, $name, $description, $args);
    }
}
