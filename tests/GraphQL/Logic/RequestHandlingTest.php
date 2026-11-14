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

    public function testApplicationGraphqlOperationNameFromQueryString(): void
    {
        $this->kernelBrowser->request(
            'POST',
            '/test/graphql/test1?operationName=Second',
            [],
            [],
            ['CONTENT_TYPE' => 'application/graphql'],
            'query First { users { _id } } query Second { user(id: 3) { _id } }'
        );
        $response = json_decode($this->kernelBrowser->getResponse()->getContent(), true);

        $this->assertGraph($response, ['data' => ['user' => ['_id' => 3]]]);
    }

    public function testMalformedJsonReturnsAnError(): void
    {
        $this->kernelBrowser->request('POST', '/test/graphql/test1', [], [], ['CONTENT_TYPE' => 'application/json'], '{not json');

        // The request is invalid: client error
        $this->assertSame(400, $this->kernelBrowser->getResponse()->getStatusCode());
        $response = json_decode($this->kernelBrowser->getResponse()->getContent(), true);
        $this->assertSame('BAD_USER_INPUT', $response['errors'][0]['extensions']['code']);
        $this->assertStringStartsWith('Invalid JSON', $response['errors'][0]['message']);
    }

    public function testNegativePageSizeIsRejected(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ apiResourceObject(id: 1) { apiResourceSubObjectAsCursorBasedSubQueryCollection(first: -1) { totalCount } } }');

        $this->assertSame('Argument "first" must be a non-negative integer.', $response['errors'][0]['message']);
    }

    public function testIntrospectionOfUnknownType(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ __type(name: "DoesNotExist") { name } }');

        $this->assertSame(['data' => ['__type' => null]], $response);
    }

    public function testGetRequest(): void
    {
        $this->kernelBrowser->request('GET', '/test/graphql/test1?query=' . rawurlencode('{ users { _id } }'), [], [], ['CONTENT_TYPE' => '']);
        $response = json_decode($this->kernelBrowser->getResponse()->getContent(), true);

        $this->assertSame(['data' => ['users' => [['_id' => 1], ['_id' => 2]]]], $response);
    }

    public function testUploadLargerThanPostMaxSize(): void
    {
        $this->kernelBrowser->request('POST', '/test/graphql/test1', ['operations' => '{}', 'map' => '{}'], [], [
            'CONTENT_TYPE' => 'multipart/form-data',
            'CONTENT_LENGTH' => PHP_INT_MAX,
        ]);

        $this->assertSame(413, $this->kernelBrowser->getResponse()->getStatusCode());
        $this->assertStringContainsString('post_max_size', $this->kernelBrowser->getResponse()->getContent());
    }
}
