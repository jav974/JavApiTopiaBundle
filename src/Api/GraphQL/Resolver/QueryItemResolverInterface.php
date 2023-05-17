<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

interface QueryItemResolverInterface
{
    public function __invoke(array $context);
}
