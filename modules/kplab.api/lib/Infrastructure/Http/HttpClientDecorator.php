<?php
namespace KPLab\API\V2\Infrastructure\Http;

use KPLab\API\V2\Infrastructure\Logger\NullHttpLogger;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\API\V2\Infrastructure\Logger\DbHttpLogger;

final class HttpClientDecorator implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly bool                $emulate = false
    ) {}

    public function request(
        string  $method,
        string  $url,
        array   $headers = [],
        ?string $body    = null,
        array   $meta    = []
    ): array {
        // --- Эмуляция ------------------------------------------------------
        if ($this->emulate) {
            return [
                'status'  => 200,
                'body'    => json_encode(['status' => 'Success', 'response' => 'Emulated request successful!']),
                'headers' => [],
            ];
        }

        /* ---------- реальный запрос ---------- */
        return $this->inner->request($method, $url, $headers, $body);
    }
}
