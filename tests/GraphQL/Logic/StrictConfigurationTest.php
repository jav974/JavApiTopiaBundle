<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

/**
 * Hardened configuration (see the "test_strict" environment of the test kernel).
 */
class StrictConfigurationTest extends AbstractApiTestCase
{
    protected static array $kernelOptions = ['environment' => 'test_strict', 'debug' => false];

    public function testErrorDetailsAreHiddenWithoutDebug(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ failingUser { _id } }');
        $json = json_encode($response);

        $this->assertSame('Internal server error', $response['errors'][0]['message']);
        $this->assertStringNotContainsString('secret internal detail', $json);
        $this->assertStringNotContainsString('"trace"', $json);
    }

    public function testConfiguredErrorStatusCode(): void
    {
        $this->graphQL('/test/graphql/test1', '{ failingUser { _id } }');

        $this->assertSame(200, $this->kernelBrowser->getResponse()->getStatusCode());
    }

    public function testIntrospectionCanBeDisabled(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ __schema { queryType { name } } }');

        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('introspection', strtolower($response['errors'][0]['message']));
    }

    public function testQueryDepthIsLimited(): void
    {
        $response = $this->graphQL('/test/graphql/test1', '{ users { _id } }');
        $this->assertArrayNotHasKey('errors', $response);

        $response = $this->graphQL('/test/graphql/test1', '{ users { orders { edges { node { reference } } } } }');
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('depth', strtolower($response['errors'][0]['message']));
    }

    public function testPageSizeIsCapped(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ apiResourceObject(id: 1) { apiResourceSubObjectAsCursorBasedSubQueryCollection(first: 6) { totalCount } } }');

        $this->assertArrayHasKey('errors', $response);
        $this->assertSame('Cannot request more than 5 items per page.', $response['errors'][0]['message']);
    }

    public function testConfiguredDefaultPageSize(): void
    {
        $response = $this->graphQL('/test/graphql/test2', '{ apiResourceObject(id: 1) { apiResourceSubObjectAsOffsetBasedSubQueryCollection { items { _id } } } }');

        $this->assertCount(2, $response['data']['apiResourceObject']['apiResourceSubObjectAsOffsetBasedSubQueryCollection']['items']);
    }

    public function testGraphiQLCanBeDisabled(): void
    {
        $this->kernelBrowser->request('GET', '/api/graphiql/test1');

        $this->assertSame(404, $this->kernelBrowser->getResponse()->getStatusCode());
    }
}
