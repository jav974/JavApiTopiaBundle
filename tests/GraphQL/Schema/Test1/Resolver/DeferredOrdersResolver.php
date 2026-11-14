<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Order;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class DeferredOrdersResolver implements DeferredResolverInterface
{
    public static int $calls = 0;

    public function __invoke(array $context, DeferredResults $results): void
    {
        ++self::$calls;

        /** @var User $parent */
        foreach ($context['source']['collection'] as $parent) {
            $orders = [];

            for ($i = 1; $i <= 3; $i++) {
                $order = new Order();
                $order->reference = sprintf('U%d-O%d', $parent->id, $i);
                $orders[] = $order;
            }

            $results->set($parent, $orders);
        }
    }
}
