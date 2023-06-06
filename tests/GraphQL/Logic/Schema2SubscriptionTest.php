<?php

namespace Jav\ApiTopiaBundle\Tests\GraphQL\Logic;

use Jav\ApiTopiaBundle\Test\AbstractApiTestCase;

class Schema2SubscriptionTest extends AbstractApiTestCase
{
    public function testSubscription(): void
    {
        $response = $this->graphQL('/test/graphql/test2', /**@lang GraphQL */'
            subscription {
                subscribeApiResourceObject2Updated(input: {id: "ApiResourceObject2:1", clientSubscriptionId: "test"}) {
                    apiResourceObject2 {
                        _id
                    }
                    clientSubscriptionId
                    mercureUrl
                }
            }
        ');

        $this->assertResponseIsSuccessful();
        $this->assertGraph($response, [
            'data' => [
                'subscribeApiResourceObject2Updated' => [
                    'apiResourceObject2' => null,
                    'clientSubscriptionId' => 'test',
                    'mercureUrl' => 'http://localhost:8000/.well-known/mercure?topic=' . rawurlencode('ApiResourceObject2:1')
                ]
            ]
        ]);
    }
}
