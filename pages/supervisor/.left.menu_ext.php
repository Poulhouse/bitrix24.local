<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$aMenuLinks = [
	/*  [
        'Список очередей',
        '/local/pages/supervisor/?mode=view&list_id=37&section_id=0&list_section_id=',
        [],
        [],
        '',
],*/
	[
        'Список очередей',
        '/local/pages/supervisor/queue.php',
        [],
        [],
        '',
    ],
    [
        'Продукты',
        '/local/pages/supervisor/products.php?mode=view&list_id=36&section_id=0&list_section_id=',
        [],
        [],
        '',
    ],
	[
        'Конфигурации окна',
        '/local/pages/supervisor/products.php?mode=view&list_id=47&section_id=0&list_section_id=',
        [],
        [],
        '',
    ],
	/* [
        'Настройка времени',
        '/local/pages/supervisor/settings.php',
        [],
        [],
        '',
],*/
	 [
        'Детализация звонков',
        '/local/pages/supervisor/detail.php',
        [],
        [],
        '',
    ],[
        'Офисы',
        '/local/pages/supervisor/?mode=view&list_id=41&section_id=0&list_section_id=',
        [],
        [],
        '',
    ],[
        'Скрипты',
        '/local/pages/supervisor/?mode=view&list_id=43&section_id=0&list_section_id=',
        [],
        [],
        '',
    ],[
        'Скрипты [Блоки]',
        '/local/pages/supervisor/?mode=view&list_id=44',
        [],
        [],
        '',
    ],[
        'Скрипты [Стандартные фразы]',
        '/local/pages/supervisor/?mode=view&list_id=45',
        [],
        [],
        '',
    ],[
        'Скрипты [Возражения]',
        '/local/pages/supervisor/?mode=view&list_id=46',
        [],
        [],
        '',
    ],[
        'Кампании',
        '/local/pages/supervisor/campaigns/',
        [],
        [],
        '',
    ]
];
