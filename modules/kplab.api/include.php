<?php
// Для основного кода модуля (если есть)
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\API\\V2\\', dirname(__FILE__) . '/lib');

// Отдельно регистрируем неймспейс для контроллеров
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\API\\V2\\Controller', dirname(__FILE__) . '/controller');

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
