<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryItemResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Address;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class UserResolver implements QueryItemResolverInterface
{
    public function __invoke(array $context): User
    {
        $user = new User();

        $user->id = $context['args']['id'];
        $user->email = 'test@test.com';
        $user->phone = null;
        $user->address = new Address();
        $user->address->street = 'Street 1';
        $user->address->city = 'City 1';
        $user->address->country = 'Country 1';

        $user->otherAddreses = [
            new Address(),
            new Address()
        ];

        $user->otherAddreses[0]->street = 'Street 2';
        $user->otherAddreses[0]->city = 'City 2';
        $user->otherAddreses[0]->country = 'Country 2';

        $user->otherAddreses[1]->street = 'Street 3';
        $user->otherAddreses[1]->city = 'City 3';
        $user->otherAddreses[1]->country = 'Country 3';

        return $user;
    }
}
