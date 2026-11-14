<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Relay;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Jav\ApiTopiaBundle\GraphQL\Relay\RelayTypes;
use PHPUnit\Framework\TestCase;

class RelayTypesTest extends TestCase
{
    public function testConnectionTypeNameMustEndWithConnection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RelayTypes::connectionType('ThingsPage', RelayTypes::edgeType('Thing', Type::string()));
    }

    public function testMutationEchoesClientMutationId(): void
    {
        $mutation = RelayTypes::mutation(
            'rename',
            ['name' => ['type' => Type::nonNull(Type::string())]],
            ['name' => ['type' => Type::string(), 'resolve' => fn (array $payload) => $payload['name']]],
            fn (array $input) => ['name' => strtoupper($input['name'])]
        );
        unset($mutation['inputType']);

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => ['ok' => Type::boolean()]]),
            'mutation' => new ObjectType(['name' => 'Mutation', 'fields' => ['rename' => $mutation]]),
        ]);
        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, 'mutation { rename(input: {name: "x", clientMutationId: "42"}) { name clientMutationId } }')->toArray();
        $this->assertSame(['data' => ['rename' => ['name' => 'X', 'clientMutationId' => '42']]], $result);

        $result = GraphQL::executeQuery($schema, 'mutation { rename(input: {name: "x"}) { clientMutationId } }')->toArray();
        $this->assertSame(['data' => ['rename' => ['clientMutationId' => null]]], $result);
    }

    public function testGlobalIdFieldRequiresAnId(): void
    {
        $field = RelayTypes::globalIdField('T', fn ($object) => $object['id'] ?? null);

        $this->assertSame(base64_encode('T:1'), ($field['resolve'])(['id' => 1]));
        $this->assertNull(($field['resolve'])([]));
    }
}
