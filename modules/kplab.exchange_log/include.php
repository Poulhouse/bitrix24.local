<?php
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\ExchangeLog\\Helpers\\', dirname(__FILE__) . '/helpers');

\Bitrix\Main\Loader::registerAutoLoadClasses('kplab.exchange_log', [
    '\KPLab\ExchangeLog\ExchangeLogTable' => 'lib/exchange_log_table.php',
    '\KPLab\ExchangeLog\Handlers\Company' => 'Handlers/Company.php',
    '\KPLab\ExchangeLog\Handlers\Item' => 'Handlers/Item.php',
    '\KPLab\ExchangeLog\Handlers\Deal' => 'Handlers/Deal.php',
    '\KPLab\ExchangeLog\Handlers\Lead' => 'Handlers/Lead.php',
    '\KPLab\ExchangeLog\Helpers\Exchange' => 'helpers/Exchange.php',
]);

class CKPLabExchangeLog
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
            "text" => "KPLab: Exchange Log",
            "title" => 'Управления отслеживанием изменений',
            "items_id" => $MODULE_ID . "_items",
            'more_url' => [
                $MODULE_ID.'_list.php',
                $MODULE_ID.'_settings.php'
            ],
            "items" => array()
        );

        $aMenu['items'][] = [
            'text' => 'Настройки отслеживания',
            'url' => $MODULE_ID.'_settings.php',
            'module_id' => $MODULE_ID,
            'more_url' => [],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aMenu['items'][] = [
            'text' => 'Журнал изменений',
            'url' => $MODULE_ID.'_list.php?lang=' . LANGUAGE_ID,
            'module_id' => $MODULE_ID,
            'more_url' => [],
            "items_id" => $MODULE_ID . "_items",
        ];

        $aModuleMenu[] = $aMenu;
    }
}
