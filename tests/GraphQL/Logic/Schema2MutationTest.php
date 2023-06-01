<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Schema2MutationTest extends AbstractApiTestCase
{
    public function testCreateSimpleWithInputObjectDeserialized(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation {
                createSimpleWithInputObjectDeserialized(input: {
                    name: "Test"
                    age: 42
                    height: 1.80
                    isCool: true
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                        mandatoryInt
                        mandatoryFloat
                        mandatoryBool
                    }
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createSimpleWithInputObjectDeserialized' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'Test',
                        'mandatoryInt' => 42,
                        'mandatoryFloat' => 1.80,
                        'mandatoryBool' => true
                    ]
                ]
            ]
        ]);
    }

    public function testCreateSimpleWithInputObjectRaw(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation {
                createSimpleWithInputObjectRaw(input: {
                    name: "Test"
                    age: 42
                    height: 1.80
                    isCool: true
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                        mandatoryInt
                        mandatoryFloat
                        mandatoryBool
                    }
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createSimpleWithInputObjectRaw' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'Test',
                        'mandatoryInt' => 42,
                        'mandatoryFloat' => 1.80,
                        'mandatoryBool' => true
                    ]
                ]
            ]
        ]);
    }

    public function testCreateSimpleWithInputObjectAsArg(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation {
                createSimpleWithInputObjectAsArg(input: {
                    theObject: {
                        name: "Test"
                        age: 42
                        height: 1.80
                        isCool: true
                    }
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                        mandatoryInt
                        mandatoryFloat
                        mandatoryBool
                    }
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createSimpleWithInputObjectAsArg' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'Test',
                        'mandatoryInt' => 42,
                        'mandatoryFloat' => 1.80,
                        'mandatoryBool' => true
                    ]
                ]
            ]
        ]);
    }

    public function testCreateWithFileUploadInputAsArg(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation NamedMutation($file: Upload!) {
                createWithFileUploadInputAsArg(input: {
                    theFile: $file
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                    }
                }
            }
        ', files: [
            'file' => $this->getFile('test.txt')
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createWithFileUploadInputAsArg' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'test.txt',
                    ]
                ]
            ]
        ]);
    }

    public function testCreateWithFileUploadInputDeserialized(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation NamedMutation($file1: Upload!, $file2: Upload!, $file3: Upload!, $optionalFile1: Upload, $optionalFile2: Upload) {
                createWithFileUploadInputDeserialized(input: {
                    name: "Test"
                    mandatoryFile: $file1
                    mandatoryFiles: [$file2, $file3]
                    mandatorySubObject: {
                        weight: 42
                        optionalFileInSubObject: $optionalFile1
                    }
                    mandatorySubObjects: [
                        {
                            weight: 43
                            optionalFileInSubObject: $optionalFile2
                        },
                        {
                            weight: 44
                        }
                    ]
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                        optionalString
                        mandatoryArrayOfString
                        mandatoryArrayOfFloat
                    }
                }
            }
        ', files: [
            'file1' => $this->getFile('file1.txt'),
            'file2' => $this->getFile('file2.txt'),
            'file3' => $this->getFile('file3.txt'),
            'optionalFile1' => $this->getFile('optionalFile1.txt'),
            'optionalFile2' => $this->getFile('optionalFile2.txt'),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createWithFileUploadInputDeserialized' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'Test',
                        'optionalString' => 'file1.txt',
                        'mandatoryArrayOfString' => [
                            'file2.txt',
                            'file3.txt',
                            'optionalFile1.txt',
                            'optionalFile2.txt',
                        ],
                        'mandatoryArrayOfFloat' => [
                            42,
                            43,
                            44,
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function testCreateWithFileUploadInputRaw(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /** @lang GraphQL */'
            mutation NamedMutation($file1: Upload!, $file2: Upload!, $file3: Upload!, $optionalFile1: Upload, $optionalFile2: Upload) {
                createWithFileUploadInputRaw(input: {
                    name: "Test"
                    mandatoryFile: $file1
                    mandatoryFiles: [$file2, $file3]
                    mandatorySubObject: {
                        weight: 42
                        optionalFileInSubObject: $optionalFile1
                    }
                    mandatorySubObjects: [
                        {
                            weight: 43
                            optionalFileInSubObject: $optionalFile2
                        },
                        {
                            weight: 44
                        }
                    ]
                }) {
                    apiResourceObject {
                        _id
                        mandatoryString
                        optionalString
                        mandatoryArrayOfString
                        mandatoryArrayOfFloat
                    }
                }
            }
        ', files: [
            'file1' => $this->getFile('file1.txt'),
            'file2' => $this->getFile('file2.txt'),
            'file3' => $this->getFile('file3.txt'),
            'optionalFile1' => $this->getFile('optionalFile1.txt'),
            'optionalFile2' => $this->getFile('optionalFile2.txt'),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'createWithFileUploadInputRaw' => [
                    'apiResourceObject' => [
                        '_id' => 1,
                        'mandatoryString' => 'Test',
                        'optionalString' => 'file1.txt',
                        'mandatoryArrayOfString' => [
                            'file2.txt',
                            'file3.txt',
                            'optionalFile1.txt',
                            'optionalFile2.txt',
                        ],
                        'mandatoryArrayOfFloat' => [
                            42,
                            43,
                            44,
                        ]
                    ]
                ]
            ]
        ]);
    }

    private function getFile(string $originalName): UploadedFile
    {
        // Generate a file in the Output directory with the given name
        $outputDir = __DIR__ . '/../Output';
        $path = "$outputDir/$originalName";

        file_put_contents($path, $originalName);

        return new UploadedFile(
            $path,
            $originalName
        );
    }
}
