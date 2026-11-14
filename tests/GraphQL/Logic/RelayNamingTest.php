<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RelayNamingTest extends KernelTestCase
{
    private function assertRelayCompliant(ObjectType ...$types): void
    {
        /** @var SchemaBuilder $schemaBuilder */
        $schemaBuilder = self::getContainer()->get('jav_apitopia.graphql.schema_builder');
        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => array_combine(
                array_map(fn (ObjectType $type) => lcfirst($type->name), $types),
                array_map(fn (ObjectType $type) => ['type' => $type], $types)
            )]),
        ]);

        $schemaBuilder->assertRelayCompliant($schema, 'test');
    }

    public function testDefaultTypeNames(): void
    {
        $schema = self::getContainer()->get('jav_apitopia.graphql.schema_builder')->getSchema('test2');

        $this->assertTrue($schema->hasType('ApiResourceObject2OffsetPage'));
        $this->assertFalse($schema->hasType('ApiResourceObject2OffsetConnection'));
        $this->assertTrue($schema->hasType('ApiResourceObject2CursorConnection'));
    }

    public function testObjectsNamedConnectionMustBeConnections(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"UserConnection": type names ending with "Connection" are reserved');

        $this->assertRelayCompliant(new ObjectType(['name' => 'UserConnection', 'fields' => ['count' => Type::int()]]));
    }

    public function testPageInfoIsReserved(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"PageInfo" is reserved');

        $this->assertRelayCompliant(new ObjectType(['name' => 'PageInfo', 'fields' => ['count' => Type::int()]]));
    }

    public function testGeneratedSchemasAreCompliant(): void
    {
        /** @var SchemaBuilder $schemaBuilder */
        $schemaBuilder = self::getContainer()->get('jav_apitopia.graphql.schema_builder');

        foreach (['test1', 'test2'] as $name) {
            $schemaBuilder->assertRelayCompliant($schemaBuilder->getSchema($name), $name);
        }

        $this->addToAssertionCount(1);
    }
}
