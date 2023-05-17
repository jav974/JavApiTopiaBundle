<?php

namespace Jav\ApiTopiaBundle\Api\GraphQL\Resolver;

interface MutationResolverInterface
{
    public function __invoke(array $context);
}
