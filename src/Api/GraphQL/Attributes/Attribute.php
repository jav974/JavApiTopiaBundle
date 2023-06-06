<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

abstract class Attribute
{
    /**
     * @param string|null $name The optional name of the endpoint (Will be generated if missing)
     * @param string|null $resolver The resolver for this endpoint
     * @param string|null $output The output type for this query (Put the desired FQN: Entity::class)
     * @param array<string, array<string, string>>|null $args The arguments for this endpoint
     * @param string|null $description An optional description that will be added to the generated schema
     */
    public function __construct(
        public ?string $name = null,
        public ?string $resolver = null,
        public ?string $output = null,
        public ?array $args = null,
        public ?string $description = null
    ) {
    }
}
