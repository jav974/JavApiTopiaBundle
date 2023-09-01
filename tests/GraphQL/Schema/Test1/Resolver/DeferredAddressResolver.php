<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Address;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Enums\AddressType;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class DeferredAddressResolver implements DeferredResolverInterface
{
    public function __invoke(array $context, DeferredResults $results): void
    {
        /** @var User $parent */
        foreach ($context['source']['collection'] as $parent) {
            $address = new Address();
            $address->city = 'City ' . $parent->id;
            $address->street = 'Street ' . $parent->id;
            $address->country = 'Country ' . $parent->id;
            $address->type = $parent->id % 2 === 0 ? AddressType::HOME : AddressType::WORK;
            $results->set($parent, $address);
        }
    }
}
