<?php
namespace KPLab\Market\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Web\HttpClient;
use KPLab\Market\Logger\LoggerInterface;

/**
 * Универсальный REST-клиент для Bitrix24 (через RestContext)
 */
class Rest
{
    public const RETRY_LIMIT = 1;
    public const TIMEOUT = 15;

    protected RestContext $context;
    private LoggerInterface $logger;
    private static ?LoggerInterface $sLogger = null;

    public function __construct(RestContext $context, LoggerInterface $logger)
    {
        $this->context = $context;
        $this->logger = $logger;
        self::$sLogger = $logger;
    }

    /* -------------------------------------------------------------
     * Базовый REST-вызов
     * ------------------------------------------------------------- */
    public function call(string $method, array $params = [], int $retry = 0): array
    {
        $auth = $this->context->auth ?? [];
        if (empty($auth['access_token']) || empty($auth['domain'])) {
            $this->logger->error('rest_call_failed', [
                'error' => 'no_auth_data',
                'context' => $auth,
                'method' => $method,
            ]);
            return ['error' => 'no_auth_data'];
        }

        $url = "https://{$auth['domain']}/rest/{$method}.json";
        $params['auth'] = $auth['access_token'];

        $http = new HttpClient([
            'socketTimeout' => static::TIMEOUT,
            'streamTimeout' => static::TIMEOUT,
        ]);

        $response = $http->post($url, $params);
        $data = json_decode($response, true) ?: [];

        if (!empty($data['error']) && $data['error'] === 'expired_token' && $retry < static::RETRY_LIMIT) {
            if ($this->context->refreshTokens()) {
                return $this->call($method, $params, $retry + 1);
            }
        }

        $this->logger->info('rest_call', [
            'method' => $method,
            'params' => $params,
            'result' => $data,
            'http_status' => $http->getStatus(),
            'domain' => $auth['domain'] ?? 'unknown',
            'member_id' => $auth['member_id'] ?? 'nomember',
            'request_id' => $context['request_id'] ?? uniqid(),
        ]);

        return $data;
    }

    /* -------------------------------------------------------------
     * Batch-вызов с зависимыми командами
     * ------------------------------------------------------------- */
    public function batch(array $commands, int $halt = 0): array
    {
        $cmd = [];

        foreach ($commands as $key => $data) {
            if (!isset($data['method'])) continue;

            $method = $data['method'];
            $params = !empty($data['params'])
                ? '?' . http_build_query($data['params'])
                : '';

            $cmd[$key] = $method . $params;
        }

        if (empty($cmd)) {
            return ['error' => 'no_commands'];
        }

        $payload = [
            'halt' => $halt,
            'cmd'  => $cmd,
        ];

        // Используем внутренний метод call() — он сам знает auth из контекста
        $res = $this->call('batch', $payload);
        self::log('rest_batch', [
            'payload' => $payload,
            'result' => $res,
            'domain' => $this->context->auth['domain'] ?? null,
        ]);

        return $res;
    }


    /* -------------------------------------------------------------
     * Логирование вызовов
     * ------------------------------------------------------------- */
    public static function log(string $type, $data, ?RestContext $ctx = null): void
    {
        /*if (self::$sLogger) {
            self::$sLogger->write($type, $data, $ctx);
        }*/

        $domain = $ctx['domain'] ?? 'unknown';
        $member = $ctx['member_id'] ?? 'nomember';
        $basePath = Application::getDocumentRoot() . '/upload/kplab_market/logs/';
        $path = $basePath . date('Y-m-d') . '/' . $domain . '_' . $member . '/';

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logEntry = [
            'timestamp' => date('c'),
            'level'     => $type,
            'message' => $data,
            'context' => $ctx,
            'request_id' => $ctx['request_id'] ?? uniqid(),
        ];

        file_put_contents($path . time() . '_' . $type . '.json', json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    }



}
