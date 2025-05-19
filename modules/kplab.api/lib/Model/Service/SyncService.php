<?php

namespace KPLab\API\V2\Model\Service;

use KPLab\API\V2\Interfaces\Http\HttpClientInterface;

class SyncService
{
    private HttpClientInterface $http;
    private string $method;
    private string $endpoint;

    public function __construct(
        HttpClientInterface $httpClient,
        string $method,
        string $endpoint = ''
    ) {
        $this->http = $httpClient;
        $this->method = $method;
        $this->endpoint = $endpoint;
    }
    public function Execute(array $meta, array $payload = []): array
    {
        return $this->http->request(
            $this->method,
            $this->endpoint,
            [
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'Accept-Charset' => 'UTF-8',
            ],
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $meta
        );
    }
}