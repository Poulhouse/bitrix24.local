<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use KPLab\Market\Service\RestContext;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$logDir = $_SERVER['DOCUMENT_ROOT'].'/upload/kplab_market/logs/'.date('Y-m-d').'/raw/';
@mkdir($logDir, 0775, true);

$time = time();
$request = Application::getInstance()->getContext()->getRequest();

$data = [
    'POST' => $request->getPostList()->toArray(),
    'GET'  => $request->getQueryList()->toArray(),
    'FILES'=> $_FILES ?? [],
    'SERVER'=> [
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
    ],
];

file_put_contents(
    $logDir.$time.'_'.basename(__FILE__).'.log',
    print_r($data, true)
);

Loader::includeModule('kplab.market');

$request = Application::getInstance()->getContext()->getRequest();
$data = $request->getValues();

$ctx = RestContext::fromRequest($data);
$event = $data['event'] ?? 'unknown';

// Логирование события
$ctx->rest()->call('log', []); // фиктивный вызов для примера, можно убрать
KPLab\Market\Service\Rest::log('event', ['event' => $event, 'payload' => $data]);

// Попытка выполнить кастомный обработчик из /local/apps/{app}/events/
$appCode = $data['app'] ?? $data['APP_CODE'] ?? 'unknown';
$path = Application::getDocumentRoot() . "/local/apps/{$appCode}/events/{$event}.php";

if (file_exists($path)) {
    include $path;
    KPLab\Market\Service\Rest::log('event_run', [
        'app' => $appCode,
        'event' => $event,
        'domain' => $ctx->auth['domain'] ?? null,
        'payload' => $_REQUEST,
    ],$ctx);
} else {
    KPLab\Market\Service\Rest::log('event_missing', [
        'app' => $appCode,
        'event' => $event,
        'domain' => $ctx->auth['domain'] ?? null,
        'payload' => $_REQUEST,
    ],$ctx);
}

echo 'OK';
