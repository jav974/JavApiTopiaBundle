<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SchemaBuildTest extends KernelTestCase
{
    private SchemaBuilder $schemaBuilder;

    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->schemaBuilder = $container->get(SchemaBuilder::class);
    }

    public function testBuild(): void
    {
        $this->schemaBuilder->build();

        $this->assertFileExists(__DIR__ . '/../Output/schema.test1.graphql');
        $this->assertFileExists(__DIR__ . '/../Output/schema.test2.graphql');
    }

    public function testSchemaTypes(): void
    {
        $this->schemaBuilder->build();
        $filename = __DIR__ . '/../Output/schema.test2.graphql';
        $this->assertFileExists($filename);
        $schema = file_get_contents($filename);

        $this->assertStringContainsString('type Query {', $schema, 'Query not found');
        $this->assertStringContainsString('type ApiResourceObject implements Node {', $schema, 'ApiResourceObject not found');
        $this->assertStringContainsString('type ApiResourceObject2 implements Node {', $schema, 'ApiResourceObject2 not found');

        $apiResourceObjectType = $this->extractTypeBlock($schema, 'ApiResourceObject');

        // Assert scalar types are correctly reported in graphql schema
        $this->assertStringContainsString("id: ID!\n", $apiResourceObjectType, 'ApiResourceObject `id` field of type ID! not found');
        $this->assertStringContainsString("_id: Int!\n", $apiResourceObjectType, 'ApiResourceObject `_id` field of type Int! not found');
        $this->assertStringContainsString("mandatoryString: String!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryString` field of type String! not found');
        $this->assertStringContainsString("optionalString: String\n", $apiResourceObjectType, 'ApiResourceObject `optionalString` field of type String not found');
        $this->assertStringContainsString("mandatoryInt: Int!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryInt` field of type Int! not found');
        $this->assertStringContainsString("optionalInt: Int\n", $apiResourceObjectType, 'ApiResourceObject `optionalInt` field of type Int not found');
        $this->assertStringContainsString("mandatoryFloat: Float!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryFloat` field of type Float! not found');
        $this->assertStringContainsString("optionalFloat: Float\n", $apiResourceObjectType, 'ApiResourceObject `optionalFloat` field of type Float not found');
        $this->assertStringContainsString("mandatoryBool: Boolean!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryBool` field of type Boolean! not found');
        $this->assertStringContainsString("optionalBool: Boolean\n", $apiResourceObjectType, 'ApiResourceObject `optionalBool` field of type Boolean not found');
        // Assert array of scalars are correctly reported in graphql schema
        $this->assertStringContainsString("mandatoryArrayOfString: [String!]!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryArrayOfString` field of type [String!]! not found');
        $this->assertStringContainsString("optionalArrayOfString: [String!]\n", $apiResourceObjectType, 'ApiResourceObject `optionalArrayOfString` field of type [String!] not found');
        $this->assertStringContainsString("mandatoryArrayOfInt: [Int!]!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryArrayOfInt` field of type [Int!]! not found');
        $this->assertStringContainsString("optionalArrayOfInt: [Int!]\n", $apiResourceObjectType, 'ApiResourceObject `optionalArrayOfInt` field of type [Int!] not found');
        $this->assertStringContainsString("mandatoryArrayOfFloat: [Float!]!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryArrayOfFloat` field of type [Float!]! not found');
        $this->assertStringContainsString("optionalArrayOfFloat: [Float!]\n", $apiResourceObjectType, 'ApiResourceObject `optionalArrayOfFloat` field of type [Float!] not found');
        $this->assertStringContainsString("mandatoryArrayOfBool: [Boolean!]!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryArrayOfBool` field of type [Boolean!]! not found');
        $this->assertStringContainsString("optionalArrayOfBool: [Boolean!]\n", $apiResourceObjectType, 'ApiResourceObject `optionalArrayOfBool` field of type [Boolean!] not found');
        // Assert object types are correctly reported in graphql schema
        $this->assertStringContainsString("mandatoryPureObject: PureObject!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryPureObject` field of type PureObject! not found');
        $this->assertStringContainsString("optionalPureObject: PureObject\n", $apiResourceObjectType, 'ApiResourceObject `optionalPureObject` field of type PureObject not found');
        $this->assertStringContainsString("mandatoryApiResourceSubObjectAsPureObject: ApiResourceObject2!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryApiResourceSubObjectAsPureObject` field of type ApiResourceObject2! not found');
        $this->assertStringContainsString("optionalApiResourceSubObjectAsPureObject: ApiResourceObject2\n", $apiResourceObjectType, 'ApiResourceObject `optionalApiResourceSubObjectAsPureObject` field of type ApiResourceObject2 not found');
        // Assert object types are correctly reported in graphql schema
        $this->assertStringContainsString("mandatoryArrayOfPureObject: [PureObject!]!\n", $apiResourceObjectType, 'ApiResourceObject `mandatoryArrayOfPureObject` field of type [PureObject!]! not found');
        $this->assertStringContainsString("optionalArrayOfPureObject: [PureObject!]\n", $apiResourceObjectType, 'ApiResourceObject `optionalArrayOfPureObject` field of type [PureObject!] not found');
    }

    private function extractTypeBlock(string $schema, string $type): ?string
    {
        return self::extractBlock($schema, 'type', $type);
    }

    private function extractBlock(string $schema, string $blockType, string $type): ?string
    {
        $pos = mb_strpos($schema, "$blockType $type");

        if ($pos === false) {
            return null;
        }

        return mb_substr($schema, $pos, mb_strpos($schema, '}', $pos) - $pos + 1);
    }
}
