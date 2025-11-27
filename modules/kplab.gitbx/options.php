<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\HttpClient;

$moduleId = 'kplab.gitbx';

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

$request = Application::getInstance()->getContext()->getRequest();

/**
 * =====================================================
 *  AJAX: Проверка соединения с новым Bitrix API endpoint
 * =====================================================
 */
if ($request->get('ajax') === 'check_connection' && check_bitrix_sessid()) {

    $endpoint = trim($request->get('endpoint'));
    $token    = trim($request->get('token'));

    if (!$endpoint) {
        echo json_encode(['status' => 'error', 'message' => 'Не указан endpoint']);
        die();
    }

    $client = new HttpClient([
        'timeout' => 5,
        'disableSslVerification' => true,
    ]);

    $client->setHeader('Authorization', 'Bearer ' . $token);

    $url = rtrim($endpoint, '/') .
        '/bitrix/services/main/ajax.php?action=kplab:gitbx.Ping.run';

    $responseRaw = $client->get($url);
    $httpCode = $client->getStatus();

    if ($httpCode !== 200) {

        $errors = $client->getError();
        if (is_array($errors)) {
            $errors = implode('; ', $errors);
        }

        $msg = 'HTTP ' . $httpCode;
        if ($errors) {
            $msg .= ' — ' . $errors;
        }

        if ($responseRaw !== '' && $responseRaw !== false) {
            $msg .= '. Ответ сервера: ' . mb_substr($responseRaw, 0, 200);
        }

        echo json_encode(['status' => 'error', 'message' => $msg]);
        die();
    }

    $json = json_decode($responseRaw, true);

    if (!is_array($json)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Некорректный JSON от сервера: ' . htmlspecialcharsbx($responseRaw)
        ]);
        die();
    }

    if (($json['status'] ?? '') !== 'success') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Ошибка API: ' . htmlspecialcharsbx($responseRaw)
        ]);
        die();
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Соединение OK. Ответ сервера: ' . htmlspecialcharsbx($responseRaw)
    ]);
    die();
}


/**
 * =====================================================
 *  ОБРАБОТКА POST сохранения настроек
 * =====================================================
 */
if ($request->isPost() && $request["Update"] === "Y" && check_bitrix_sessid()) {

    // Основные
    Option::set($moduleId, 'env', $request['env'] ?? 'test');
    Option::set($moduleId, 'role', $request['role'] ?? 'source');
    Option::set($moduleId, 'remote_url', trim($request['remote_url'] ?? ''));
    Option::set($moduleId, 'remote_token', trim($request['remote_token'] ?? ''));
    Option::set($moduleId, 'current_token', trim($request['current_token'] ?? ''));
    Option::set($moduleId, 'api_timeout', intval($request['api_timeout'] ?? 5));
    Option::set($moduleId, 'logging', $request['logging'] === 'Y' ? 'Y' : 'N');

    // Директории
    Option::set($moduleId, 'dir_migrations', trim($request['dir_migrations'] ?? ''));
    Option::set($moduleId, 'dir_snapshots', trim($request['dir_snapshots'] ?? ''));
    Option::set($moduleId, 'dir_logs', trim($request['dir_logs'] ?? ''));

    CAdminMessage::ShowMessage([
        'MESSAGE' => 'Настройки сохранены',
        'TYPE'    => 'OK',
    ]);
}


/**
 * =====================================================
 *  ЗАГРУЗКА ТЕКУЩИХ ЗНАЧЕНИЙ
 * =====================================================
 */
$env            = Option::get($moduleId, 'env', 'test');
$role           = Option::get($moduleId, 'role', 'source');
$remoteUrl = Option::get($moduleId, 'remote_url', '');
$remoteToken    = Option::get($moduleId, 'remote_token', '');
$currentToken    = Option::get($moduleId, 'current_token', '');
$apiTimeout     = Option::get($moduleId, 'api_timeout', 5);
$logging        = Option::get($moduleId, 'logging', 'Y');

$dirMigrations = Option::get($moduleId, 'dir_migrations', '/local/modules/kplab.gitbx/storage/migrations/');
$dirSnapshots  = Option::get($moduleId, 'dir_snapshots', '/local/modules/kplab.gitbx/storage/snapshots/');
$dirLogs       = Option::get($moduleId, 'dir_logs', '/local/modules/kplab.gitbx/storage/logs/');


$tabControl = new CAdminTabControl("tabControl", [
    ["DIV" => "main",   "TAB" => "Основные",    "TITLE" => "Основные настройки"],
    ["DIV" => "dirs",   "TAB" => "Директории",  "TITLE" => "Расположение данных"],
    ["DIV" => "sys",    "TAB" => "Системные",   "TITLE" => "Служебные функции"],
]);
?>

<style>
    #connection_result {
        margin-top: 10px;
        padding: 10px;
        border-radius: 6px;
        display: none;
    }
    #connection_result.ok {
        background: #e5f9e7;
        border: 1px solid #30a52a;
    }
    #connection_result.err {
        background: #ffe5e5;
        border: 1px solid #ce2a2a;
    }
