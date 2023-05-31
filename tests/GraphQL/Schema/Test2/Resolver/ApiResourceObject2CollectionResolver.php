<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\QueryCollectionResolverInterface;
use Jav\ApiTopiaBundle\Pagination\ArrayPaginator;
use Jav\ApiTopiaBundle\Pagination\VirtualArrayPaginator;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test2\DTO\ApiResourceObject2;

class ApiResourceObject2CollectionResolver implements QueryCollectionResolverInterface
{
    public function __invoke(array $context): iterable
    {
        $objects = $this->getObjects($context['args']['howMany'] ?? 10);

        return match ($context['args']['returnType'] ?? null) {
            // With ArrayPaginator, we need to pass the entire array of results, not optimized for performance
            'ArrayPaginator' => new ArrayPaginator(
                $objects,
                $context['pagination']['offset'] ?? 0,
                $context['pagination']['limit'] ?? PHP_INT_MAX
            ),
            // Virtual array works only with the real page size (so it is optimized for performance)
            'VirtualArrayPaginator' => new VirtualArrayPaginator(
                array_slice($objects, $context['pagination']['offset'] ?? 0, $context['pagination']['limit'] ?? null),
                $context['pagination']['offset'] ?? 0, // But we need to tell him the 'offset' where the virtual array starts
                    $context['args']['howMany'] ?? 10 // And how many items are supposed to be in total
            ),
            default => $objects
        };
    }

    private function getObjects(int $howMany = 10): array
    {
        $objects = [];

        for ($i = 1; $i <= $howMany; $i++) {
            $objects[] = new ApiResourceObject2($i);
        }

        return $objects;
    }
}
