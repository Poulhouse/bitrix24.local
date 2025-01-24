<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

return [
    'parent_menu' => 'global_menu_services',  // Раздел меню, куда добавить пункт (например, Сервисы)
    'section' => 'kplab_api',
    'sort' => 100,
    'text' => Loc::getMessage('KPLAB_API_MENU_TITLE'), // Название пункта меню
    'title' => Loc::getMessage('KPLAB_API_MENU_TITLE'), // Всплывающая подсказка при наведении
    'url' => 'kplab_api_admin.php?lang=ru', // Ссылка на список ключей
    'icon' => 'iblock_menu_icon',  // Иконка пункта меню
    'page_icon' => 'iblock_page_icon',
    'items_id' => 'menu_kplab_api',
    'items' => [],
];
