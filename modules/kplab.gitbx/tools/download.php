<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Logger;

$_SERVER["DOCUMENT_ROOT"] = realpath(__DIR__ . '/../../../..');
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if (!Loader::includeModule('kplab.gitbx')) {
    http_response_code(500);
    echo "Module not loaded";
    die();
}

// Тип файла
$type = Context::getCurrent()->getRequest()->get('type');

$dirSnapshots = rtrim(ModuleSettings::dirSnapshots(), '/');

switch ($type) {
    case 'snapshot_test':
        $file = $dirSnapshots . '/snapshot_test.json';
        $downloadName = 'snapshot_test.json';
        break;

    case 'snapshot_prod':
        $file = $dirSnapshots . '/snapshot_prod.json';
        $downloadName = 'snapshot_prod.json';
        break;

    default:
        http_response_code(400);
        echo "Unknown file type";
        die();
}

// Проверяем наличие файла
if (!file_exists($file)) {
    Logger::warning("DOWNLOAD: file not found", ['type' => $type, 'path' => $file]);
    http_response_code(404);
    echo "File not found";
    die();
}

// Заголовки
header('Content-Type: application/json');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');

// Чтение файла
readfile($file);

Logger::info("DOWNLOAD: file served", ['type' => $type, 'size' => filesize($file)]);

die();
