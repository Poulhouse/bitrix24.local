<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

\Bitrix\Main\Loader::registerAutoLoadClasses(
    'kplab.jira',
    array(
        '\KPLab\Jira\EventHandlers' => 'lib/EventHandlers.php',
        'KPLab\Jira\EventHandlers' => 'lib/EventHandlers.php',
    )
);