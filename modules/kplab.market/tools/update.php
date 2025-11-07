<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use KPLab\Market\Service\RestContext;
use KPLab\Market\Repository\InstallationRepositoryInterface;
use KPLab\Market\Service\Rest;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loader::includeModule('kplab.market');

// 1. Получаем входящие данные (Bitrix24 отправляет POST с 'update=Y')
$data = Application::getInstance()->getContext()->getRequest()->getValues();

// 2. ✅ ИСПОЛЬЗУЕМ БЕЗОПАСНЫЙ КОНТЕКСТ — ВСЁ ПРОВЕРЕНО ЧЕРЕЗ app.info
try {
    $ctx = RestContext::fromRequest($data);
} catch (\RuntimeException $e) {
    Rest::log('update_failed_context', [
        'error' => $e->getMessage(),
        'request' => $data,
    ]);
    http_response_code(403);
    echo "Forbidden: Invalid update request.";
    exit;
}

// 3. ✅ ПРОВЕРЯЕМ, ЧТО ЭТО ДЕЙСТВИТЕЛЬНО ЗАПРОС НА ОБНОВЛЕНИЕ
// Bitrix24 отправляет update=Y — без этого запрос игнорируется
if (empty($data['update']) || $data['update'] !== 'Y') {
    Rest::log('update_invalid_type', [
        'expected_update' => 'Y',
        'received' => $data['update'] ?? 'missing',
        'request' => $data,
    ]);
    http_response_code(400);
    echo "Bad request: missing or invalid 'update' flag.";
    exit;
}

// 4. ✅ ИСПОЛЬЗУЕМ РЕПОЗИТОРИЙ, а не устаревший AppStore
$repository = new \KPLab\Market\Repository\InstallationRepository();

// 5. Обновляем статус в HL-блоке — только если запись существует
$success = $repository->updateStatus(
    $ctx->auth()['member_id'],
    $ctx->appCode(),
    [
        'UF_STATUS' => 'UPDATED',
        'UF_UPDATED_AT' => (new DateTime())->toString(),
        'UF_UPDATE_REASON' => 'platform_update', // можно расширить: 'manual', 'auto', 'version_change'
    ]
);

if (!$success) {
    Rest::log('update_failed_upsert', [
        'member_id' => $ctx->auth()['member_id'],
        'app_code' => $ctx->appCode(),
        'error'     => 'Installation record not found or update failed',
    ]);
    http_response_code(500);
    echo "Internal error: failed to update installation record.";
    exit;
}

// 6. ✅ Логируем успешное обновление
Rest::log('update_success', [
    'app_code'     => $ctx->appCode(),
    'domain'     => $ctx->auth()['domain'],
    'member_id'    => $ctx->auth()['member_id'],
    'auth_id'     => $ctx->auth()['auth_id'],
    'payload'     => $data, // для отладки — можно убрать в продакшене
], $ctx);

// 7. ✅ Отвечаем строго в формате, который ожидает Bitrix24
echo "UPDATED " . $ctx->appCode();