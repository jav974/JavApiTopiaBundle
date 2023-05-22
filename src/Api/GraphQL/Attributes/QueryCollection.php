<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE|\Attribute::TARGET_PARAMETER)]
class QueryCollection extends Query
{
    public const PAGINATION_TYPE_CURSOR = 'cursor';
    public const PAGINATION_TYPE_OFFSET = 'offset';

    /**
     * @param array<string, array<string, string>>|null $args
     */
    public function __construct(
        string $resolver,
        public bool $paginationEnabled = true,
        public ?string $paginationType = self::PAGINATION_TYPE_CURSOR,
        ?string $output = null,
        ?string $name = null,
        ?string $description = null,
        ?array $args = null
    ) {
        parent::__construct($resolver, $output, $name, $description, $args);
    }
}
