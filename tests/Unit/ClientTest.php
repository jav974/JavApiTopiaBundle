<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\GraphQL\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ClientTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private function createClient(array $defaultHeaders = []): Client
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"data":{"ok":true}}');
        }, 'http://localhost');

        return new Client($http, $defaultHeaders);
    }

    private function header(string $name): ?string
    {
        foreach ($this->requests[0]['options']['headers'] as $header) {
            if (stripos($header, "$name:") === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }

    public function testJsonRequest(): void
    {
        $result = $this->createClient(['X-Debug' => '1'])->request('/graphql', '{ ok }', ['a' => 1]);

        $this->assertSame(['data' => ['ok' => true]], $result);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('application/json', $this->header('Content-Type'));
        $this->assertSame('1', $this->header('X-Debug'));
        $this->assertSame('{"query":"{ ok }","variables":{"a":1}}', $this->requests[0]['options']['body']);
    }

    public function testNoXdebugCookieByDefault(): void
    {
        $this->createClient()->request('/graphql', '{ ok }');

        $this->assertNull($this->header('Cookie'));
    }

    public function testGraphQLRequest(): void
    {
        $this->createClient()->request('/graphql?x=1', '{ ok }', ['a' => 1], [], Client::CONTENT_TYPE_GRAPHQL);

        $this->assertSame('application/graphql', $this->header('Content-Type'));
        $this->assertSame('{ ok }', $this->requests[0]['options']['body']);
        $this->assertStringContainsString('&variables=' . rawurlencode('{"a":1}'), $this->requests[0]['url']);
    }

    public function testFormDataRequest(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'apitopia');
        file_put_contents($path, 'hello');
        $file = new UploadedFile($path, 'hello.txt', null, null, true);

        $this->createClient()->request('/graphql', 'mutation ($f: Upload!) { up(f: $f) }', [], ['f' => $file]);

        $this->assertStringStartsWith('multipart/form-data', $this->header('Content-Type'));
        $body = $this->requests[0]['options']['body'];
        $body = is_string($body) ? $body : implode('', iterator_to_array($body()));
        $this->assertStringContainsString('{"file0":["variables.f"]}', $body);
        $this->assertStringContainsString('name="file0"; filename="hello.txt"', $body);
        $this->assertStringContainsString('hello', $body);
    }

    public function testInvalidContentType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createClient()->request('/graphql', '{ ok }', [], [], 'text/plain');
    }
}
