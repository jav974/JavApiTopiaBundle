<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

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
}
