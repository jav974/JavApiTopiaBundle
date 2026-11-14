<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject2;

class ApiResourceObject2ByIdResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): object|array|null
    {
        // "id" is declared as ID!: it is received as a string
        $id = $context['args']['id'];

        return is_string($id) && ctype_digit($id) ? new ApiResourceObject2((int) $id) : null;
    }
}
