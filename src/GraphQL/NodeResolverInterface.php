<?php

namespace Jav\ApiTopiaBundle\GraphQL;

interface NodeResolverInterface
{
    public function resolve(string $type, string $id): mixed;
}
