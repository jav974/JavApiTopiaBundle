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
        ?array $args = null,
        ?string $description = null,
        public bool $deserialize = true
    ) {
        parent::__construct($name, $resolver, $output, $args, $description);
    }
}
