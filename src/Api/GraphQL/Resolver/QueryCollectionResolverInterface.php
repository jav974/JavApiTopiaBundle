<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

interface QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable;
}
