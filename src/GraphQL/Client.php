<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use InvalidArgumentException;
use JsonException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Client
{
    public const CONTENT_TYPE_JSON = 'application/json';
    public const CONTENT_TYPE_GRAPHQL = 'application/graphql';
    public const CONTENT_TYPE_FORM_DATA = 'multipart/form-data';

    protected CurlHttpClient|KernelBrowser $client;

    public function __construct(?KernelBrowser $client = null)
    {
        if ($client !== null) {
            $this->client = $client;
        } else {
            $this->client = new CurlHttpClient();
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<UploadedFile> $files
     * @throws JsonException
     * @return array{data?: array<string, mixed>, errors?: array<array<string, mixed>>} The decoded query result
     */
    public function request(string $endpoint, string $gql, array $variables = [], array $files = [], ?string $contentType = null): array
    {
        if ($contentType === self::CONTENT_TYPE_FORM_DATA || !empty($files)) {
            $response = $this->requestFormData($endpoint, $gql, $variables, $files);
        } elseif ($contentType === self::CONTENT_TYPE_GRAPHQL) {
            $response = $this->requestGraphQL($endpoint, $gql, $variables);
        } elseif ($contentType === self::CONTENT_TYPE_JSON || $contentType === null) {
            $response = $this->requestJson($endpoint, $gql, $variables);
        } else {
            throw new InvalidArgumentException(sprintf('Invalid content type: %s', $contentType));
        }

        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<UploadedFile> $files
     */
    protected function requestFormData(string $endpoint, string $gql, array $variables = [], array $files = []): ResponseInterface|Response
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

        if ($this->client instanceof KernelBrowser) {
            $this->client->request(
                'POST',
                $endpoint,
                $query,
                $_files
            );
            return $this->client->getResponse();
        }

        if (!empty($_files)) {
            foreach ($_files as $key => $file) {
                $query[$key] = new DataPart($file->getContent(), $file->getClientOriginalName());
            }
        }

        $formData = new FormDataPart($query);

        return $this->client->request(
            'POST',
            $endpoint,
            [
                'headers' => $formData->getPreparedHeaders()->toArray() + ['Cookie' => 'XDEBUG_SESSION=eclipse'],
                'body' => $formData->bodyToString()
            ]
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    protected function requestGraphQL(string $endpoint, string $gql, array $variables = []): ResponseInterface|Response
    {
        if (!empty($variables)) {
            $endpoint .= !str_contains($endpoint, '?') ? '?' : '&';
            $endpoint .= http_build_query(['variables' => $variables]);
        }

        return $this->client->request(
            'POST',
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => self::CONTENT_TYPE_GRAPHQL,
                    'Cookie' => 'XDEBUG_SESSION=eclipse'
                ],
                'body' => $gql
            ]
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    protected function requestJson(string $endpoint, string $gql, array $variables = []): ResponseInterface|Response
    {
        $body = ['query' => $gql, 'variables' => $variables];

        if ($this->client instanceof KernelBrowser) {
            $this->client->jsonRequest(
                'POST',
                $endpoint,
                $body
            );
            return $this->client->getResponse();
        }

        return $this->client->request(
            'POST',
            $endpoint,
            [
                'headers' => [
                    'Content-Type' => self::CONTENT_TYPE_JSON,
                    'Cookie' => 'XDEBUG_SESSION=eclipse'
                ],
                'body' => json_encode($body)
            ]
        );
    }
}
