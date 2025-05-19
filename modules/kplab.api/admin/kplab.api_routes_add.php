<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Routing\RoutingConfigurator;
use KPLab\API\V2\Model\ORM\RoutesTable;
use Bitrix\Main\Config\Option;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Добавить новый маршрут");

Loader::includeModule('kplab.api');

$MODULE_ID = 'kplab.api';
$request = Application::getInstance()->getContext()->getRequest();

// Сохранение маршрута
if ($request->isPost() && check_bitrix_sessid()) {
    $routePath = $request->getPost('ROUTE_PATH');
    $controllerName = $request->getPost('CONTROLLER_NAME');
    $methodName = $request->getPost('METHOD_NAME');
    $httpMethod = $request->getPost('HTTP_METHOD');
    $active = $request->getPost('ACTIVE') == 'Y' ? 'Y' : 'N';
    $logLevel = $request->getPost('LOG_LEVEL'); // Новый параметр логирования

    if (!empty($routePath) && !empty($controllerName) && !empty($methodName) && !empty($httpMethod)) {
        $result = RoutesTable::add([
            'ROUTE_PATH' => $routePath,
            'CONTROLLER_NAME' => $controllerName,
            'METHOD_NAME' => $methodName,
            'HTTP_METHOD' => $httpMethod,
            'ACTIVE' => $active,
        ]);

        if ($result->isSuccess()) {
            $routeId = $result->getId(); // Получаем ID нового маршрута
            // Сохраняем уровень логирования в опции
            Option::set('kplab.api', "route_{$routeId}_log_level", $logLevel);
            LocalRedirect('kplab.api_routes_list.php');
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

    <label>HTTP-Метод:</label><br />
    <select name="HTTP_METHOD">
        <option value="post" selected>POST</option>
        <option value="get">GET</option>
        <option value="put">PUT</option>
        <option value="delete">DELETE</option>
    </select><br /><br />

    <label>Активный:</label><br />
    <select name="ACTIVE">
        <option value="Y" selected>Да</option>
        <option value="N" >Нет</option>
    </select><br /><br />

    <label>Уровень логирования:</label><br />
    <select name="LOG_LEVEL">
        <option value="full">Полное логирование</option>
        <option value="errors">Только ошибки</option>
    </select><br /><br />

    <input type="submit" value="Добавить маршрут" />
</form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
