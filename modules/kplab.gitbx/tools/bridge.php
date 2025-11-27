<?php
/**
 * GitBx Bridge — универсальная прокси-прослойка между текущим порталом
 * и удалённым TEST/PROD сервером. Поддерживает GET и POST.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Context;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Logger;


// ------------------------------------------------------------
// JSON header
// ------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');


// ------------------------------------------------------------
// Load module
// ------------------------------------------------------------
if (!Loader::includeModule('kplab.gitbx')) {
    echo json_encode(['status' => 'error', 'message' => 'module_not_loaded']);
    die();
}


// ------------------------------------------------------------
// Read method + incoming params
// ------------------------------------------------------------
$request = Context::getCurrent()->getRequest();
$method  = $request->getRequestMethod();

$action = $request->get('action');
if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'action_missing']);
    die();
}

$payload = [];
$payloadRaw = $request->get('payload');

// POST or GET payload
if (is_string($payloadRaw) && $payloadRaw !== '') {
    $decoded = json_decode($payloadRaw, true);
    $payload = is_array($decoded) ? $decoded : [];
} elseif (is_array($payloadRaw)) {
    $payload = $payloadRaw;
}


// ------------------------------------------------------------
// CONFIG
// ------------------------------------------------------------
$remoteUrl   = rtrim(ModuleSettings::remoteUrl(), '/');
$remoteToken = ModuleSettings::remoteToken();
$timeout     = ModuleSettings::apiTimeout();

if (!$remoteUrl || !$remoteToken) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Remote URL/token not configured',
    ]);
    die();
}


// ------------------------------------------------------------
// LOG incoming
// ------------------------------------------------------------
Logger::info('BRIDGE INIT', [
    'method'      => $method,
    'headers'     => function_exists('getallheaders') ? getallheaders() : [],
    'query'       => $request->getQueryList()->toArray(),
    'post'        => $request->getPostList()->toArray(),
    'payload'     => $payload,
]);

Logger::info('ENV', [
    'current_token' => ModuleSettings::currentToken(),
    'remote_token'  => $remoteToken,
    'remote_url'    => $remoteUrl,
    'host'          => $_SERVER['HTTP_HOST'] ?? '',
]);


// ------------------------------------------------------------
// Подготовка HTTP клиента
// ------------------------------------------------------------
$client = new HttpClient([
    'socketTimeout' => $timeout,
    'streamTimeout' => $timeout,
]);
$client->setHeader('Authorization', 'Bearer ' . $remoteToken, true);
$client->setHeader('Content-Type', 'application/json', true);


// ------------------------------------------------------------
// Формирование удалённого адреса
// ------------------------------------------------------------
$queryString = $request->getRequestUri();
$queryString = parse_url($queryString, PHP_URL_QUERY);

// собираем final URL
$remoteApi = $remoteUrl . '/bitrix/services/main/ajax.php?action=' . urlencode($action);

// переносим остальные GET параметры (кроме action)
if ($method === 'GET' && $queryString) {
    parse_str($queryString, $queryData);
    unset($queryData['action']); // action уже передан выше

    if (!empty($queryData)) {
        $remoteApi .= '&' . http_build_query($queryData);
    }
}

Logger::info('BRIDGE outbound prepare', [
    'remote_api' => $remoteApi,
    'method'     => $method,
    'payload'    => $payload,
]);


// ------------------------------------------------------------
// EXECUTE GET or POST
// ------------------------------------------------------------
try {

    if ($method === 'GET') {

        Logger::info('BRIDGE outbound GET', ['url' => $remoteApi]);

        $raw = $client->get($remoteApi);

    } else { // POST (или что-то ещё → считаем POST)

        Logger::info('BRIDGE outbound POST', [
            'url'     => $remoteApi,
            'payload' => $payload,
        ]);

        $raw = $client->post($remoteApi, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $httpCode = $client->getStatus();
    $decoded  = json_decode($raw, true);

    Logger::info('BRIDGE RAW REMOTE', ['raw' => $raw]);
    Logger::info('BRIDGE outbound response', [
        'http_status' => $httpCode,
        'decoded'     => $decoded,
    ]);

    if (!is_array($decoded)) {
        throw new \Exception("Invalid JSON from remote portal");
    }


    // --------------------------------------------------------
    // УНИФИКАЦИЯ steps/result
    // --------------------------------------------------------
    $steps  = [];
    $result = null;

    if (isset($decoded['data']) && is_array($decoded['data'])) {

        $lvl1 = $decoded['data'];

        if (isset($lvl1['steps']) || isset($lvl1['result'])) {
            $steps  = $lvl1['steps'] ?? [];
            $result = $lvl1['result'] ?? null;
        }

        if (isset($lvl1['data']) && is_array($lvl1['data'])) {
            $lvl2 = $lvl1['data'];

            if (isset($lvl2['steps']) || isset($lvl2['result'])) {
                $steps  = $lvl2['steps'] ?? $steps;
                $result = $lvl2['result'] ?? $result;
            }
        }
    }


    // --------------------------------------------------------
    // Итоговый ответ UI
    // --------------------------------------------------------
    echo json_encode([
        'status' => 'success',
        'data'   => [
            'remote_http'     => $httpCode,
            'remote_response' => $decoded,
            'steps'           => $steps,
            'result'          => $result,
        ],
    ]);
    die();


} catch (\Throwable $e) {

    Logger::error("BRIDGE failed", [
        'exception' => $e->getMessage(),
    ]);

    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
    die();
}

