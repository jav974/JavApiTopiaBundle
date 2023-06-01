<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use GraphQLRelay\Connection\ArrayConnection;
use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

class Schema2QueryTest extends AbstractApiTestCase
{
    public function testApiResourceObject()
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            query {
                apiResourceObject(id: 1) {
                    id
                    _id
                    mandatoryString
                    optionalString
                    mandatoryInt
                    optionalInt
                    mandatoryFloat
                    optionalFloat
                    mandatoryBool
                    optionalBool
                    mandatoryPureObject {
                        type
                    }
                    optionalPureObject {
                        type
                    }
                    mandatoryArrayOfString
                    optionalArrayOfString
                    mandatoryArrayOfInt
                    optionalArrayOfInt
                    mandatoryArrayOfFloat
                    optionalArrayOfFloat
                    mandatoryArrayOfBool
                    optionalArrayOfBool
                    mandatoryArrayOfPureObject {
                        type
                    }
                    optionalArrayOfPureObject {
                        type
                    }
                    mandatoryApiResourceSubObjectAsPureObject {
                        id                    
                        _id                    
                    }
                    optionalApiResourceSubObjectAsPureObject {
                        id                    
                        _id                    
                    }
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'apiResourceObject' => [
                    '_id' => 1,
                    'mandatoryString' => 'mandatoryString',
                    'optionalString' => 'optionalString',
                    'mandatoryBool' => true,
                    'optionalBool' => false,
                    'mandatoryInt' => 1,
                    'optionalInt' => 2,
                    'mandatoryFloat' => 1.1,
                    'optionalFloat' => 2.2,
                    'mandatoryPureObject' => [
                        'type' => 'mandatoryPureObject'
                    ],
                    'optionalPureObject' => null,
                    'mandatoryApiResourceSubObjectAsPureObject' => [
                        '_id' => 42,
                    ],
                    'optionalApiResourceSubObjectAsPureObject' => null,
                    'mandatoryArrayOfString' => [
                        'mandatoryString1',
                        'mandatoryString2'
                    ],
                    'optionalArrayOfString' => [
                        'optionalString1',
                        'optionalString2'
                    ],
                    'mandatoryArrayOfInt' => [
                        1,
                        2
                    ],
                    'optionalArrayOfInt' => [
                        3,
                        4
                    ],
                    'mandatoryArrayOfFloat' => [
                        1.1,
                        2.2
                    ],
                    'optionalArrayOfFloat' => [
                        3.3,
                        4.4
                    ],
                    'mandatoryArrayOfBool' => [
                        true,
                        false
                    ],
                    'optionalArrayOfBool' => [
                        false,
                        true
                    ],
                    'mandatoryArrayOfPureObject' => [
                        [
                            'type' => 'a'
                        ],
                        [
                            'type' => 'b'
                        ]
                    ],
                    'optionalArrayOfPureObject' => null,
                ]
            ]
        ]);
    }

    public function testApiResourceObjectSubqueries(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            query {
                apiResourceObject(id: 1) {
                    mandatoryApiResourceSubObjectAsSubQuery(id: 42) {
                        id
                        _id
                    }
                    optionalApiResourceSubObjectAsSubQuery(id: 43) {
                        _id
                    }
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'apiResourceObject' => [
                    'mandatoryApiResourceSubObjectAsSubQuery' => [
                        '_id' => 42,
                    ],
                    'optionalApiResourceSubObjectAsSubQuery' => null,
                ],
            ],
        ]);
    }

    public function testSubqueryCollectionCursorBasedPaginated(): void
    {
        // Test the default array paginator (resolver returns an array containing howMany items, then Relay::createConnectionFromArray is called directly with the results)
        // Test forwards and backwards navigation with even/odd number of items and with even/odd number of items per page
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 9, 2, null);
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 2, null);
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 3, null);
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 9, 2, null);
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 2, null);
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 3, null);

        // Test the ArrayPaginator (resolver returns an ArrayPaginator instance containing howMany items, then Relay::createConnectionFromArraySlice is called with the results)
        // Test forwards and backwards navigation with even/odd number of items and with even/odd number of items per page
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 9, 2, 'ArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 2, 'ArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 3, 'ArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 9, 2, 'ArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 2, 'ArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 3, 'ArrayPaginator');

        // Test the VirtualArrayPaginator (resolver returns a VirtualArrayPaginator instance containing pageSize items only, then Relay::createConnectionFromArraySlice is called with the results)
        // Test forwards and backwards navigation with even/odd number of items and with even/odd number of items per page
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 9, 2, 'VirtualArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 2, 'VirtualArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('forwards', 10, 3, 'VirtualArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 9, 2, 'VirtualArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 2, 'VirtualArrayPaginator');
        $this->doTestSubqueryCollectionCursorBasedPaginated('backwards', 10, 3, 'VirtualArrayPaginator');
    }

    public function testSubqueryCollectionOffsetBasedPaginated(): void
    {
        $this->doTestSubqueryCollectionOffsetBasedPaginated(9, null, 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, null, 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, null, 3);

        $this->doTestSubqueryCollectionOffsetBasedPaginated(9, 'ArrayPaginator', 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, 'ArrayPaginator', 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, 'ArrayPaginator', 3);

        $this->doTestSubqueryCollectionOffsetBasedPaginated(9, 'VirtualArrayPaginator', 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, 'VirtualArrayPaginator', 2);
        $this->doTestSubqueryCollectionOffsetBasedPaginated(10, 'VirtualArrayPaginator', 3);
    }

    public function testSubqueryCollectionNotPaginatated(): void
    {
        $this->doTestSubqueryCollectionNotPaginatated(9, null);
        $this->doTestSubqueryCollectionNotPaginatated(10, 'ArrayPaginator');
        $this->doTestSubqueryCollectionNotPaginatated(10, 'VirtualArrayPaginator');
    }

    /**
     * @param string $direction 'forwards' or 'backwards'
     */
    private function doTestSubqueryCollectionCursorBasedPaginated(string $direction/* = 'forwards'*/, int $howMany/* = 10*/, int $pageSize/* = 2*/, ?string $paginatorType/* = null*/): void
    {
        // Ask for the first or last $pageSize items out of $howMany items, so we need at least $howMany/$pageSize turns of loop
        $maxLoop = (int)ceil($howMany / $pageSize);
        $afterOrBefore = null;
        $firstOrLast = $direction === 'forwards' ? 'first' : 'last';
        $paramAfterOrBefore = $direction === 'forwards' ? 'after' : 'before';
        $offset = $direction  === 'forwards' ? 0 : max($howMany - $pageSize, 0);

        while (--$maxLoop >= 0) {
            $response = $this->graphQL(
                '/test/graphql/test2',
                /** @lang GraphQL */"
                    query NamedQuery(\$$paramAfterOrBefore: String, \$howMany: Int!, \$pageSize: Int!, \$paginatorType: String) {
                        apiResourceObject(id: 1) {
                            apiResourceSubObjectAsCursorBasedSubQueryCollection($firstOrLast: \$pageSize, howMany: \$howMany, $paramAfterOrBefore: \$$paramAfterOrBefore, returnType: \$paginatorType) {
                                totalCount
                                pageInfo {
                                    hasNextPage
                                    hasPreviousPage
                                    startCursor
                                    endCursor
                                }
                                edges {
                                    cursor
                                    node {
                                        id
                                        _id
                                    }
                                }
                            }
                        }
                    }
                ",
                ['paginatorType' => $paginatorType, 'howMany' => $howMany, 'pageSize' => $pageSize, $paramAfterOrBefore => $afterOrBefore]
            );

            $edges = [];

            foreach (
                range(
                    $offset,
                    $direction === 'forwards' ? min($offset + $pageSize - 1, $howMany - 1) : ($offset === 0 ? 0 : $offset + $pageSize - 1)
                ) as $i) {
                $edges[] = [
                    'cursor' => ArrayConnection::offsetToCursor($i),
                    'node' => [
                        '_id' => $i + 1,
                    ],
                ];
            }

            if ($direction === 'forwards') {
                $endCursor = min($offset + $pageSize - 1, $howMany - 1);
            } else {
                $endCursor = $offset + $pageSize - 1;

                if ($offset === 0 && $howMany % $pageSize) {
                    $endCursor = $howMany % $pageSize - 1;
                }
            }

            $this->assertResponseIsSuccessful();
            $this->assertGraph($response, [
                'data' => [
                    'apiResourceObject' => [
                        'apiResourceSubObjectAsCursorBasedSubQueryCollection' => [
                            'totalCount' => $howMany,
                            'pageInfo' => [
                                'hasNextPage' => !($direction === 'backwards') && $maxLoop > 0, // When paginating backwards, hasNextPage is always false
                                'hasPreviousPage' => !($direction === 'forwards') && $maxLoop > 0, // When paginating forwards, hasPreviousPage is always false
                                'startCursor' => ArrayConnection::offsetToCursor($offset),
                                'endCursor' => ArrayConnection::offsetToCursor($endCursor),
                            ],
                            'edges' => $edges
                        ],
                    ],
                ],
            ]);

            if ($direction === 'forwards') {
                $afterOrBefore = ArrayConnection::offsetToCursor($offset + $pageSize - 1);
                $offset += $pageSize;
            } else {
                $afterOrBefore = ArrayConnection::offsetToCursor($offset);
                $offset = max($offset - $pageSize, 0);
            }
        }
    }

    private function doTestSubqueryCollectionOffsetBasedPaginated(int $howMany, ?string $paginatorType, int $limit): void
    {
        $maxLoop = (int)ceil($howMany / $limit);
        $offset = 0;

        while (--$maxLoop >= 0) {
            $response = $this->graphQL(
                '/test/graphql/test2',
                /** @lang GraphQL */ '
                    query NamedQuery($offset: Int!, $limit: Int, $howMany: Int!, $paginatorType: String) {
                        apiResourceObject(id: 1) {
                            apiResourceSubObjectAsOffsetBasedSubQueryCollection(offset: $offset, limit: $limit, howMany: $howMany, returnType: $paginatorType) {
                                totalCount
                                items {
                                    _id                        
                                }
                            }
                        }
                    }
                ',
                ['paginatorType' => $paginatorType, 'howMany' => $howMany, 'offset' => $offset, 'limit' => $limit]
            );

            $this->assertResponseIsSuccessful();
            $this->assertGraph($response, [
                'data' => [
                    'apiResourceObject' => [
                        'apiResourceSubObjectAsOffsetBasedSubQueryCollection' => [
                            'totalCount' => $howMany,
                            'items' => array_map(function (int $i) {
                                return [
                                    '_id' => $i + 1,
                                ];
                            }, range($offset, min($offset + $limit - 1, $howMany - 1))),
                        ],
                    ],
                ]
            ]);

            $offset += $limit;
        }
    }

    private function doTestSubqueryCollectionNotPaginatated(int $howMany, ?string $paginatorType): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            query NamedQuery($howMany: Int!, $paginatorType: String) {
                apiResourceObject(id: 1) {
                    apiResourceSubObjectAsNotPaginatedSubQueryCollection(howMany: $howMany, returnType: $paginatorType) {
                        _id
                    }
                }
            }
        ', ['paginatorType' => $paginatorType, 'howMany' => $howMany]);

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'apiResourceObject' => [
                    'apiResourceSubObjectAsNotPaginatedSubQueryCollection' => array_map(function (int $i) {
                        return [
                            '_id' => $i + 1,
                        ];
                    }, range(0, $howMany - 1)),
                ],
            ],
        ]);
    }
}
