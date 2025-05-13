<?php
namespace KPLab\API\V2\Infrastructure\Http;

use KPLab\API\V2\Infrastructure\Logger\NullHttpLogger;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\API\V2\Infrastructure\Logger\DbHttpLogger;
use KPLab\Logs;

define("LOG_HTTP_CLIENT", $_SERVER['DOCUMENT_ROOT']."/local/logs/http_client.log");

final class LoggingHttpClientDecorator implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly DbHttpLogger|NullHttpLogger     $dbLogger,
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
            $fake = [
                'status'  => 200,
                'body'    => json_encode(['status' => 'Success', 'response' => 'Emulated request successful!']),
                'headers' => [],
            ];
            $this->writeLog($method, $url, $headers, $body, $meta, $fake, true, 0);
            return $fake;
        }

        Logs\File::AddMessage($meta,"meta", LOG_HTTP_CLIENT);

        /* ---------- реальный запрос ---------- */
        $start   = hrtime(true);
        $result  = $this->inner->request($method, $url, $headers, $body);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->writeLog($method, $url, $headers, $body, $meta, $result, true, $elapsed);

        return $result;
    }

    /* --- приведём данные к формату LogsAction::Request --- */
    private function writeLog(
        string $method,
        string $url,
        array  $headers,
        ?string $body,
        array  $meta,
        array  $result,
        bool   $outRequest,
        float  $elapsedMs
    ): void {
        $this->dbLogger->log(
            $meta['objectData']     ?? [],
            $meta['methodName']     ?? '',
            $url,
            $meta['controllerName'] ?? '',
            $method,
            $result['status'] === 200 ? 'Success' : 'Failed',
            ['response' => $result['body']],
            ['start' => microtime(true) - $elapsedMs / 1000, 'duration' => $elapsedMs],
            $body,
            json_encode($headers),
            $meta['taskId']        ?? null,
            $meta['requestTypeId'] ?? 0,
            $outRequest,
            $meta['partnerName'] ?? 'Битрикс24'
        );
    }
}
