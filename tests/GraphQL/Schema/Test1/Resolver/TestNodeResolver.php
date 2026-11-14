<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\GraphQL\NodeResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class TestNodeResolver implements NodeResolverInterface
{
    public function resolve(string $type, string $id): mixed
    {
        if ($type !== 'test1.User') {
            return null;
        }

        // Array nodes must expose their GraphQL type name
        if (str_starts_with($id, 'array-')) {
            return ['__typename' => 'User', 'id' => (int) substr($id, 6), 'email' => "array$id@test.com"];
        }

        if ($id === 'untyped') {
            return ['id' => 1];
        }

        $user = new User();
        $user->id = (int) $id;
        $user->email = "node$id@test.com";

        return $user;
    }
}
