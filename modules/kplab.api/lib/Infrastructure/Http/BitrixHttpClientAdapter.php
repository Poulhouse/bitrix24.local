<?php
namespace KPLab\API\V2\Infrastructure\Http;

use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;

final class BitrixHttpClientAdapter implements HttpClientInterface
{
    private array $httpOptions;

    public function __construct($httpOptions = ['version' => HttpClient::HTTP_1_1]){
        $this->httpOptions = $httpOptions;
    }
    public function request(
        string  $method,
        string  $url,
        array   $headers = [],
        ?string $body    = null,
        array   $meta    = []
    ): array {
        $client = new HttpClient($this->httpOptions);
        $client->setHeaders($headers);
        $client->disableSslVerification();
        $method = strtoupper($method);

        switch ($method) {
            case 'POST':
                $client->post($url, $body);
                break;

            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                // при необходимости добавьте сюда другие HTTP-методы
                $client->query($method, $url, $body);
                break;

            case 'GET':
            default:
                $client->get($url);
        }

        return [
            'status'  => $client->getStatus(),
            'body'    => $client->getResult(),
            'headers' => $client->getHeaders()->toArray(), // вдруг пригодится
            'errors'   => $client->getError(),
        ];
    }
}
