<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type;
use Kplab\Exchange_log\ExchangeLogTable;
use Bitrix\Main\UserTable;
use Bitrix\Main\UI\Filter;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Журнал изменений");
Loader::includeModule('iblock');

$gridID = 'kplab_exchange_logs_grid';
$filterID = 'kplab_exchange_logs_filter';

// Опции фильтра
$filterOptions = new FilterOptions($filterID);
$filterData = $filterOptions->getFilter();

// Настройки навигации
$nav = new PageNavigation("page");
$nav->allowAllRecords(true)
    ->setPageSize(20)
    ->initFromUri();

$itemsList = [];
$start = 0;
do {
    // Параметры запроса: если старт не 0 — передаём курсор
    $params = [];
    if ($start > 0) {
        $params['start'] = $start;
    }
    // Делаем вызов
    $response = \CRest::call('crm.type.list', $params);
    // Извлекаем блок типов (может быть пустым)
    $types = $response['result']['types'] ?? [];
    // Накатываем в результирующий массив
    $itemsList = array_merge($itemsList, $types);

    // Если API вернуло курсор next — запомним его, иначе выйдем из цикла
    if (isset($response['next']) && $response['next'] !== null) {
        $start = (int)$response['next'];
    } else {
        $start = null;
    }

// Повторяем, пока есть курсор next
} while ($start !== null);
$ENTITY_TYPE_ID_LIST = [
    1 => "Лид",
    2 => "Сделка",
    3 => "Контакт",
    4 => "Компания"
];
foreach ($itemsList as $smartProcess) {;
    $ENTITY_TYPE_ID_LIST[$smartProcess['entityTypeId']] = $smartProcess['title'];
}

/*echo "<pre>";
$users = UserTable::getMap();

echo "</pre>";*/

// Определяем поля фильтра. Для поля CHANGE_DATE используем ключи _from и _to
$filterFields = [
    [
        "id" => "ID",
        "name" => "ID",
        "type" => "number",
        "default" => true,
    ],
    [
        "id" => "ENTITY_TYPE_ID",
        "name" => "Тип CRM",
        "type" => "list",
        "items" => $ENTITY_TYPE_ID_LIST,
        "default" => true,
    ],
    [
        "id" => "ENTITY_ID",
        "name" => "ID элемента CRM",
        "type" => "number",
        "default" => true,
    ],
    [
        "id" => "FIELD_NAME",
        "name" => "Поле элемента CRM",
        "type" => "string",
        "default" => true,
    ],
    [
        "id" => "OLD_VALUE",
        "name" => "Старое значение",
        "type" => "text",
        "default" => true,
    ],
    [
        "id" => "NEW_VALUE",
        "name" => "Новое значение",
        "type" => "text",
        "default" => true,
    ],
    [
        "id" => "USER_ID",
        "name" => "Кем изменено",
        'type' => 'entity_selector',
	    'params' => [
            'multiple' => 'N',
            'dialogOptions' => [
                'height' => 240,
                'context' => 'filter',
                'entities' => [
                    [
                        'id' => 'user',
                        'options' => [
                            'inviteEmployeeLink' => false
                        ],
                    ],
                    [
                        'id' => 'department',
                    ]
                ]
            ],
        ],
        "default" => true,
    ],
    [
        "id" => "SERVICE_UPDATE_NAME",
        "name" => "Сервис источник обновления",
        "type" => "string",
        "default" => true,
    ],
    [
        "id" => "CHANGE_DATE",
        "name" => "Дата и время изменения",
        "type" => "date",
        "time" => true,
        "default" => true,
    ],
];

// Формирование условий фильтрации
$filterConditions = [];

// Прямые поля:
if (!empty($filterData['ID'])) {
    $filterConditions['ID'] = $filterData['ID'];
}
if (!empty($filterData['ENTITY_TYPE_ID'])) {
    $filterConditions['ENTITY_TYPE_ID'] = $filterData['ENTITY_TYPE_ID'];
}
if (!empty($filterData['ENTITY_ID'])) {
    $filterConditions['ENTITY_ID'] = $filterData['ENTITY_ID'];
}
if (!empty($filterData['FIELD_NAME'])) {
    $filterConditions['%FIELD_NAME'] = $filterData['FIELD_NAME'];
}
if (!empty($filterData['SERVICE_UPDATE_NAME'])) {
    $filterConditions['%SERVICE_UPDATE_NAME'] = $filterData['SERVICE_UPDATE_NAME'];
}
if (!empty($filterData['OLD_VALUE'])) {
    $filterConditions['%OLD_VALUE'] = $filterData['OLD_VALUE'];
}
if (!empty($filterData['NEW_VALUE'])) {
    $filterConditions['%NEW_VALUE'] = $filterData['NEW_VALUE'];
}
if (!empty($filterData['USER_ID'])) {
    $filterConditions['USER_ID'] = $filterData['USER_ID'];
}

