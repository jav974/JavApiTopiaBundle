<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use GraphQLRelay\Relay;
use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredCompanyResolver;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\Resolver\DeferredOrdersResolver;

class Schema1DeferredFixesTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DeferredCompanyResolver::$calls = 0;
        DeferredOrdersResolver::$calls = 0;
    }

    public function testTwoDeferredFieldsOnSameClassDoNotShareResults(): void
    {
        $response = $this->graphQL('/test/graphql/test1', /** @lang GraphQL */'
            query {
                users {
                    _id
                    billingAddress { city }
                    company { name }
                }
            }
        ');

        $this->assertGraph($response, [
            'data' => [
                'users' => [
                    ['_id' => 1, 'billingAddress' => ['city' => 'City 1'], 'company' => ['name' => 'Company 1']],
                    ['_id' => 2, 'billingAddress' => ['city' => 'City 2'], 'company' => null],
                ]
            ]
        ]);
    }

    public function testDeferredResolverIsCalledOnceEvenWhenSomeParentsHaveNoResult(): void
    {
        $this->graphQL('/test/graphql/test1', '{ users { _id company { name } } }');

        // User 2 has no company: it must not trigger a second batch call
        $this->assertSame(1, DeferredCompanyResolver::$calls);
    }

    public function testDeferredPaginatedSubQueryCollectionReturnsAConnection(): void
    {
        $response = $this->graphQL('/test/graphql/test1', /** @lang GraphQL */'
            query {
                users {
                    _id
                    orders(first: 2) {
                        totalCount
                        pageInfo { hasNextPage }
                        edges { node { reference } }
                    }
                }
            }
        ');

        $this->assertGraph($response, [
            'data' => [
                'users' => [
                    [
                        '_id' => 1,
                        'orders' => [
                            'totalCount' => 3,
                            'pageInfo' => ['hasNextPage' => true],
                            'edges' => [
                                ['node' => ['reference' => 'U1-O1']],
                                ['node' => ['reference' => 'U1-O2']],
                            ],
                        ],
                    ],
                    [
                        '_id' => 2,
                        'orders' => [
                            'totalCount' => 3,
                            'edges' => [
                                ['node' => ['reference' => 'U2-O1']],
                                ['node' => ['reference' => 'U2-O2']],
                            ],
                        ],
                    ],
                ]
            ]
        ]);
        $this->assertCount(2, $response['data']['users'][0]['orders']['edges']);
        $this->assertSame(1, DeferredOrdersResolver::$calls);
    }

    public function testDeferredStateDoesNotLeakBetweenRequests(): void
    {
        $this->graphQL('/test/graphql/test1', '{ users { _id company { name } } }');
        $response = $this->graphQL('/test/graphql/test1', '{ users { _id company { name } } }');

        $this->assertSame('Company 1', $response['data']['users'][0]['company']['name']);
        $this->assertSame(2, DeferredCompanyResolver::$calls);
    }

    public function testRelayNodeField(): void
    {
        $globalId = Relay::toGlobalId('test1.User', '42');
        $response = $this->graphQL('/test/graphql/test1', /** @lang GraphQL */'
            query ($id: ID!) {
                node(id: $id) {
                    id
                    ... on User { _id email }
                }
            }
        ', ['id' => $globalId]);

        $this->assertGraph($response, [
            'data' => [
                'node' => [
                    'id' => $globalId,
                    '_id' => 42,
                    'email' => 'node42@test.com',
                ]
            ]
        ]);
    }

    public function testRelayNodeFieldReturnsNullForUnknownId(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ node(id: "' . Relay::toGlobalId('test1.Nope', '1') . '") { id } }');

        $this->assertGraph($response, ['data' => ['node' => null]]);
    }
}