</style>

<form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>">
    <?= bitrix_sessid_post(); ?>
    <input type="hidden" name="Update" value="Y">
    <?php $tabControl->Begin(); ?>

    <!-- ======================================================== -->
    <!-- TAB 1: Основные -->
    <!-- ======================================================== -->
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="40%">Окружение портала</td>
        <td width="60%">
            <select name="env">
                <option value="test" <?= $env === 'test' ? 'selected':'' ?>>Тестовый</option>
                <option value="prod" <?= $env === 'prod' ? 'selected':'' ?>>Боевой</option>
            </select>
        </td>
    </tr>

    <tr>
        <td>Роль агента</td>
        <td>
            <select name="role">
                <option value="source" <?= $role === 'source' ? 'selected':'' ?>>Source (Источник)</option>
                <option value="target" <?= $role === 'target' ? 'selected':'' ?>>Target (Приёмник)</option>
            </select>
        </td>
    </tr>

    <tr>
        <td>Адрес второго портала</td>
        <td>
            <input type="text" name="remote_url" size="50" value="<?= htmlspecialcharsbx($remoteUrl) ?>">
        </td>
    </tr>

    <tr>
        <td>Токен авторизации со вторым порталом</td>
        <td>
            <input type="text" name="remote_token" size="50" value="<?= htmlspecialcharsbx($remoteToken) ?>">
        </td>
    </tr>

    <tr>
        <td>Токен текущего портала</td>
        <td>
            <input type="text" name="current_token" size="50" value="<?= htmlspecialcharsbx($currentToken) ?>">
        </td>
    </tr>

    <tr>
        <td>Таймаут API (сек)</td>
        <td>
            <input type="number" name="api_timeout" min="1" value="<?= $apiTimeout ?>">
        </td>
    </tr>

    <tr>
        <td>Логирование</td>
        <td>
            <input type="checkbox" name="logging" value="Y" <?= $logging === 'Y' ? 'checked':'' ?>>
            Записывать все операции в лог
        </td>
    </tr>

    <tr>
        <td>Проверка соединения</td>
        <td>
            <button type="button" class="adm-btn" id="checkConnectionBtn">Проверить</button>

            <div id="connection_result"></div>

            <script>
                BX.ready(function() {
                    BX.bind(BX('checkConnectionBtn'), 'click', function() {

                        // API Bitrix Controllers: Ping.run
                        BX.ajax({
                            url: '/local/modules/kplab.gitbx/tools/bridge.php',
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'kplab:gitbx.Ping.run',
                                payload: {}
                            },
                            onsuccess: function(response) {
                                console.log("RESPONSE:", response);

                                let box = BX('connection_result');

                                if (response && response.status === 'success') {
                                    box.className = 'ok';
                                    box.innerHTML = 'Соединение установлено<br>' +
                                        'Ответ: ' + JSON.stringify(response);
                                } else {
                                    box.className = 'err';
                                    box.innerHTML = 'Ошибка: ' +
                                        JSON.stringify(response ?? 'null');
                                }
                                box.style.display = 'block';
                            },
                            onfailure: function(error) {
                                console.log("FAIL:", error);

                                let box = BX('connection_result');
                                box.className = 'err';
                                box.innerHTML = 'AJAX ошибка: ' + JSON.stringify(error);
                                box.style.display = 'block';
                            }
                        });
                    });
                });
            </script>
        </td>
    </tr>


    <!-- ======================================================== -->
    <!-- TAB 2: Директории -->
    <!-- ======================================================== -->
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td>Путь к миграциям</td>
        <td><input type="text" name="dir_migrations" size="50" value="<?= htmlspecialcharsbx($dirMigrations) ?>"></td>
    </tr>

    <tr>
        <td>Путь к снапшотам</td>
        <td><input type="text" name="dir_snapshots" size="50" value="<?= htmlspecialcharsbx($dirSnapshots) ?>"></td>
    </tr>

    <tr>
        <td>Путь к логам</td>
        <td><input type="text" name="dir_logs" size="50" value="<?= htmlspecialcharsbx($dirLogs) ?>"></td>
    </tr>


    <!-- ======================================================== -->
    <!-- TAB 3: Системные -->
    <!-- ======================================================== -->
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td>Очистить кеш</td>
        <td><input type="submit" name="clear_cache" value="Очистить" class="adm-btn"></td>
    </tr>

    <tr>
        <td>Пересканировать CRM</td>
        <td><input type="submit" name="rebuild_crm" value="Запустить" class="adm-btn"></td>
    </tr>

    <tr>
        <td>Сброс настроек</td>
        <td><input type="submit" name="reset_settings" value="Сбросить" class="adm-btn adm-btn-red"></td>
    </tr>

    <tr>
        <td>Версия модуля</td>
        <td><?= \Bitrix\Main\ModuleManager::getVersion($moduleId) ?></td>
    </tr>

    <tr>
        <td>Версия API</td>
        <td>v1</td>
    </tr>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" value="Сохранить" class="adm-btn-save">

    <?php $tabControl->End(); ?>

</form>
