<?php
namespace KPLab\API\V2\Interfaces\Http;

interface HttpClientInterface
{
    /**
     * @param string      $method   'GET' | 'POST' | ...
     * @param string      $url
     * @param array       $headers  ассоц-массив
     * @param string|null $body     строка (JSON, form-urlencoded и т. д.)
     *
     * @return array{status:int, body:string, headers:array<string,string>}
     *                    └─ можно расширить, но пока достаточно
     */
    public function request(
        string $method,
        string $url,
        array  $headers = [],
        ?string $body   = null,
        array   $meta    = []
    ): array;
}
