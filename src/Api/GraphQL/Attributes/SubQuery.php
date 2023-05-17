<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SubQuery extends Query
{
    public function __construct(
        string $resolver,
        ?string $output = null,
        ?string $description = null,
        ?array $args = null
    ) {
        parent::__construct($resolver, $output, null, $description, $args);
    }
}
