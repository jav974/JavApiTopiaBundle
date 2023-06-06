<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

interface SubscriptionResolverInterface
{
    /**
     * @param array<string, mixed> $context
     * @return object|array<mixed>|null
     */
    public function __invoke(array $context): object|array|null;
}
