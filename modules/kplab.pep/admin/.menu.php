<?php
use Bitrix\Main\Localization\Loc;

$moduleId = "kplab.pep";

$aMenu = [
    "parent_menu" => "global_menu_services", // или global_menu_services
    "section" => "kplab_pep",
    "sort" => 1000,
    "text" => "Настройки ПЭП",
    "title" => "Настройки модуля электронной подписи",
    "icon" => "iblock_menu_icon",
    "page_icon" => "iblock_page_icon",
    "items_id" => "menu_kplab_pep",
    "url" => "kplab_pep_options.php?lang=" . LANGUAGE_ID,
];

return [$aMenu];
