<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

class Schema1DeferredQueryTest extends AbstractApiTestCase
{
    public function testDeferredQuery(): void
    {
        $response = $this->graphQL('/test/graphql/test1', /** @lang GraphQL */'
            query {
                users {
                    _id
                    billingAddress {
                        street
                        city
                        country
                    }
                }
            }
        ');

        $this->assertGraph($response, [
            'data' => [
                'users' => [
                    [
                        '_id' => 1,
                        'billingAddress' => [
                            'street' => 'Street 1',
                            'city' => 'City 1',
                            'country' => 'Country 1'
                        ]
                    ],
                    [
                        '_id' => 2,
                        'billingAddress' => [
                            'street' => 'Street 2',
                            'city' => 'City 2',
                            'country' => 'Country 2'
                        ]
                    ]
                ]
            ]
        ]);
    }
}
