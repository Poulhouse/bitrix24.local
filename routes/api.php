<?php

\Bitrix\Main\Loader::includeModule('kplab.api.v2');
\Bitrix\Main\Loader::includeModule('kplab.api');
\Bitrix\Main\Loader::includeModule('kplab.onec');
\Bitrix\Main\Loader::includeModule('kplab.jwt');

use Bitrix\Main\Routing\RoutingConfigurator;
use KPLab\API\V2\RoutesTable;
use KPLab\API\V2\Helpers\ControllerGenerator;

use \KPLab\JWT\Controller\User;
use \KPLab\JWT\Controller\Auth;
use \KPLab\JWT\Controller\Referral;
use \KPLab\JWT\Controller\Loans;


return function (RoutingConfigurator $routes) {
    // Получаем только активные маршруты из БД
    $res = RoutesTable::getList([
        'filter' => ['ACTIVE' => 'Y'],  // Загружаем только активные маршруты
        'select' => ['ROUTE_PATH', 'CONTROLLER_NAME', 'METHOD_NAME']
    ]);

    while ($route = $res->fetch()) {
        // Перед регистрацией проверяем, существует ли контроллер и метод
        ControllerGenerator::generateControllerAndMethod($route['CONTROLLER_NAME'], $route['METHOD_NAME']);
        $controllerName = "KPLab\\API\\V2\\Controller\\".$route['CONTROLLER_NAME'];
        $controllerAction = $route['METHOD_NAME'];
        // Регистрируем маршрут
        $routes->any($route['ROUTE_PATH'], [$controllerName, $controllerAction]);
    }
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
};