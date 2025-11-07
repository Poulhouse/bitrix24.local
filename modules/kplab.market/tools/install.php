<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use KPLab\Market\Repository\InstallationRepositoryInterface;
use KPLab\Market\Service\RestContext;
use KPLab\Market\Service\Rest;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loader::includeModule('kplab.market');

// 1. Получаем входящие данные
$data = Application::getInstance()->getContext()->getRequest()->getValues();

// 2. Создаём безопасный контекст — ВСЁ проверено через app.info
try {
    $ctx = RestContext::fromRequest($data);
} catch (\RuntimeException $e) {
    Rest::log('install_failed_context', [
        'error' => $e->getMessage(),
        'request' => $data,
    ]);
    http_response_code(403);
    echo "Forbidden: Invalid installation request.";
    exit;
}

// 3. Уже проверено в RestContext::fromRequest() — повторная проверка избыточна
// Но оставим для дебага и логирования (опционально)
if (empty($ctx->auth()['domain']) || empty($ctx->keys()['UF_CLIENT_ID'])) {
    Rest::log('install_failed_missing_data', [
        'auth_domain' => $ctx->auth()['domain'] ?? 'missing',
        'client_id' => $ctx->keys()['UF_CLIENT_ID'] ?? 'missing',
    ]);
    http_response_code(400);
    echo "Bad request: missing required data.";
    exit;
}

// 4. ✅ ИСПОЛЬЗУЕМ РЕПОЗИТОРИЙ, а не устаревший AppStore
$repository = new \KPLab\Market\Repository\InstallationRepository();

// 5. Подготавливаем данные для установки
$expiresAt = (new DateTime())->add('1 hour');

$installData = [
    'UF_APP_CODE'     => $ctx->appCode(), // ← Используем метод, а не свойство
    'UF_DOMAIN'        => $ctx->auth()['domain'],
    'UF_ACCESS_TOKEN' => $ctx->auth()['access_token'],
    'UF_REFRESH_TOKEN' => $ctx->auth()['refresh_token'] ?? '', // может быть пустым
    'UF_EXPIRES_AT'    => $expiresAt->toString(),
    'UF_STATUS'        => 'ACTIVE',
    'UF_INSTALLED_AT' => (new DateTime())->toString(),
    'UF_MEMBER_ID'     => $ctx->auth()['member_id'], // ← Важно: из app.info
];

// 6. Сохраняем установку — с обработкой ошибок
if (!$repository->upsert($ctx->auth()['member_id'], $ctx->appCode(), $installData)) {
    Rest::log('install_failed_upsert', [
        'member_id' => $ctx->auth()['member_id'],
        'app_code' => $ctx->appCode(),
        'data'     => $installData,
    ]);
    http_response_code(500);
    echo "Internal error: failed to save installation data.";
    exit;
}

// 7. ✅ РЕГИСТРАЦИЯ СОБЫТИЙ — БЕЗ $_SERVER!
// Используем домен из app.info — он гарантированно верный
$domain = $ctx->auth()['domain'];
$baseUrl = 'https://' . $domain . '/local/modules/kplab.market/tools/';

$commands = [
    'uninstall' => [
        'method' => 'event.bind',
        'params' => [
            'event' => 'ONAPPUNINSTALL',
            'handler' => $baseUrl . 'uninstall.php?app=' . urlencode($ctx->appCode()),
        ],
    ],
    'update' => [
        'method' => 'event.bind',
        'params' => [
            'event' => 'ONAPPUPDATE',
            'handler' => $baseUrl . 'update.php?app=' . urlencode($ctx->appCode()),
        ],
    ],
];

// 8. Выполняем batch-запрос через REST-клиент
$rest = $ctx->rest();
$result = $rest->batch($commands);

// 9. Логируем успех
Rest::log('install_success', [
    'app_code'     => $ctx->appCode(),
    'domain'     => $domain,
    'member_id'    => $ctx->auth()['member_id'],
    'batch_result' => $result,
], $ctx);

// 10. Отдаем ответ Bitrix24 — обязательный формат
echo "<!DOCTYPE html>
<html>
<head>
    <script src=\"//api.bitrix24.com/api/v1/\"></script>
    <script>
        BX24.init(function() {
            BX24.installFinish();
        });
    </script>
</head>
<body>
    Installation completed successfully for {$ctx->appCode()}.
</body>
</html>";