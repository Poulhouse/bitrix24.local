<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Snapshot\SnapshotManager;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

Loader::includeModule('kplab.gitbx');

$request = Context::getCurrent()->getRequest();

// 1. Проверяем авторизацию
$incomingToken = $request->getHeader("Authorization");
$incomingToken = str_replace('Bearer ', '', (string) $incomingToken);

$allowedToken = ModuleSettings::get('remote_token');

if (!$allowedToken || $incomingToken !== $allowedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_token']);
    die();
}

// 2. Определяем действие
$action = $request->get('action');

switch ($action) {

    case 'snapshot':
        // Сформировать snapshot прода
        $manager = new SnapshotManager();
        header("Content-Type: application/json; charset=utf-8");
        echo $manager->buildJson();
        break;

    case 'ping':
        header("Content-Type: application/json");
        echo json_encode(['status' => 'ok']);
        break;

    case 'apply_migration':
        $body = json_decode(file_get_contents('php://input'), true);

        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_json']);
            die();
        }

        $runner = new \KPLab\GitBx\Migration\Scripts\Runner();
        $result = $runner->run($body);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        header("Content-Type: application/json");
        echo json_encode(['error' => 'unknown_action']);
}

die();
