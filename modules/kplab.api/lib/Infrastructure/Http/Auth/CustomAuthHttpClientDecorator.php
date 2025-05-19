<?php

namespace KPLab\API\V2\Infrastructure\Http\Auth;

use KPLab\API\V2\Interfaces\Http\Auth\AuthSchemeInterface;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;

final class CustomAuthHttpClientDecorator implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private AuthSchemeInterface $scheme,      // стратегия «какой заголовок»
        private $keyHeader
    ) {}

    /**
     * @inheritDoc
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $meta = []
    ): array
    {
        $headers[$this->keyHeader] = $this->scheme->headerValue();
        return $this->inner->request($method, $url, $headers, $body, $meta);
    }
}