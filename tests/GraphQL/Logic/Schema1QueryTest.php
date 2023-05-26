<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

class Schema1QueryTest extends AbstractApiTestCase
{
    public function testUser()
    {
        $response = $this->graphQL('/test/graphql/test1', /** @lang GraphQL */'
            query {
                user(id: 1) {
                    id
                    _id
                    email
                    phone
                    address {
                        street
                        city
                        country                    
                    }
                    otherAddreses {
                        street
                        city
                        country                    
                    }
                }
            }
        ');

        $this->assertGraph($response, [
            'data' => [
                'user' => [
                    '_id' => 1,
                    'email' => 'test@test.com',
                    'phone' => null,
                    'address' => [
                        'street' => 'Street 1',
                        'city' => 'City 1',
                        'country' => 'Country 1'
                    ],
                    'otherAddreses' => [
                        [
                            'street' => 'Street 2',
                            'city' => 'City 2',
                            'country' => 'Country 2'
                        ],
                        [
                            'street' => 'Street 3',
                            'city' => 'City 3',
                            'country' => 'Country 3'
                        ]
                    ]
                ]
            ]
        ]);
    }
}
