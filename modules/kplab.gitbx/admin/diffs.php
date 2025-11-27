<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Snapshot\SnapshotManager;
use KPLab\GitBx\Diff\DiffManager;
use KPLab\GitBx\Exception\SnapshotException;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

$moduleId = 'kplab.gitbx';

if (!Loader::includeModule($moduleId)) {
    $APPLICATION->AuthForm("Модуль $moduleId не найден");
}

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < "R") {
    $APPLICATION->AuthForm("Доступ запрещён");
}

// Заголовок
$APPLICATION->SetTitle("GitBx: Сравнение структур (Diff)");

// Подключаем интерфейсы Bitrix
Extension::load("ui.buttons");
Extension::load("ui.notification");

$action = $_REQUEST['action'] ?? '';
$error = null;
$diffJson = null;
$diffArray = null;

if ($action === 'make_diff' && check_bitrix_sessid()) {

    try {
        $remoteEndpoint = ModuleSettings::remoteUrl();
        $remoteToken    = ModuleSettings::remoteToken();

        if (!$remoteEndpoint || !$remoteToken) {
            throw new \Exception("Не настроены параметры remote_endpoint / remote_token. Перейдите в настройки модуля.");
        }

        // 1. Snapshot тестового портала
        $snapshotManager = new SnapshotManager();
        $snapshotTest = $snapshotManager->buildArray();

        // 2. Snapshot боевого портала по API
        $snapshotProd = requestRemoteSnapshot($remoteEndpoint, $remoteToken);

        // 3. Diff
        $diffManager = new DiffManager();
        $diffArray = $diffManager->compare($snapshotTest, $snapshotProd);

        $diffJson = \Kplab\GitBx\Util\Json::encode($diffArray);

    } catch (Throwable $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

/**
 * Запрос snapshot удалённого портала
 */
function requestRemoteSnapshot(string $url, string $token): array
{
    $url = rtrim($url, '/')
        . '/local/modules/kplab.gitbx/tools/agent.php?action=snapshot';

    $opts = [
        "http" => [
            "method"  => "GET",
            "header"  => "Authorization: Bearer " . $token . "\r\n",
            "timeout" => 10,
        ]
    ];

    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        throw new SnapshotException("Не удалось получить snapshot с боевого портала");
    }

    $data = json_decode($result, true);

    if (!is_array($data)) {
        throw new SnapshotException("Некорректный ответ боевого портала");
    }

    return $data;
}

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>

<?php if ($error): ?>
    <?php \CAdminMessage::ShowMessage(["TYPE" => "ERROR", "MESSAGE" => $error]); ?>
<?php endif; ?>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="make_diff">

    <div style="margin-bottom: 20px;">
        <input type="submit" value="Сравнить test → prod" class="ui-btn ui-btn-primary ui-btn-lg">
    </div>
</form>

<?php if ($diffJson): ?>
    <h3>Результат diff</h3>

    <textarea style="width:100%; height:350px; font-family:monospace;"><?= htmlspecialcharsbx($diffJson) ?></textarea>

    <hr>

    <h3>CRM поля: изменения</h3>
    <?php
    $fields = $diffArray['crm_fields'] ?? [];
    foreach ($fields as $entity => $changes):
        ?>
        <h4><?= strtoupper($entity) ?></h4>

        <b>Добавлены:</b><br>
        <pre><?php print_r($changes['added']); ?></pre>

        <b>Изменены:</b><br>
        <pre><?php print_r($changes['changed']); ?></pre>

        <b>Удалены:</b><br>
        <pre><?php print_r($changes['removed']); ?></pre>

        <hr>
    <?php endforeach; ?>

<?php endif; ?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>
