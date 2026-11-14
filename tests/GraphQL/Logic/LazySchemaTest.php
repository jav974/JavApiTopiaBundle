<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\Relay\Relay;
use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Jav\ApiTopiaBundle\GraphQL\TypeRegistry;
use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

/**
 * Once the schema index is cached, requests only build what they use.
 */
class LazySchemaTest extends AbstractApiTestCase
{
    protected static array $kernelOptions = ['environment' => 'test_lazy', 'debug' => false];

    public static function setUpBeforeClass(): void
    {
        // Build the schema index (what cache:warmup does)
        $kernel = new (static::getKernelClass())('test_lazy', false);
        $kernel->boot();
        $kernel->getContainer()->get('jav_apitopia.graphql.schema_builder')->getSchema('test2');
        $kernel->shutdown();
    }

    private function registry(): TypeRegistry
    {
        /** @var SchemaBuilder $schemaBuilder */
        $schemaBuilder = static::getContainer()->get('jav_apitopia.graphql.schema_builder');

        return (new \ReflectionProperty($schemaBuilder, 'typeRegistry'))->getValue($schemaBuilder);
    }

    public function testOnlyUsedTypesAreBuilt(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ apiResourceObject(id: 1) { _id } }');

        $this->assertSame(['data' => ['apiResourceObject' => ['_id' => 1]]], $response);
        $this->assertTrue($this->registry()->has('test2.ApiResourceObject'));
        // Building a type builds all its fields (e.g. ApiResourceObject2OffsetPage here), but unrelated types are not built
        $this->assertFalse($this->registry()->has('test2.subscribeApiResourceObject2UpdatedPayload'), 'Unused subscriptions are not built');
        $this->assertFalse($this->registry()->has('test2.createSimpleWithInputObjectRawPayload'), 'Unused mutations are not built');
    }

    public function testTypeOnlyReachableThroughAFragment(): void
    {
        $id = Relay::toGlobalId('test2.ApiResourceObject2', '5');
        $response = $this->graphQL('/test/graphql/test2', 'query ($id: ID!) { node(id: $id) { ... on ApiResourceObject2 { _id } } }', ['id' => $id]);

        $this->assertSame(['data' => ['node' => ['_id' => 5]]], $response);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function typeNames(): iterable
    {
        yield 'created by a field of another type' => ['ApiResourceObject2OffsetPage'];
        yield 'created by a mutation' => ['createSimpleWithInputObjectRawPayload'];
        yield 'created by a subscription' => ['subscribeApiResourceObject2UpdatedInput'];
        yield 'object type' => ['PureObject'];
        yield 'input class' => ['SimpleInputObject'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('typeNames')]
    public function testAnyTypeCanBeLoadedByName(string $typeName): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ __type(name: "' . $typeName . '") { name } }');

        $this->assertSame(['data' => ['__type' => ['name' => $typeName]]], $response);
    }

    public function testFullIntrospection(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ __schema { types { name } } }');
        $names = array_column($response['data']['__schema']['types'], 'name');

        $this->assertContains('ApiResourceObject2CursorConnection', $names);
        $this->assertContains('createWithFileUploadInputRawPayload', $names);
    }
}
