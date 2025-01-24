<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Routing\RoutingConfigurator;
use KPLab\API\V2\RoutesTable;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Настройка маршрутизации");

Loader::includeModule('kplab.api.v2');

// Сохранение маршрута
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid()) {
    $routePath = $_POST['ROUTE_PATH'];
    $controllerName = $_POST['CONTROLLER_NAME'];
    $methodName = $_POST['METHOD_NAME'];

    if (!empty($routePath) && !empty($controllerName) && !empty($methodName)) {
        $result = RoutesTable::add([
            'ROUTE_PATH' => $routePath,
            'CONTROLLER_NAME' => $controllerName,
            'METHOD_NAME' => $methodName
        ]);

        if ($result->isSuccess()) {
            echo "Маршрут успешно добавлен.";
        } else {
            echo "Ошибка при добавлении маршрута.";
        }
    }
}

// Форма для добавления маршрута
?>
<form method="POST">
    <?= bitrix_sessid_post() ?>
    <label>Путь маршрута:</label><br />
    <input type="text" name="ROUTE_PATH" value="" /><br /><br />

    <label>Контроллер:</label><br />
    <input type="text" name="CONTROLLER_NAME" value="" /><br /><br />

    <label>Метод:</label><br />
    <input type="text" name="METHOD_NAME" value="" /><br /><br />

    <input type="submit" value="Добавить маршрут" />
</form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
