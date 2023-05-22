<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Client
{
    private CurlHttpClient $client;

    public function __construct()
    {
        $this->client = new CurlHttpClient();
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<UploadedFile> $files
     */
    public function request(string $endpoint, string $gql, array $variables = [], array $files = []): ResponseInterface
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
}
