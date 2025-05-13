<?php
\Bitrix\Main\Loader::includeModule('kplab.api');
use \Bitrix\Main\Routing\RoutingConfigurator;
use KPLab\API\V2\RoutesTable;
use \KPLab\API\V2\Helpers\ControllerGenerator;


/**
 * @OA\OpenApi(
 *      openapi="3.0.3",
 *      security={{"BitrixAuth": {}},{"QueryKey": {}}}
 *  )
 * @OA\Info(
 *     title="Bitrix API Seller-Capital",
 *     version="2.0"
 * )
 *
 * @OA\Server(
 *     url="https://testcrm.seller-capital.ru/api/v2/",
 *     description="API dev server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="BitrixAuth",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     description="Example: BitrixAuth key"
 * )
 * @OA\SecurityScheme(
 *      securityScheme="QueryKey",
 *      type="apiKey",
 *      name="authId",
 *      in="query"
 *  )
 *
 */
return function (RoutingConfigurator $routes) {
    // Получаем только активные маршруты из БД
    $res = RoutesTable::getList([
        'filter' => ['ACTIVE' => 'Y'],  // Загружаем только активные маршруты
        'select' => ['*']
    ]);
    while ($route = $res->fetch()) {
        // Перед регистрацией проверяем, существует ли контроллер и метод
        ControllerGenerator::generateControllerAndMethod($route['CONTROLLER_NAME'], $route['METHOD_NAME'], $route['ROUTE_PATH'], $route['HTTP_METHOD']);

        $controllerName = "\\KPLab\\API\\V2\\Controller\\" . $route['CONTROLLER_NAME'];
        $controllerAction = $route['METHOD_NAME'];
        $httpMethod = $route['HTTP_METHOD'];
        $routePath = $route['ROUTE_PATH'];

        // Регистрируем маршрут
        if ($httpMethod == "post") {
            $routes->post($routePath, [$controllerName, $controllerAction]);
        }
        if ($httpMethod == "get") {
            $routes->get($routePath, [$controllerName, $controllerAction]);
        }
        if ($httpMethod == "put") {
            $routes->put($routePath, [$controllerName, $controllerAction]);
        }
        if ($httpMethod == "delete") {
            $routes->delete($routePath, [$controllerName, $controllerAction]);
        }

    }
};