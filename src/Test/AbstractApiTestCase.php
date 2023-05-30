<?php

namespace Jav\ApiTopiaBundle\Test;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpClient();
    }

    protected function setUpClient(): void
    {
        $this->client = self::createClient([], [
            'CONTENT_TYPE' => 'multipart/form-data'
        ]);
    }

    /**
     * @param array<string, mixed> $variables
     * @param UploadedFile[] $files
     * @return array<mixed>
     */
    protected function graphQL(string $uri, string $gql, array $variables = [], array $files = []): array
    {
        $_files = [];
        $i = 0;
        $query = ['operations' => ['query' => $gql], 'map' => []];

        foreach ($files as $fileParamName => $file) {
            $variables[$fileParamName] = null;
            $query['map']["$i"] = ["variables.$fileParamName"];
            $_files[$i] = $file;
            ++$i;
        }

        if (!empty($variables)) {
            $query['operations']['variables'] = $variables;
        }

        $query['operations'] = json_encode($query['operations']);
        $query['map'] = json_encode($query['map']);

        $this->client->request(
            'POST',
            $uri,
            $query,
            $_files
        );

        return $this->getJsonResponse();
    }

    protected function getResponse(): Response
    {
        return $this->client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    protected function getJsonResponse(): array
    {
        return json_decode($this->getResponse()->getContent(), true);
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
