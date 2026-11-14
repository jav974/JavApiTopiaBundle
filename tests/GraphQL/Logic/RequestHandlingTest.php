<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\GraphQL\Client;
use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

/**
 * Default configuration: backward compatible behaviours.
 */
class RequestHandlingTest extends AbstractApiTestCase
{
    public function testApplicationGraphqlContentTypeAcceptsRawDocument(): void
    {
        $response = $this->client->request(
            '/test/graphql/test1',
            'query ($id: Int!) { user(id: $id) { _id email } }',
            ['id' => 7],
            [],
            Client::CONTENT_TYPE_GRAPHQL
        );

        $this->assertGraph($response, ['data' => ['user' => ['_id' => 7, 'email' => 'test@test.com']]]);
    }

    public function testApplicationGraphqlContentTypeAcceptsAnonymousQuery(): void
    {
        $response = $this->client->request('/test/graphql/test1', '{ users { _id } }', [], [], Client::CONTENT_TYPE_GRAPHQL);

        $this->assertGraph($response, ['data' => ['users' => [['_id' => 1], ['_id' => 2]]]]);
    }

    public function testApplicationGraphqlContentTypeStillAcceptsLegacyJsonPayload(): void
    {
        $this->kernelBrowser->request('POST', '/test/graphql/test1', [], [], ['CONTENT_TYPE' => 'application/graphql'], json_encode([
            'query' => '{ users { _id } }',
        ]));

        $response = json_decode($this->kernelBrowser->getResponse()->getContent(), true);

        $this->assertGraph($response, ['data' => ['users' => [['_id' => 1], ['_id' => 2]]]]);
    }

    public function testErrorsUseLegacyStatusCodeByDefault(): void
    {
        $this->graphQL('/test/graphql/test1', '{ failingUser { _id } }');

        $this->assertSame(500, $this->kernelBrowser->getResponse()->getStatusCode());
    }

    public function testBatchedQueriesWithErrorsAreDetected(): void
    {
        $this->kernelBrowser->jsonRequest('POST', '/test/graphql/test1', [
            ['query' => '{ users { _id } }'],
            ['query' => '{ failingUser { _id } }'],
        ]);

        $this->assertSame(500, $this->kernelBrowser->getResponse()->getStatusCode());
        $response = json_decode($this->kernelBrowser->getResponse()->getContent(), true);
        $this->assertCount(2, $response);
        $this->assertArrayHasKey('errors', $response[1]);
    }

    public function testDebugModeExposesErrorDetails(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ failingUser { _id } }');

        $this->assertStringContainsString('boom: secret internal detail', json_encode($response));
    }

    public function testGraphiQLIsAvailableInDebug(): void
    {
        $this->kernelBrowser->request('GET', '/api/graphiql/test1');

        $this->assertSame(200, $this->kernelBrowser->getResponse()->getStatusCode());
        $this->assertStringContainsString('\\/test\\/graphql\\/test1', $this->kernelBrowser->getResponse()->getContent());
    }

    public function testDefaultPaginationLimitStillApplies(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ apiResourceObject(id: 1) { apiResourceSubObjectAsCursorBasedSubQueryCollection(howMany: 80) { totalCount edges { node { _id } } } } }');
        $collection = $response['data']['apiResourceObject']['apiResourceSubObjectAsCursorBasedSubQueryCollection'];

        $this->assertSame(80, $collection['totalCount']);
        $this->assertCount(50, $collection['edges']);
    }
}
