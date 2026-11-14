<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_PARAMETER)]
class Mutation extends Attribute
{
    /**
     * @param array<string, array<string, string>>|null $args
     * @param bool $validate Validate the deserialized input with the Symfony Validator (when installed)
     * @param string[]|null $validationGroups Validation groups ("Default" when null)
     */
    public function __construct(
        string $name,
        string $resolver,
        public ?string $input = null,
        ?string $output = null,
        ?array $args = null,
        ?string $description = null,
        public bool $deserialize = true,
        public bool $validate = true,
        public ?array $validationGroups = null,
    ) {
        parent::__construct($name, $resolver, $output, $args, $description);
    }
}
