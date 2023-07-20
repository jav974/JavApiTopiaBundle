<?php

namespace Jav\ApiTopiaBundle\Test;

use Jav\ApiTopiaBundle\GraphQL\Client;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function setUpClient(): void
    {
        $kernelBrowser = self::createClient([], [
            'CONTENT_TYPE' => 'multipart/form-data'
        ]);
        $this->client = new Client($kernelBrowser);
    }

    /**
     * @param array<string, mixed> $variables
     * @param UploadedFile[] $files
     * @return array<mixed>
     * @throws JsonException
     */
    protected function graphQL(string $uri, string $gql, array $variables = [], array $files = []): array
    {
        return $this->client->request($uri, $gql, $variables, $files);
    }

    /**
     * @param array<mixed> $actual
     * @param array<mixed> $expected
     */
    protected function assertGraph(array $actual, array $expected, string $path = ''): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual, "GraphQL path $path");

            $currentPath = sprintf('%s[%s]', $path, $key);
            if (is_array($value) && is_array($actual[$key])) {
                $this->assertGraph($actual[$key], $value, $currentPath);
            } else {
                $this->assertSame($value, $actual[$key], "GraphQL path $currentPath");
            }
        }
    }
}
