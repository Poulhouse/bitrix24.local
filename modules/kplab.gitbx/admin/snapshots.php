<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Config\Option;
use KPLab\GitBx\Snapshot\SnapshotManager;
use KPLab\GitBx\Exception\SnapshotException;
use KPLab\GitBx\Config\ModuleSettings;

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";

$moduleId = 'kplab.gitbx';

Loc::loadMessages(__FILE__);

if (!Loader::includeModule($moduleId)) {
    $APPLICATION->AuthForm("Не удалось загрузить модуль {$moduleId}");
}

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < "R") {
    $APPLICATION->AuthForm("Доступ запрещён");
}

// UI-расширения
Extension::load("ui.buttons");
Extension::load("ui.notification");

// Заголовок страницы
$APPLICATION->SetTitle("GitBx: Snapshot CRM");

// ------------------------
// Обработка действий
// ------------------------
$request     = Application::getInstance()->getContext()->getRequest();
$action      = $request->get('action');
$resultJson  = null;
$downloadUrl = null;
$error       = null;

if ($action === 'make_snapshot' && check_bitrix_sessid())
{
    try {
        $manager  = new SnapshotManager();
        $snapshot = $manager->buildArray();

        // Кодируем JSON (красивый, человекочитаемый)
        $resultJson = Json::encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        // Путь к директории снапшотов берём из настроек модуля

        $relativeDir = ModuleSettings::get('dir_snapshots');
        $relativeDir = rtrim($relativeDir, '/') . '/';

        $docRoot = Application::getDocumentRoot();
        $absoluteDir = $docRoot . $relativeDir;

        // Создаём директорию при необходимости
        if (!CheckDirPath($absoluteDir)) {
            throw new \RuntimeException("Не удалось создать директорию для снапшотов: {$absoluteDir}");
        }

        // Имя файла
        $fileName = 'snapshot_' . date('Ymd_His') . '.json';
        $filePath = $absoluteDir . $fileName;

        if (file_put_contents($filePath, $resultJson) === false) {
            throw new \RuntimeException("Не удалось записать файл снапшота: {$filePath}");
        }

        // URL для скачивания
        $downloadUrl = $relativeDir . $fileName;

    } catch (SnapshotException $e) {
        $error = "Ошибка при формировании snapshot: " . $e->getMessage();
    } catch (\Throwable $e) {
        $error = "Системная ошибка: " . $e->getMessage();
    }
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php";

// ------------------------
// Вывод сообщений
// ------------------------
if ($error) {
    CAdminMessage::ShowMessage([
        "TYPE"    => "ERROR",
        "MESSAGE" => $error,
    ]);
}

if ($resultJson) {
    CAdminMessage::ShowMessage([
        "TYPE"    => "OK",
        "MESSAGE" => "Snapshot успешно создан",
        "DETAILS" => "Файл сохранён и доступен по ссылке ниже.",
        "HTML"    => true,
    ]);
}
?>

    <form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="make_snapshot">

        <div style="margin-bottom: 20px;">
            <input type="submit"
                   value="Сделать snapshot"
                   class="ui-btn ui-btn-success ui-btn-lg">
        </div>
    </form>

<?php if ($resultJson): ?>
    <div style="margin-top: 20px;">
        <h3>JSON Snapshot</h3>
        <textarea style="width: 100%; height: 300px; font-family: monospace;"><?= htmlspecialcharsbx($resultJson) ?></textarea>

        <?php if ($downloadUrl): ?>
            <div style="margin-top: 15px;">
                <a href="<?= htmlspecialcharsbx($downloadUrl) ?>"
                   class="ui-btn ui-btn-primary"
                   download>
                    Скачать snapshot
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
