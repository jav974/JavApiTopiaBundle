<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class UsersResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        $user1 = new User();
        $user2 = new User();

        $user1->id = 1;
        $user1->email = 'test1@test.com';
        $user2->id = 2;
        $user2->email = 'test2@test.com';

        return [$user1, $user2];
    }
}
