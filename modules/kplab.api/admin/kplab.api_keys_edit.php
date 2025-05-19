<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use KPLab\API\V2\Model\ORM\ApiKeysTable;
use KPLab\API\V2\Model\ORM\ApiKeyRoutesTable;
use KPLab\API\V2\Model\ORM\RoutesTable;

Loc::loadMessages(__FILE__);
$APPLICATION->SetTitle("Редактирование API-ключа");

$request = Application::getInstance()->getContext()->getRequest();
$apiKeyId = intval($request->getQuery("ID"));

$successMessage = '';
$errorMessage = '';

// Получение данных API-ключа
$apiKey = ApiKeysTable::getById($apiKeyId)->fetch();
if (!$apiKey) {
    echo "<div class='error-message'>API-ключ не найден</div>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    exit;
}

// Проверяем, был ли ключ создан вручную
$isManualEntry = ($apiKey['MANUAL_ENTRY'] === 'Y');

// Получение доступных маршрутов
$routes = RoutesTable::getList(['select' => ['ID', 'ROUTE_PATH']])->fetchAll();

// Получение маршрутов, к которым уже привязан ключ
$assignedRoutes = ApiKeyRoutesTable::getList([
    'filter' => ['API_KEY_ID' => $apiKeyId],
    'select' => ['ROUTE_ID']
])->fetchAll();

$assignedRouteIds = array_column($assignedRoutes, 'ROUTE_ID');

// Обработка сохранения изменений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $newStatus = $_POST['STATUS'] ?? 'active';
    $newUserId = $_POST['USER_ID'] ?? 1;
    $newServiceName = $_POST['SERVICE_NAME'] ?? 'Manual Entry';
    $apiKeyMode = $_POST['API_KEY_MODE'] ?? 'auto';
    $manualApiKey = trim($_POST['API_KEY'] ?? '');
    $keyLocation = $_POST['KEY_LOCATION'] ?? 'body';
    $keyParamName = trim($_POST['KEY_PARAM_NAME'] ?? 'apiKey');

    if ($apiKeyMode === 'manual' && empty($manualApiKey)) {
        $errorMessage = "Вы выбрали ручной ввод ключа, но не указали его.";
    } else {
        if ($apiKeyMode === 'manual') {
            $apiKeyValue = $manualApiKey;
            $ManualEntry = 'Y';
            $isManualEntry = true;
        } else {
            $apiKeyValue = $apiKey['API_KEY']; // Не менять ключ при авто-режиме
            $ManualEntry = 'N';
            $isManualEntry = false;
        }

        $updateResult = ApiKeysTable::update($apiKeyId, [
            'API_KEY' => $apiKeyValue,
            'USER_ID' => $newUserId,
            'SERVICE_NAME' => $newServiceName,
            'STATUS' => $newStatus,
            'MANUAL_ENTRY' => $ManualEntry,
            'KEY_LOCATION' => $keyLocation,
            'KEY_PARAM_NAME' => $keyParamName
        ]);

        if ($updateResult->isSuccess()) {
            // Удаляем старые привязки API-ключа к маршрутам
            $routesToDelete = ApiKeyRoutesTable::getList([
                'filter' => ['API_KEY_ID' => $apiKeyId],
                'select' => ['ID']
            ])->fetchAll();

            foreach ($routesToDelete as $route) {
                ApiKeyRoutesTable::delete($route['ID']);
            }
            // Обновляем маршруты
            if (!empty($_POST['ROUTE_IDS'])) {
                foreach ($_POST['ROUTE_IDS'] as $routeId) {
                    ApiKeyRoutesTable::add([
                        'API_KEY_ID' => $apiKeyId,
                        'ROUTE_ID' => (int)$routeId
                    ]);
                }
            }

            $successMessage = "API-ключ успешно обновлен!";
        } else {
            $errorMessage = "Ошибка при обновлении ключа.";
        }
    }
}
?>

