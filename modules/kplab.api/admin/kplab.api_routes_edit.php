<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use KPLab\API\V2\Model\ORM\RoutesTable;
use Bitrix\Main\Config\Option;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Редактирование маршрута");

Loader::includeModule('kplab.api');

$MODULE_ID = 'kplab.api';
$request = Application::getInstance()->getContext()->getRequest();
$routeId = intval($request->getQuery("ID"));

if ($routeId > 0) {
    $route = RoutesTable::getById($routeId)->fetch();
} else {
    echo "Маршрут не найден!";
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    exit;
}

// Обработка сохранения формы
if ($request->isPost() && check_bitrix_sessid()) {
    $routePath = $request->getPost('ROUTE_PATH');
    $controllerName = $request->getPost('CONTROLLER_NAME');
    $methodName = $request->getPost('METHOD_NAME');
    $httpMethod = $request->getPost('HTTP_METHOD');
    $active = $request->getPost('ACTIVE') == 'Y' ? 'Y' : 'N';
    $logLevel = $request->getPost('LOG_LEVEL'); // Новый параметр логирования

    if (!empty($routePath) && !empty($controllerName) && !empty($methodName) && !empty($httpMethod)) {
        $result = RoutesTable::update($routeId, [
            'ROUTE_PATH' => $routePath,
            'CONTROLLER_NAME' => $controllerName,
            'METHOD_NAME' => $methodName,
            'HTTP_METHOD' => $httpMethod,
            'ACTIVE' => $active,
        ]);

        if ($result->isSuccess()) {
            Option::set($MODULE_ID, "route_{$routeId}_log_level", $logLevel);
            LocalRedirect('kplab.api_routes_list.php');
        } else {
            echo "Ошибка при обновлении маршрута.";
        }
    }
}

$logLevel = Option::get($MODULE_ID, "route_{$routeId}_log_level", 'errors'); // По умолчанию 'errors'

?>

<form method="POST">
    <?= bitrix_sessid_post() ?>
    <label>Путь маршрута:</label><br />
    <input type="text" name="ROUTE_PATH" value="<?= htmlspecialcharsbx($route['ROUTE_PATH']) ?>" /><br /><br />

    <label>Контроллер:</label><br />
    <input type="text" name="CONTROLLER_NAME" value="<?= htmlspecialcharsbx($route['CONTROLLER_NAME']) ?>" /><br /><br />

    <label>Метод:</label><br />
    <input type="text" name="METHOD_NAME" value="<?= htmlspecialcharsbx($route['METHOD_NAME']) ?>" /><br /><br />

    <label>HTTP-Метод:</label><br />
    <select name="HTTP_METHOD">
        <option value="post"  <?= $route['HTTP_METHOD'] == 'post' ? 'selected' : '' ?>>POST</option>
        <option value="get" <?= $route['HTTP_METHOD'] == 'get' ? 'selected' : '' ?>>GET</option>
        <option value="put" <?= $route['HTTP_METHOD'] == 'put' ? 'selected' : '' ?>>PUT</option>
        <option value="delete" <?= $route['HTTP_METHOD'] == 'delete' ? 'selected' : '' ?>>DELETE</option>
    </select><br /><br />

    <label>Активный:</label><br />
    <select name="ACTIVE">
        <option value="Y" <?= $route['ACTIVE'] == 'Y' ? 'selected' : '' ?>>Да</option>
        <option value="N" <?= $route['ACTIVE'] == 'N' ? 'selected' : '' ?>>Нет</option>
    </select><br /><br />

    <label>Уровень логирования:</label><br />
    <select name="LOG_LEVEL">
        <option value="full" <?= $logLevel == 'full' ? 'selected' : '' ?>>Полное логирование</option>
        <option value="errors" <?= $logLevel == 'errors' ? 'selected' : '' ?>>Только ошибки</option>
    </select><br /><br />

    <input type="submit" value="Сохранить" />
</form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