// Фильтрация по диапазону даты для CHANGE_DATE
if (!empty($filterData['CHANGE_DATE_from'])) {
    // Добавляем условие "начало" диапазона
    $filterConditions['>=CHANGE_DATE'] = $filterData['CHANGE_DATE_from'];
}
if (!empty($filterData['CHANGE_DATE_to'])) {
    // Добавляем условие "конец" диапазона
    $filterConditions['<=CHANGE_DATE'] = $filterData['CHANGE_DATE_to'];
}


$totalCount = ExchangeLogTable::getCount($filterConditions);

// Запрашиваем данные из таблицы `kplab_api_logs`
$res = ExchangeLogTable::getList([
    'filter' => $filterConditions,
    'select' => ['*'],
    'order' => ['ID' => 'DESC'],
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
]);

$items = [];
while ($row = $res->fetch()) {
    if (isset($ENTITY_TYPE_ID_LIST[$row['ENTITY_TYPE_ID']])) {
        $row['ENTITY_TYPE_ID'] = $ENTITY_TYPE_ID_LIST[$row['ENTITY_TYPE_ID']];
    }

    $filterUsers = [
        'ID' => $row['USER_ID'],
        'ACTIVE' => 'Y',
    ];
    $resUsers = UserTable::getList([
        'filter' => $filterUsers,
        'select' => ['NAME','LAST_NAME']
    ])->fetchAll();
    //print_r($resUsers);
    $row['USER_ID'] = $resUsers[0]['NAME'] . ' ' . $resUsers[0]['LAST_NAME'];

    // Добавляем данные в массив для использования в таблице
    $items[] = [
        'data' => $row,
    ];
}
$nav->setRecordCount($totalCount);

$gridOptions = new GridOptions($gridID);
$sorting = $gridOptions->getSorting(["sort" => ["ID" => "desc"]]);
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $filterID,
        'GRID_ID' => $gridID,
        'FILTER' => $filterFields,
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true
    ]
);
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $gridID,
        'COLUMNS' => [
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => false],
            ['id' => 'CHANGE_DATE', 'name' => 'Дата и время изменения', 'sort' => 'CHANGE_DATE', 'default' => true],
            ['id' => 'FIELD_NAME', 'name' => 'Поле элемента CRM', 'sort' => 'FIELD_NAME', 'default' => true],
            ['id' => 'OLD_VALUE', 'name' => 'Старое значение', 'sort' => 'OLD_VALUE', 'default' => true],
            ['id' => 'NEW_VALUE', 'name' => 'Новое значение', 'sort' => 'NEW_VALUE', 'default' => true],
            ['id' => 'USER_ID', 'name' => 'Кем изменено', 'sort' => 'USER_ID', 'default' => true],
            ['id' => 'SERVICE_UPDATE_NAME', 'name' => 'Сервис источник', 'sort' => 'SERVICE_UPDATE_NAME', 'default' => true],
            ['id' => 'ENTITY_ID', 'name' => 'ID элемента CRM', 'sort' => 'ENTITY_ID', 'default' => true],
            ['id' => 'ENTITY_TYPE_ID', 'name' => 'Тип CRM', 'sort' => 'ENTITY_TYPE_ID', 'default' => true],
        ],
        'ROWS' => $items,
        'NAV_OBJECT' => $nav,
        'SORT' => $sorting['sort'],
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => CAjax::GetComponentID('bitrix:main.ui.grid', '', ''),
        'PAGE_SIZES' => [
            ['NAME' => "5", 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
        ],
        'SHOW_CHECK_ALL_CHECKBOXES' => true,
        'SHOW_ROW_CHECKBOXES' => true,
        'SHOW_ROW_ACTIONS_MENU' => true,
        'SHOW_GRID_SETTINGS_MENU' => true,
        'SHOW_NAVIGATION_PANEL' => true,
        'SHOW_PAGINATION' => true,
        'SHOW_TOTAL_COUNTER' => true,
        'SHOW_PAGESIZE' => true,
        'SHOW_ACTION_PANEL' => true,
        'TOTAL_ROWS_COUNT' => $totalCount,
        'ACTION_PANEL' => [],
        'ENABLE_COLLAPSIBLE_ROWS' => true,
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_COLUMNS_RESIZE' => true,
        'ALLOW_HORIZONTAL_SCROLL' => true,
        'ALLOW_SORT' => true,
        'ALLOW_PIN_HEADER' => true,
        'AJAX_OPTION_JUMP' => 'N',
        'AJAX_OPTION_STYLE' => 'Y',
        'AJAX_OPTION_HISTORY' => 'N'
    ]
);


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
