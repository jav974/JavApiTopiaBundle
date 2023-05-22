<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

interface QueryCollectionResolverInterface
{
    /**
     * @param array<string, mixed> $context
     * @return iterable<object|array<mixed>>
     */
    public function __invoke(array $context): iterable;
}
