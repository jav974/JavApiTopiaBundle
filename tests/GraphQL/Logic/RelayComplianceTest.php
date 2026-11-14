<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\Relay\ArrayCursor;
use Jav\ApiTopiaBundle\GraphQL\Relay\Relay;
use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

/**
 * Relay server specification, without application NodeResolverInterface (automatic refetch).
 */
class RelayComplianceTest extends AbstractApiTestCase
{
    protected static array $kernelOptions = ['environment' => 'test_legacy'];

    public function testNodeRefetchesTheIdenticalObject(): void
    {
        $fields = '_id mandatoryString mandatoryInt mandatoryApiResourceSubObjectAsPureObject { id }';
        $object = $this->graphQL('/test/graphql/test2', "{ apiResourceObject(id: 7) { id $fields } }")['data']['apiResourceObject'];

        $refetched = $this->graphQL('/test/graphql/test2', 'query ($id: ID!) { node(id: $id) { id ... on ApiResourceObject { ' . $fields . ' } } }', ['id' => $object['id']]);

        $this->assertSame(['data' => ['node' => $object]], $refetched);
    }

    public function testNodeReturnsNullWhenItCannotRefetch(): void
    {
        $ids = [
            'not a global id',
            Relay::toGlobalId('test2.Unknown', '1'),
            Relay::toGlobalId('test1.ApiResourceObject', '1'), // another schema
            Relay::toGlobalId('test2.ApiResourceObject', 'abc'), // not an Int
            Relay::toGlobalId('test2.ApiResourceObject2', '1'), // no item query
        ];

        foreach ($ids as $id) {
            $response = $this->graphQL('/test/graphql/test2', 'query ($id: ID!) { node(id: $id) { id } }', ['id' => $id]);
            $this->assertSame(['data' => ['node' => null]], $response, $id);
        }
    }

    public function testPluralIdentifyingRootField(): void
    {
        $id1 = Relay::toGlobalId('test2.ApiResourceObject', '1');
        $id2 = Relay::toGlobalId('test2.ApiResourceObject', '2');

        $response = $this->graphQL('/test/graphql/test2', 'query ($ids: [ID!]!) { nodes(ids: $ids) { id ... on ApiResourceObject { _id } } }', [
            'ids' => [$id2, 'unknown', $id1],
        ]);

        // Same length and order as the input, null for objects that cannot be fetched
        $this->assertSame(['data' => ['nodes' => [['id' => $id2, '_id' => 2], null, ['id' => $id1, '_id' => 1]]]], $response);
    }

    public function testLegacyOffsetTypeNameCanBeKept(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ __type(name: "ApiResourceObject2OffsetConnection") { name } }');

        $this->assertSame(['data' => ['__type' => ['name' => 'ApiResourceObject2OffsetConnection']]], $response);
    }

    public function testUnknownCursorIsIgnoredWithPaginator(): void
    {
        // "after" points beyond the list: ApplyCursorsToEdges removes nothing, the first page is returned
        $response = $this->graphQL('/test/graphql/test2', 'query ($after: String) { apiResourceObject(id: 1) {
            apiResourceSubObjectAsCursorBasedSubQueryCollection(first: 2, after: $after, howMany: 5, returnType: "VirtualArrayPaginator") {
                edges { node { _id } }
                pageInfo { hasPreviousPage hasNextPage }
            }
        } }', ['after' => ArrayCursor::fromOffset(50)]);

        $this->assertSame([
            'edges' => [['node' => ['_id' => 1]], ['node' => ['_id' => 2]]],
            'pageInfo' => ['hasPreviousPage' => false, 'hasNextPage' => true],
        ], $response['data']['apiResourceObject']['apiResourceSubObjectAsCursorBasedSubQueryCollection']);
    }
}
