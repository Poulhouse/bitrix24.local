<?php

namespace KPLab\GitBx;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class AdminMenu
{
    /**
     * Добавляет пункты меню модуля в админку Битрикс.
     */
    public static function buildMenu(&$arGlobalMenu, &$arModuleMenu)
    {
        $moduleId = 'kplab.gitbx';

        if (!\Bitrix\Main\Loader::includeModule($moduleId)) {
            return;
        }

        // Раздел "GitBx"
        $menu = [
            "parent_menu" => "global_menu_services",
            "section"     => "kplab_gitbx",
            "sort"        => 300,
            "text"        => "GitBx DevOps (B24)",
            "title"       => "GitBx DevOps для Битрикс24",
            "icon"        => "default_menu_icon",
            "items_id"    => "menu_kplab_gitbx",
            "items"       => [
                [
                    "text"  => "Snapshot CRM",
                    "title" => "Снять snapshot структуры портала",
                    "url"   => "{$moduleId}_snapshots.php?lang=" . LANGUAGE_ID,
                ],
                [
                    "text"  => "Diff (test → prod)",
                    "title" => "Сравнение конфигурации двух порталов",
                    "url"   => "{$moduleId}_diff.php?lang=" . LANGUAGE_ID,
                ],
                [
                    "text"  => "Миграции",
                    "title" => "Генерация и применение миграций",
                    "url"   => "{$moduleId}_migrations.php?lang=" . LANGUAGE_ID,
                ],
                [
                    "text"  => "Актуализацияа",
                    "title" => "Актуализация тестового портала",
                    "url"   => "{$moduleId}_actualize_test.php?lang=" . LANGUAGE_ID,
                ],
                [
                    "text"  => "Настройки",
                    "title" => "Настройки модуля GitBx",
                    "url"   => "settings.php?mid=" . $moduleId . "&lang=" . LANGUAGE_ID,
                ],
            ],
        ];

        $arModuleMenu[] = $menu;
    }
}