<style>
    .api-key-form-container {
        max-width: 600px;
        margin: auto;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .api-key-form-container h2 {
        text-align: center;
        margin-bottom: 20px;
    }

    /* Выравнивание радио-кнопок */
    .form-group input[type="radio"] {
        margin-right: 5px;
        vertical-align: middle;
    }

    /* Выравнивание чекбоксов */
    .routes-list label {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .form-group input[type="text"] {

        display: inline-block;
        width: 100%;
    }

    /* Убираем выравнивание по центру */
    .form-group input[type="checkbox"],
    .form-group input[type="radio"] {
        display: inline-block;
        margin-right: 8px;
    }

    /* Выравнивание формы */
    .form-group {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .success-message {
        background: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
    }

    .submit-button {
        width: 100%;
        padding: 10px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s;
    }

    .submit-button:hover {
        background: #0056b3;
    }

    .readonly {
        background: #e9ecef;
        pointer-events: none;
    }
</style>

<div class="api-key-form-container">
    <h2>Редактирование API-ключа</h2>

    <?php if ($successMessage): ?>
        <div class="success-message"><?= $successMessage; ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="error-message"><?= $errorMessage; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= bitrix_sessid_post(); ?>


        <div class="form-group">
            <label>Название сервиса:</label>
            <input type="text" name="SERVICE_NAME" value="<?= $apiKey['SERVICE_NAME'] ?>">
        </div>

        <div class="form-group">
            <label>Выбор API-ключа:</label><br>
            <input type="radio" name="API_KEY_MODE" value="auto" id="auto_key" <?= !$isManualEntry ? 'checked' : '' ?> onclick="toggleKeyInput(false)">
            <label for="auto_key">Сгенерировать автоматически</label><br>

            <input type="radio" name="API_KEY_MODE" value="manual" id="manual_key" <?= $isManualEntry ? 'checked' : '' ?> onclick="toggleKeyInput(true)">
            <label for="manual_key">Ввести вручную</label><br>
        </div>

        <div class="form-group">
            <label>API-ключ</label>
            <input type="text" name="API_KEY" id="api_key_input" value="<?= $apiKey['API_KEY'] ?>" <?= !$isManualEntry ? 'class="readonly"' : '' ?>>
        </div>

        <div class="form-group">
            <label>Способ передачи API-ключа:</label>
            <select name="KEY_LOCATION" id="key_location">
                <option value="body" <?= $apiKey['KEY_LOCATION'] == 'body' ? 'selected' : '' ?>>Тело запроса</option>
                <option value="header" <?= $apiKey['KEY_LOCATION'] == 'header' ? 'selected' : '' ?>>Заголовок</option>
                <option value="query" <?= $apiKey['KEY_LOCATION'] == 'query' ? 'selected' : '' ?>>Query-параметр</option>
            </select>
        </div>

        <div class="form-group">
            <label>Имя параметра / заголовка / query:</label>
            <input type="text" name="KEY_PARAM_NAME" value="<?= htmlspecialchars($apiKey['KEY_PARAM_NAME']) ?>">
        </div>

        <div class="form-group">
            <label>Привязанные маршруты</label>
            <div class="routes-list">
                <?php foreach ($routes as $route): ?>
                    <label>
                        <input type="checkbox" name="ROUTE_IDS[]" value="<?= $route['ID']; ?>"
                            <?= in_array($route['ID'], $assignedRouteIds) ? 'checked' : ''; ?>>
                        <?= htmlspecialchars($route['ROUTE_PATH']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="submit-button">Сохранить изменения</button>
    </form>
</div>
<script>
    function toggleKeyInput(isManual) {
        const keyInput = document.getElementById('api_key_input');
        if (isManual) {
            keyInput.removeAttribute('readonly');
            keyInput.classList.remove('readonly');
        } else {
            keyInput.setAttribute('readonly', 'readonly');
            keyInput.classList.add('readonly');
        }
    }

    // Проверяем при загрузке страницы
    window.onload = function () {
        toggleKeyInput(document.getElementById('manual_key').checked);
    };
</script>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
