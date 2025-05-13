<?php

namespace Kplab\Pep\Admin;

class Menu
{
    public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        if ($GLOBALS['APPLICATION']->GetGroupRight("kplab.pep") < "R") {
            return;
        }

        $aMenu = [
            "parent_menu" => "global_menu_services",
            "section"     => "kplab_pep",
            "sort"        => 50,
            "text"        => "ПЭП (подпись)",
            "title"       => "Простая электронная подпись",
            "icon"        => "default_menu_icon",
            "page_icon"   => "default_page_icon",
            "items_id"    => "kplab_pep_menu",
            "items"       => []
        ];

        $aMenu['items'][] = [
            "text" => "Настройки",
            "url" => "kplab_pep_options.php?lang=" . LANGUAGE_ID,
            "module_id" => "kplab.pep",
            "more_url" => ["kplab_pep_options.php"],
        ];

        $aMenu['items'][] = [
            "text" => "Логи событий",
            "url" => "kplab_pep_logs.php?lang=" . LANGUAGE_ID,
            "module_id" => "kplab.pep",
            "more_url" => ["kplab_pep_logs.php"],
        ];

        $aModuleMenu[] = $aMenu;
    }
}
