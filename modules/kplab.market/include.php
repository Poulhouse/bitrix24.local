<?php
use Bitrix\Main\Loader;
\Bitrix\Main\Loader::registerNamespace('\\KPLab\\Market\\', dirname(__FILE__) . '/lib');

class CKPLabMarket
{
    public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        global $APPLICATION;

        // проверим права на просмотр
        if ($APPLICATION->GetGroupRight("main") < "R") {
            return;
        }

        $MODULE_ID = basename(dirname(__FILE__));

        $aMenu = [
            'parent_menu' => 'global_menu_settings', // можно заменить на 'global_menu_services'
            'section'     => $MODULE_ID,
            'sort'        => 50,
            'text'        => 'KPLab: Marketplace Core',
            'title'       => 'Управление установками приложений, токенами и лицензиями',
            'icon'        => 'sys_menu_icon',
            'page_icon'   => 'sys_page_icon',
            'items_id'    => 'menu_kplab_market',
            'items' => [
                [
                    'text' => 'Приложения',
                    'title' => 'Список приложений',
                    'url' => str_replace('.','_', $MODULE_ID).'_apps.php?lang=ru',
                ],
                [
                    'text' => 'Установки приложений',
                    'title' => 'Список установленных порталов и токенов',
                    'url' => str_replace('.','_', $MODULE_ID).'_installs.php?lang=ru',
                ],
            ],
        ];

        $aModuleMenu[] = $aMenu;
    }
}