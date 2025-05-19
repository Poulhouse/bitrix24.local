<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use KPLab\API\V2\Model\ORM\ApiKeysTable;
use KPLab\API\V2\Model\ORM\ApiKeyRoutesTable;
use KPLab\API\V2\Model\ORM\RoutesTable;

Loc::loadMessages(__FILE__);
$APPLICATION->SetTitle("Добавление API-ключа");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $apiKey = $_POST['API_KEY_MODE'] === 'auto' ? hash('sha256', uniqid(mt_rand(), true)) : trim($_POST['CUSTOM_API_KEY']);
    $isManualEntry = ($_POST['API_KEY_MODE'] === 'manual');
    if ($isManualEntry) {
        $MANUAL_ENTRY = "Y";
    } else {
        $MANUAL_ENTRY = "N";
    }

    if (empty($apiKey)) {
        echo "<div class='adm-info-message'>Ошибка: ключ не может быть пустым!</div>";
    } else {
        $result = ApiKeysTable::add([
            'API_KEY' => $apiKey,
            'USER_ID' => $_POST['USER_ID'] ?? 1,
            'SERVICE_NAME' => $_POST['SERVICE_NAME'] ?? 'Manual Entry',
            'STATUS' => 'active',
            'MANUAL_ENTRY' => $MANUAL_ENTRY,
            'KEY_LOCATION' => $_POST['KEY_LOCATION'] ?? 'body',
            'KEY_PARAM_NAME' => $_POST['KEY_PARAM_NAME'] ?? 'apiKey',
        ]);

        if ($result->isSuccess()) {
            $apiKeyId = $result->getId();

            if (!empty($_POST['ROUTE_IDS'])) {
                foreach ($_POST['ROUTE_IDS'] as $routeId) {
                    ApiKeyRoutesTable::add([
                        'API_KEY_ID' => $apiKeyId,
                        'ROUTE_ID' => (int)$routeId
                    ]);
                }
            }
            LocalRedirect("/bitrix/admin/kplab.api_keys_list.php?success=1");
        } else {
            echo "<div class='adm-info-message'>Ошибка при добавлении ключа</div>";
        }
    }
}

$routes = RoutesTable::getList([
    'select' => ['ID', 'ROUTE_PATH']
])->fetchAll();

?>

<form method="POST">
    <?= bitrix_sessid_post(); ?>

    <label>Пользователь (ID):</label>
    <input type="text" name="USER_ID" value="1"><br>

    <label>Название сервиса:</label>
    <input type="text" name="SERVICE_NAME"><br>

    <label>Способ передачи API-ключа:</label>
    <select name="KEY_LOCATION" id="key_location">
        <option value="body">Тело запроса</option>
        <option value="header">Заголовок</option>
        <option value="query">Query-параметр</option>
    </select><br>

    <label>Имя параметра / заголовка / query:</label>
    <input type="text" name="KEY_PARAM_NAME" value="apiKey"><br>

    <label>Выбор API-ключа:</label><br>
    <input type="radio" name="API_KEY_MODE" value="auto" id="auto_key" checked>
    <label for="auto_key">Сгенерировать автоматически</label><br>

    <input type="radio" name="API_KEY_MODE" value="manual" id="manual_key">
    <label for="manual_key">Ввести вручную</label><br>

    <input type="text" name="CUSTOM_API_KEY" id="custom_api_key" style="display: none;" placeholder="Введите API-ключ вручную"><br>

    <label>Маршруты:</label><br>
    <?php foreach ($routes as $route): ?>
        <input type="checkbox" name="ROUTE_IDS[]" value="<?= $route['ID'] ?>"> <?= $route['ROUTE_PATH'] ?><br>
    <?php endforeach; ?>

    <br><input type="submit" value="Создать API-ключ">
</form>

<script>
    document.getElementById('auto_key').addEventListener('change', function() {
        document.getElementById('custom_api_key').style.display = 'none';
    });
    document.getElementById('manual_key').addEventListener('change', function() {
        document.getElementById('custom_api_key').style.display = 'block';
    });
    document.getElementById('key_location').addEventListener('change', function() {
        let paramInput = document.querySelector('input[name="KEY_PARAM_NAME"]');
        if (this.value === 'body') {
            paramInput.value = 'apiKey';
        } else if (this.value === 'header') {
            paramInput.value = 'Authorization';
        } else if (this.value === 'query') {
            paramInput.value = 'api_key';
        }
    });
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
?>
