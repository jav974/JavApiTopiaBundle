<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SubQueryCollection extends QueryCollection
{
    /**
     * @param array<string, array<string, string>>|null $args
     */
    public function __construct(
        string $resolver,
        bool $paginationEnabled = true,
        ?string $paginationType = self::PAGINATION_TYPE_CURSOR,
        ?string $output = null,
        ?string $description = null,
        ?array $args = null,
        public bool $deferred = false
    ) {
        parent::__construct($resolver, $paginationEnabled, $paginationType, $output, null, $description, $args);
    }
}
