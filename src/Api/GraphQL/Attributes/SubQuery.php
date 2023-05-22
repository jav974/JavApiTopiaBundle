<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SubQuery extends Query
{
    /**
     * @param array<string, array<string, string>>|null $args
     */
    public function __construct(
        string $resolver,
        ?string $output = null,
        ?string $description = null,
        ?array $args = null
    ) {
        parent::__construct($resolver, $output, null, $description, $args);
    }
}
