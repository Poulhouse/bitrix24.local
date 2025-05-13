<?php
if (!defined('LANGUAGE_ID')) {
    define('LANGUAGE_ID', 'ru');
}

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Routing\RoutingConfigurator;

require_once $_SERVER["DOCUMENT_ROOT"]. '/local/webpractik/bitrixoa/vendor/autoload.php';

/**
 * Получение машрутов установленных в local модулей
 *
 * Сделанно анонимной функцией, чтобы все переменные
 * имели локальный пространство имен
 *
 * Поскольку переменные с этими иминами используется ранее в ядре
 *
 * @return array
 */
$getRoutePaths = static function (): array {
    foreach (ModuleManager::getInstalledModules() as $module) {
        $route = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $module['ID'] . '/routes.php';
        if (file_exists($route)) {
            $routes[] = $route;
        }
    }
    return $routes ?? [];
};

return function (RoutingConfigurator $routingConfigurator) use ($getRoutePaths) {
    foreach ($getRoutePaths() as $route) {
        $callback = include $route;
        if ($callback instanceof Closure) {
            $callback($routingConfigurator);
        }
    }
    $routingConfigurator->get('api-doc', [\BitrixOA\BitrixUiController::class, 'apidocAction']);
};