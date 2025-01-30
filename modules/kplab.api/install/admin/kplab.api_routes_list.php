<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use KPLab\API\V2\RoutesTable;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->SetTitle("Маршруты");
Loader::includeModule('kplab.api');
\Bitrix\Main\UI\Extension::load("ui.buttons");

// Настройка сетки
$gridID = 'kplab_api_routes_grid';
$gridOptions = new GridOptions($gridID);

// Опции фильтра
$filterID = 'kplab_api_routes_filter';
$filterOptions = new FilterOptions($filterID);
$filterData = $filterOptions->getFilter();

$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'desc']]);
$nav = new PageNavigation("page");
$nav->allowAllRecords(true)
    ->setPageSize(20)
    ->initFromUri();



$filterConditions = [];

if (!empty($filterData['ID'])) {
    $filterConditions['ID'] = $filterData['ID'];
}
if (!empty($filterData['ROUTE_PATH'])) {
    $filterConditions['%ROUTE_PATH'] = $filterData['ROUTE_PATH']; // Поиск по подстроке
}
if (!empty($filterData['CONTROLLER_NAME'])) {
    $filterConditions['%CONTROLLER_NAME'] = $filterData['CONTROLLER_NAME']; // Поиск по подстроке
}
if (!empty($filterData['METHOD_NAME'])) {
    $filterConditions['%METHOD_NAME'] = $filterData['METHOD_NAME']; // Поиск по подстроке
}
if (!empty($filterData['HTTP_METHOD'])) {
    $filterConditions['%HTTP_METHOD'] = $filterData['HTTP_METHOD']; // Поиск по подстроке
}

$totalCount = RoutesTable::getCount($filterConditions);

// Запрашиваем данные из таблицы маршрутов
$res = RoutesTable::getList([
    'filter' => $filterConditions,  // Добавлены % для поиска по подстроке
    'select' => ['*'],
    'order' => $sort['sort'],
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
]);

$routes = [];
while ($route = $res->fetch()) {
    $routes[] = [
        'data' => $route,
        'actions' => [
            [
                'text' => 'Изменить',
                'onclick' => "document.location.href='kplab.api_routes_edit.php?ID=" . $route['ID'] . "'"
            ],
            [
                'text' => 'Удалить',
                'onclick' => "if(confirm('Удалить маршрут?')){ document.location.href='?delete=" . $route['ID'] . "&" . bitrix_sessid_get() . "'; }"
            ],
        ],
    ];
}
$nav->setRecordCount($totalCount);

?>
    <div class="adm-toolbar-panel-container">
        <div class="adm-toolbar-panel-flexible-space">
            <?php
            $APPLICATION->IncludeComponent(
                'bitrix:main.ui.filter',
                '',
                [
                    'FILTER_ID' => $filterID,
                    'GRID_ID' => $gridID,
                    'FILTER' => [
                        ['id' => 'ID', 'name' => 'ID', 'type' => 'number'],
                        ['id' => 'ROUTE_PATH', 'name' => 'Путь маршрута', 'type' => 'string'],
                        ['id' => 'CONTROLLER_NAME', 'name' => 'Контроллер', 'type' => 'string'],
                        ['id' => 'METHOD_NAME', 'name' => 'Метод', 'type' => 'string'],
                        ['id' => 'HTTP_METHOD', 'name' => 'HTTP-метод', 'type' => 'string'],
                        ['id' => 'ACTIVE', 'name' => 'Активный', 'type' => 'boolean'],
                    ],
                    'ENABLE_LIVE_SEARCH' => true,
                    'ENABLE_LABEL' => true,
                ]
            );
            ?>
        </div>
        <div class="adm-toolbar-panel-align-right">
            <button onclick="document.location.href='kplab.api_routes_add.php'" class="ui-btn ui-btn-primary">Добавить маршрут</button>
        </div>
    </div>
<?php

// Вывод компонента фильтра
/*$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $filterID,
        'GRID_ID' => $gridID,
        'FILTER' => [
            ['id' => 'ID', 'name' => 'ID', 'type' => 'number'],
            ['id' => 'ROUTE_PATH', 'name' => 'Путь маршрута', 'type' => 'string'],
            ['id' => 'CONTROLLER_NAME', 'name' => 'Контроллер', 'type' => 'string'],
            ['id' => 'METHOD_NAME', 'name' => 'Метод', 'type' => 'string'],
        ],
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true,
    ]
);*/

// Вывод компонента таблицы
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $gridID,
        'COLUMNS' => [
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
            ['id' => 'ROUTE_PATH', 'name' => 'Путь маршрута', 'sort' => 'ROUTE_PATH', 'default' => true],
            ['id' => 'CONTROLLER_NAME', 'name' => 'Контроллер', 'sort' => 'CONTROLLER_NAME', 'default' => true],
            ['id' => 'METHOD_NAME', 'name' => 'Метод', 'sort' => 'METHOD_NAME', 'default' => true],
            ['id' => 'HTTP_METHOD', 'name' => 'HTTP-метод', 'sort' => 'HTTP_METHOD', 'default' => true],
            ['id' => 'ACTIVE', 'name' => 'Активный', 'sort' => 'ACTIVE', 'default' => true],
        ],
        'ROWS' => $routes,
        'NAV_OBJECT' => $nav,
        'AJAX_MODE' => 'Y',
        'PAGE_SIZES' => [
            ['NAME' => '5', 'VALUE' => '5'],
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
        'ENABLE_COLLAPSIBLE_ROWS' => true,
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_COLUMNS_RESIZE' => true,
        'ALLOW_HORIZONTAL_SCROLL' => true,
        'ALLOW_SORT' => true,
        'ALLOW_PIN_HEADER' => true,
    ]
);

// Удаление маршрута
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete']) && check_bitrix_sessid()) {
    $routeId = intval($_GET['delete']);
    RoutesTable::delete($routeId);
    LocalRedirect($APPLICATION->GetCurPageParam("", ["delete", "sessid"]));
}

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
