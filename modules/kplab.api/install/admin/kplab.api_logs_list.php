<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use KPLab\API\V2\LogsTable;
use Bitrix\Main\UI\Filter;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Журнал API-запросов");
Loader::includeModule('iblock');

$gridID = 'kplab_api_logs_grid';
$filterID = 'kplab_api_logs_filter';

// Опции фильтра
$filterOptions = new FilterOptions($filterID);
$filterData = $filterOptions->getFilter();

// Настройки навигации
$nav = new PageNavigation("page");
$nav->allowAllRecords(true)
    ->setPageSize(20)
    ->initFromUri();

$filterFields = [
    [
        "id" => "ID",
        "name" => "ID",
        "type" => "number",
        "default" => true,
    ],
    [
        "id" => "REQUEST_TIME",
        "name" => "Дата и время запроса",
        "type" => "datetime",
        "default" => true,
    ],
    [
        "id" => "TITLE",
        "name" => "Название",
        "type" => "string",
        "default" => true,
    ],
    [
        "id" => "PARTNER_NAME",
        "name" => "Партнер",
        "type" => "string",
        "default" => true,
    ],
    [
        "id" => "REQUEST_URL",
        'data_type' => 'string',
        'name' => 'URL запроса',
        "default" => false,
    ],
    [
        "id" => 'CONTROLLER_NAME',
        'data_type' => 'string',
        'name' => 'Контроллер',
        "default" => false,
    ],
    [
        "id" => 'METHOD_NAME',
        'data_type' => 'string',
        'name' => 'Метод API',
        "default" => false,
    ],
    [
        "id" => "REQUEST_METHOD",
        "name" => "Метод запроса",
        "type" => "list",
        "items" => [
            "GET" => "GET",
            "POST" => "POST",
        ],
        "default" => true,
    ],
    [
        "id" => "REQUEST_TYPE",
        "name" => "Тип запроса",
        "type" => "list",
        "items" => [
            "Исходящий" => "Исходящий",
            "Входящий" => "Входящий",
        ],
        "default" => true,
    ],
    [
        "id" => "REQUEST_STATUS",
        "name" => "Статус",
        "type" => "list",
        "items" => [
            "Success" => "Success",
            "Failed" => "Failed",
        ],
        "default" => true,
    ],
    [
        "id" => "EXECUTION_TIME",
        "name" => "Время выполнения",
        "type" => "float",
        "default" => true,
    ],
];

// Добавление условий фильтрации в запрос к базе данных
$filterConditions = [];

if (!empty($filterData['ID'])) {
    $filterConditions['ID'] = $filterData['ID'];
}
if (!empty($filterData['TITLE'])) {
    $filterConditions['%TITLE'] = $filterData['TITLE'];
}
if (!empty($filterData['PARTNER_NAME'])) {
    $filterConditions['%PARTNER_NAME'] = $filterData['PARTNER_NAME'];
}
if (!empty($filterData['REQUEST_METHOD'])) {
    $filterConditions['REQUEST_METHOD'] = $filterData['REQUEST_METHOD'];
}
if (!empty($filterData['REQUEST_STATUS'])) {
    $filterConditions['REQUEST_STATUS'] = $filterData['REQUEST_STATUS'];
}
if (!empty($filterData['REQUEST_TYPE'])) {
    $filterConditions['REQUEST_TYPE'] = $filterData['REQUEST_TYPE'];
}


$totalCount = LogsTable::getCount($filterConditions);

// Запрашиваем данные из таблицы `kplab_api_logs`
$res = LogsTable::getList([
    'filter' => $filterConditions,
    'select' => ['*'],
    'order' => ['ID' => 'DESC'],
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
]);

$items = [];
while ($row = $res->fetch()) {
    // Формируем ссылку на подробности
    $detailUrl = "kplab.api_logs_detail.php?ID=" . $row['ID'];

    // Заменяем значение TITLE на ссылку
    $row['TITLE'] = '<a href="' . htmlspecialcharsbx($detailUrl) . '">' . htmlspecialcharsbx($row['TITLE']) . '</a>';
    $row['OBJECT_URL'] = '<a href="' . htmlspecialcharsbx($row['OBJECT_URL']) . '" target="blank">' . htmlspecialcharsbx($row['OBJECT_URL']) . '</a>';


    // Добавляем данные в массив для использования в таблице
    $items[] = [
        'data' => $row,
        'actions' => [
            [
                'text' => 'Подробнее',
                'onclick' => "document.location.href='" . htmlspecialcharsbx($detailUrl)."'",
            ],
        ],
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
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
            ['id' => 'TITLE', 'name' => 'Название', 'sort' => 'TITLE', 'default' => true],
            ['id' => 'REQUEST_TIME', 'name' => 'Дата и время', 'sort' => 'REQUEST_TIME', 'default' => true],
            ['id' => 'EXECUTION_TIME', 'name' => 'Время выполнения, сек.', 'sort' => 'EXECUTION_TIME', 'default' => true],
            ['id' => 'PARTNER_NAME', 'name' => 'Партнер', 'sort' => 'PARTNER_NAME', 'default' => true],
            ['id' => 'REQUEST_METHOD', 'name' => 'Метод', 'sort' => 'REQUEST_METHOD', 'default' => true],
            ['id' => 'REQUEST_STATUS', 'name' => 'Статус', 'sort' => 'REQUEST_STATUS', 'default' => true],
            ['id' => 'REQUEST_TYPE', 'name' => 'Тип запроса', 'sort' => 'REQUEST_TYPE', 'default' => true],
            ['id' => 'REQUEST_URL', 'name' => 'Строка URL-запроса', 'sort' => 'REQUEST_URL', 'default' => true],
            ['id' => 'OBJECT_URL', 'name' => 'Объект интеграции', 'sort' => 'OBJECT_URL', 'default' => true],
            ['id' => 'CONTROLLER_NAME', 'name' => 'Имя контроллера API', 'sort' => 'CONTROLLER_NAME', 'default' => true],
            ['id' => 'METHOD_NAME', 'name' => 'Метод API', 'sort' => 'METHOD_NAME', 'default' => true],
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
