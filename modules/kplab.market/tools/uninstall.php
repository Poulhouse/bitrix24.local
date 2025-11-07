<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use KPLab\Market\Service\RestContext;
use KPLab\Market\Repository\InstallationRepositoryInterface;
use KPLab\Market\Service\Rest;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loader::includeModule('kplab.market');

// 1. Получаем входящие данные (POST или GET — Bitrix24 использует POST)
$data = Application::getInstance()->getContext()->getRequest()->getValues();

// 2. ✅ ИСПОЛЬЗУЕМ БЕЗОПАСНЫЙ КОНТЕКСТ — ВСЁ ПРОВЕРЕНО ЧЕРЕЗ app.info
try {
    $ctx = RestContext::fromRequest($data);
} catch (\RuntimeException $e) {
    Rest::log('uninstall_failed_context', [
        'error' => $e->getMessage(),
        'request' => $data,
    ]);
    http_response_code(403);
    echo "Forbidden: Invalid uninstall request.";
    exit;
}

// 3. Проверяем, что это действительно запрос на деинсталляцию
// Bitrix24 отправляет POST с 'uninstall' => 'Y'
if (empty($data['uninstall']) || $data['uninstall'] !== 'Y') {
    Rest::log('uninstall_invalid_type', [
        'expected_uninstall' => 'Y',
        'received' => $data['uninstall'] ?? 'missing',
        'request' => $data,
    ]);
    http_response_code(400);
    echo "Bad request: missing or invalid 'uninstall' flag.";
    exit;
}

// 4. ✅ ИСПОЛЬЗУЕМ РЕПОЗИТОРИЙ, а не устаревший AppStore
$repository = new \KPLab\Market\Repository\InstallationRepository();

// 5. Обновляем статус установки в HL-блоке
$success = $repository->updateStatus(
    $ctx->auth()['member_id'],
    $ctx->appCode(),
    [
        'UF_STATUS' => 'INACTIVE',
        'UF_UNINSTALLED_AT' => (new DateTime())->toString(),
        'UF_UNINSTALL_REASON' => 'user_uninstall', // можно расширить: 'expired', 'admin_removed', etc.
    ]
);

if (!$success) {
    Rest::log('uninstall_failed_update', [
        'member_id' => $ctx->auth()['member_id'],
        'app_code' => $ctx->appCode(),
        'error'     => 'Failed to update HL-block status',
    ]);
    http_response_code(500);
    echo "Internal error: failed to update installation status.";
    exit;
}

// 6. ✅ Логируем успешную деинсталляцию
Rest::log('uninstall_success', [
    'app_code'     => $ctx->appCode(),
    'domain'     => $ctx->auth()['domain'],
    'member_id'    => $ctx->auth()['member_id'],
    'auth_id'     => $ctx->auth()['auth_id'],
], $ctx);

// 7. ✅ Отвечаем строго в формате, который ожидает Bitrix24
echo "OK";