<?php
namespace KPLab\API\V2\Infrastructure\Http\Auth;

use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\API\V2\Interfaces\Http\Auth\AuthSchemeInterface;

final class AuthHttpClientDecorator implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private AuthSchemeInterface $scheme      // стратегия «какой заголовок»
    ) {}

    public function request(
        string  $method,
        string  $url,
        array   $headers = [],
        ?string $body    = null,
        array   $meta    = []
    ): array {
        // добавляем/перезаписываем заголовок Authorization
        $headers['Authorization'] = $this->scheme->headerValue();

        return $this->inner->request($method, $url, $headers, $body, $meta);
    }
}
