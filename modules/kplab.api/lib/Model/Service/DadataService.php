<?php namespace KPLab\API\V2\Model\Service;

use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\Logs;

define("LOG_DADATA_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_dadata.log");
class DadataService
{
    private HttpClientInterface $http;
    public function __construct(HttpClientInterface $http)
    {
        $this->http = $http;
    }
    public function findByFiasId(string $fiasId): ?array
    {
        $url = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address';
        $headers = $this->getDefaultHeaders();
        $body = json_encode(['query' => $fiasId]);

        $response = $this->http->request('POST', $url, $headers, $body);

        return $this->parseResponse($response);
    }
    public function suggestAddressByString(string $query): ?array
    {
        $url = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';
        $headers = $this->getDefaultHeaders();
        $body = json_encode(['query' => $query]);

        $response = $this->http->request('POST', $url, $headers, $body);

        return $this->parseResponse($response);
    }
    private function getDefaultHeaders(): array
    {
        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
    private function parseResponse(array $response): ?array
    {
        try {
            if (!isset($response['body']) || !is_string($response['body'])) {
                return null;
            }
            $parsed = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            foreach($parsed['suggestions'] as $suggestion) {
                if (is_null($suggestion['data']['house'])) {
                    continue;
                }
                return $suggestion['data'] ?? null;
            }

        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в DadataService::parseResponse()", LOG_DADATA_SERVICE);
            return null;
        }
    }
}