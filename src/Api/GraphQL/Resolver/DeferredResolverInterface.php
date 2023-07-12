<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;

interface DeferredResolverInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context, DeferredResults $results): void;
}
