<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use KPLab\GitBx\Snapshot\SnapshotManager;
use KPLab\GitBx\Diff\DiffManager;
use KPLab\GitBx\Migration\MigrationManager;
use KPLab\GitBx\Config\ModuleSettings;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

$moduleId = 'kplab.gitbx';

if (!Loader::includeModule($moduleId)) {
    $APPLICATION->AuthForm("Не удалось загрузить модуль $moduleId");
}

$APPLICATION->SetTitle("GitBx: Миграции");

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < "R") {
    $APPLICATION->AuthForm("Доступ запрещён");
}

Extension::load("ui.buttons");
Extension::load("ui.notification");

$action = $_REQUEST['action'] ?? '';
$error = null;
$migrationFilePath = null;
$migrationPlan = null;
$applyResult = null;

if ($action === 'make_migration' && check_bitrix_sessid()) {

    try {
        $remoteEndpoint = ModuleSettings::remoteUrl();
        $remoteToken    = ModuleSettings::remoteToken();

        if (!$remoteEndpoint || !$remoteToken) {
            throw new \Exception("Не настроены параметры удалённого портала.");
        }

        // 1. Snapshot: тестовый портал
        $snapshotManager = new SnapshotManager();
        $snapshotTest = $snapshotManager->buildArray();

        // 2. Snapshot: боевой портал
        $snapshotProd = requestRemoteSnapshot($remoteEndpoint, $remoteToken);

        // 3. Diff
        $diffManager = new DiffManager();
        $diff = $diffManager->compare($snapshotTest, $snapshotProd);

        // 4. Generate migration plan
        $migrationManager = new MigrationManager();
        $migrationPlan = $migrationManager->generate($diff);

        // 5. Save to file
        $migrationFilePath = $migrationManager->saveToFile($migrationPlan);

    } catch (Throwable $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

if ($action === 'apply_migration' && check_bitrix_sessid()) {

    try {
        $migrationJson = $_POST['migration_json'] ?? null;

        if (!$migrationJson) {
            throw new \Exception("migration_json пустой.");
        }

        $remoteEndpoint = ModuleSettings::remoteUrl();
        $remoteToken    = ModuleSettings::remoteToken();

        $applyResult = sendMigrationToProd($remoteEndpoint, $remoteToken, $migrationJson);

    } catch (Throwable $e) {
        $error = "Ошибка применения миграции: " . $e->getMessage();
    }
}


// ===============================
// ВНУТРЕННИЕ ФУНКЦИИ
// ===============================

function requestRemoteSnapshot(string $endpoint, string $token): array
{
    $url = rtrim($endpoint, '/') . '/local/modules/kplab.gitbx/tools/agent.php?action=snapshot';

    $opts = [
        "http" => [
            "method"  => "GET",
            "header"  => "Authorization: Bearer " . $token . "\r\n",
            "timeout" => 10,
        ]
    ];

    $res = file_get_contents($url, false, stream_context_create($opts));

    if ($res === false) {
        throw new \Exception("Не удалось получить snapshot с боевого портала");
    }

    $data = json_decode($res, true);

    if (!is_array($data)) {
        throw new \Exception("Некорректный ответ боевого портала");
    }

    return $data;
}

function sendMigrationToProd(string $endpoint, string $token, string $migrationJson): array
{
    $url = rtrim($endpoint, '/') . '/local/modules/kplab.gitbx/tools/agent.php?action=apply_migration';

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Bearer $token\r\nContent-Type: application/json",
            'content' => $migrationJson,
            'timeout' => 20,
        ]
    ];

    $res = file_get_contents($url, false, stream_context_create($opts));

    if ($res === false) {
        throw new \Exception("Не удалось отправить миграцию на боевой портал");
    }

    $data = json_decode($res, true);

    if (!is_array($data)) {
        throw new \Exception("Некорректный ответ боевого портала");
    }

    return $data;
}

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>

<?php if ($error): ?>
    <?php CAdminMessage::ShowMessage(["TYPE" => "ERROR", "MESSAGE" => $error]); ?>
<?php endif; ?>

<!-- Кнопка: Сформировать миграцию -->
<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" style="margin-bottom:25px;">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="make_migration">
    <input type="submit" value="Сформировать миграцию" class="ui-btn ui-btn-success ui-btn-lg">
</form>


<?php if ($migrationPlan): ?>

    <h3>Миграционный план</h3>
    <?php
    $migrationJson = json_encode($migrationPlan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ?>
    <textarea style="width:100%; height:260px; font-family:monospace;"><?= htmlspecialcharsbx($migrationJson) ?></textarea>

    <br><br>

    <h3>Доступный файл миграции</h3>
    <?php
    $fileName = basename($migrationFilePath);
    $dir_migrations    = ModuleSettings::get('dir_migrations');
    $downloadUrl = $dir_migrations . $fileName;
    ?>
    <a href="<?= $downloadUrl ?>" class="ui-btn ui-btn-primary" download>Скачать migration.json</a>

    <br><br>

    <!-- Форма применения миграции -->
    <form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="apply_migration">
        <input type="hidden" name="migration_json" value="<?= htmlspecialcharsbx($migrationJson) ?>">

        <input type="submit"
               value="Отправить и применить миграцию на боевом портале"
               class="ui-btn ui-btn-danger ui-btn-lg">
    </form>

<?php endif; ?>


<?php if ($applyResult): ?>

    <h3>Результат применения миграции</h3>
    <textarea style="width:100%; height:250px; font-family:monospace;">
<?= htmlspecialcharsbx(json_encode($applyResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
    </textarea>

<?php endif; ?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>
