<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver;

use Jav\ApiTopiaBundle\Api\GraphQL\DeferredResults;
use Jav\ApiTopiaBundle\Api\GraphQL\Resolver\DeferredResolverInterface;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Company;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\User;

class DeferredCompanyResolver implements DeferredResolverInterface
{
    public static int $calls = 0;

    public function __invoke(array $context, DeferredResults $results): void
    {
        ++self::$calls;

        /** @var User $parent */
        foreach ($context['source']['collection'] as $parent) {
            // Users with an even id have no company: no result is set for them on purpose
            if ($parent->id % 2 === 0) {
                continue;
            }

            $company = new Company();
            $company->name = 'Company ' . $parent->id;
            $results->set($parent, $company);
        }
    }
}
