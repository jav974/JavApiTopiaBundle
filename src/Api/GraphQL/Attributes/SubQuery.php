<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SubQuery extends Query
{
    /**
     * @param array<string, array<string, string>> $args
     */
    public function __construct(
        string $resolver,
        array $args,
        ?string $output = null,
        ?string $description = null
    ) {
        parent::__construct($resolver, $output, null, $args, $description);
    }
}
