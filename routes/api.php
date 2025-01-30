<?php
if (!defined('LANGUAGE_ID')) {
    define('LANGUAGE_ID', 'ru');
}
file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/logs/api_debug.log", "api.php загружен!\n", FILE_APPEND);
\Bitrix\Main\Loader::includeModule('kplab.api');
\Bitrix\Main\Loader::includeModule('kplab.onec');

use \KPLab\JWT\Controller\User;
use \KPLab\JWT\Controller\Auth;
use \KPLab\JWT\Controller\Referral;
use \KPLab\JWT\Controller\Loans;

use \Bitrix\Main\Routing\RoutingConfigurator;
use KPLab\API\V2\RoutesTable;
use KPLab\API\V2\Helpers\ControllerGenerator;
use \Bitrix\Main\Routing\Controllers\PublicPageController;

return function (RoutingConfigurator $routes) {
    // Получаем только активные маршруты из БД
    $res = RoutesTable::getList([
        'filter' => ['ACTIVE' => 'Y'],  // Загружаем только активные маршруты
        'select' => ['*']
    ]);
    while ($route = $res->fetch()) {
        // Перед регистрацией проверяем, существует ли контроллер и метод
        ControllerGenerator::generateControllerAndMethod($route['CONTROLLER_NAME'], $route['METHOD_NAME']);

        $controllerName = "\\KPLab\\API\\V2\\Controller\\".$route['CONTROLLER_NAME'];
        $controllerAction = $route['METHOD_NAME'];
        $httpMethod = $route['HTTP_METHOD'];
        $routePath = $route['ROUTE_PATH'];

        $routeData = [
            'controllerName' => $controllerName,
            'controllerAction' => $controllerAction,
            'httpMethod' => $httpMethod,
            'routePath' => $routePath,
        ];

        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/logs/controller_debug.log", print_r($routeData, true), FILE_APPEND);

        // Регистрируем маршрут
        if($httpMethod == "post") {
            $routes->post($routePath, [$controllerName, $controllerAction]);
        }
        if($httpMethod == "get") {
            $routes->get($routePath, [$controllerName, $controllerAction]);
        }
        if($httpMethod == "put") {
            $routes->put($routePath, [$controllerName, $controllerAction]);
        }
        if($httpMethod == "delete") {
            $routes->delete($routePath, [$controllerName, $controllerAction]);
        }

        //$routes->any($route['ROUTE_PATH'], [$controllerName, $controllerAction]);
    }

    if (\Bitrix\Main\Loader::includeModule('kplab.jwt')) {
        $routes->get('/api/auth/jwt/', [Auth::class, 'jwtAction']);

        $routes->get('/api/auth/getId/', [User::class, 'getIdAction']);

        $routes->get('/api/auth/psw/', [User::class, 'codePswAction']);

        $routes->get('/api/profile/get/', [User::class, 'getProfileAction']);

        $routes->get('/api/referral/getInfo/', [Referral::class, 'referralGetInfoAction']);

        $routes->get('/api/referral/getSellers/', [Referral::class, 'getSellersAction']);

        $routes->get('/api/loans/get/', [Loans::class, 'getLoansAction']);

        $routes->get('/api/loans/getByInterval/', [Loans::class, 'getLoansByIntervalAction']);

        $routes->get('/api/loans/getLimits/', [Loans::class, 'getLoansLimitsAction']);

        $routes->post('/api/loans/newTransh/', [Loans::class, 'setOrderNewTranshAction']);
    }
};