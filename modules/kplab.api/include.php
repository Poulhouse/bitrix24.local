<?php
//\Bitrix\Main\Loader::registerNamespace('KPLab\API\V2\Controller', $_SERVER["DOCUMENT_ROOT"] . '/local/modules/kplab.api.v2/controller');


\Bitrix\Main\Loader::registerNamespace('\\KPLab\\API\\V2\\Helpers\\', dirname(__FILE__) . '/helpers');
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\API\\V2\\DTO', dirname(__FILE__) . '/model/dto');
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\API\\V2\\Service', dirname(__FILE__) . '/lib/Service');

file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/logs/namespace_debug.log", "Регистрация namespace выполняется!\n", FILE_APPEND);

//General
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Time' => 'lib/time.php',
    'KPLab\API\V2\LogsAction' => 'lib/logs.php',
    'KPLab\API\V2\LogsTable' => 'lib/logs_table.php',
    'KPLab\API\V2\RoutesTable' => 'lib/routes_table.php',
    'KPLab\API\V2\ApiKeysTable' => 'lib/api_keys_table.php',
    'KPLab\API\V2\ApiKeyRoutesTable' => 'lib/api_key_routes_table.php',
    'KPLab\API\V2\HttpClientFactory' => 'lib/HttpClientFactory.php'
]);

//Controller\ActionFilter
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Controller\ActionFilter\Authentication' => 'controller/ActionFilter/Authentication.php',
    'KPLab\API\V2\Controller\ActionFilterBots\Authentication' => 'controller/ActionFilterBots/Authentication.php'
]);

//Interfaces\Http
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Interfaces\Http\Auth\AuthSchemeInterface' => 'lib/Interfaces/Http/Auth/AuthSchemeInterface.php',
    'KPLab\API\V2\Interfaces\Http\HttpClientInterface' => 'lib/Interfaces/Http/HttpClientInterface.php',
    'KPLab\API\V2\Interfaces\Http\Auth\BasicScheme' => 'lib/Interfaces/Http/Auth/BasicScheme.php',
    'KPLab\API\V2\Interfaces\Http\Auth\ApiKeyScheme' => 'lib/Interfaces/Http/Auth/ApiKeyScheme.php'
]);

//Infrastructure\Http
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Infrastructure\Http\Auth\AuthHttpClientDecorator' => 'lib/Infrastructure/Http/Auth/AuthHttpClientDecorator.php',
    'KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter' => 'lib/Infrastructure/Http/BitrixHttpClientAdapter.php',
    'KPLab\API\V2\Infrastructure\Http\LoggingHttpClientDecorator' => 'lib/Infrastructure/Http/LoggingHttpClientDecorator.php',
    'KPLab\API\V2\Infrastructure\Http\HttpClientDecorator' => 'lib/Infrastructure/Http/HttpClientDecorator.php'
]);

//Infrastructure\Logger
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Infrastructure\Logger\DbHttpLogger' => 'lib/Infrastructure/Logger/DbHttpLogger.php',
    'KPLab\API\V2\Infrastructure\Logger\NullHttpLogger' => 'lib/Infrastructure/Logger/NullHttpLogger.php'
]);

//Service
\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.api', [
    'KPLab\API\V2\Service\CompanyService' => 'lib/Service/CompanyService.php'
]);

class CKPLabApi
{
    public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        if ($GLOBALS['APPLICATION']->GetGroupRight("main") < "R") {
            return;
        }

        $MODULE_ID = basename(dirname(__FILE__));
        $aMenu = array(
            "parent_menu" => "global_menu_services",
            "icon" => "default_menu_icon",
            "page_icon" => "default_page_icon",
            "section" => $MODULE_ID,
            "sort" => 50,
            "text" => "KPLab API",
            "title" => 'Журнал логов API',
            "items_id" => $MODULE_ID . "_items",
            'more_url' => [
                $MODULE_ID.'_logs_list.php',
                $MODULE_ID.'_logs_detail.php',
                $MODULE_ID.'_routes_list.php',
                $MODULE_ID.'_routes_add.php',
                $MODULE_ID.'_routes_edit.php',
                $MODULE_ID.'_settings.php'
            ], // Добавляем сюда страницы
            "items" => array()
        );

        $aMenu['items'][] = [
            'text' => 'Логи API',
            'url' => $MODULE_ID.'_logs_list.php',
            'module_id' => $MODULE_ID,
            'more_url' => [
                $MODULE_ID.'_logs_list.php',
                $MODULE_ID.'_logs_detail.php'
            ],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aMenu['items'][] = [
            'text' => 'Ключи API',
            'url' => $MODULE_ID.'_keys_list.php?lang=' . LANGUAGE_ID,
            'module_id' => $MODULE_ID,
            'more_url' => [
                $MODULE_ID.'_keys_list.php',
                $MODULE_ID.'_keys_add.php',
                $MODULE_ID.'_keys_edit.php'
            ],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aMenu['items'][] = [
            'text' => 'Маршруты API',
            'url' => $MODULE_ID.'_routes_list.php?lang=' . LANGUAGE_ID,
            'module_id' => $MODULE_ID,
            'more_url' => [
                $MODULE_ID.'_routes_list.php',
                $MODULE_ID.'_routes_add.php',
                $MODULE_ID.'_routes_edit.php'
            ],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aMenu['items'][] = [
            'text' => 'Настройки API',
            'url' => $MODULE_ID.'_settings.php?lang=' . LANGUAGE_ID,
            'module_id' => $MODULE_ID,
            'more_url' => [],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aModuleMenu[] = $aMenu;
    }
}
