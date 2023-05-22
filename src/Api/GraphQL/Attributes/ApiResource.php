<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiResource
{
    /**
     * @param string|null $name The name of the resource (defaults to the class name if omitted)
     * @param string|null $description The description of the resource
     * @param Query[]|QueryCollection[] $queries The queries to expose to Query object schema
     * @param Mutation[] $mutations The mutations to expose to Mutation object schema
     * @param array<mixed> $subscriptions The subscriptions to expose to Subscription object schema
     * @param Attribute[] $graphQLOperations All queries, mutations and subscriptions to expose to the schema (for compatibility with ApiPlatform way of defining GraphQL operations)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public array $queries = [],
        public array $mutations = [],
        public array $subscriptions = [],
        public array $graphQLOperations = [],
    ) {
        if (!empty($this->graphQLOperations)) {
            $this->queries = array_unique(array_merge($this->queries, array_filter($this->graphQLOperations, fn ($operation) => $operation instanceof Query)));
            $this->mutations = array_unique(array_merge($this->mutations, array_filter($this->graphQLOperations, fn ($operation) => $operation instanceof Mutation)));
//            $this->subscriptions = array_unique(array_merge($this->subscriptions, array_filter($this->graphQLOperations, fn ($operation) => $operation instanceof Subscription)));
        }
    }
}
