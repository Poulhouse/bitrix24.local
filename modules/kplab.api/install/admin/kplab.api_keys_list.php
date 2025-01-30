<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
use Bitrix\Main\Loader;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use KPLab\API\V2\ApiKeysTable;

$APPLICATION->SetTitle("API-ключи");
Loader::includeModule('kplab.api');
\Bitrix\Main\UI\Extension::load("ui.buttons");

// Настройка сетки
$gridID = 'kplab_api_keys_grid';
$gridOptions = new GridOptions($gridID);

// Опции фильтра
$filterID = 'kplab_api_keys_filter';
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
if (!empty($filterData['API_KEY'])) {
    $filterConditions['%API_KEY'] = $filterData['API_KEY'];
}
if (!empty($filterData['USER_ID'])) {
    $filterConditions['USER_ID'] = $filterData['USER_ID'];
}
if (!empty($filterData['SERVICE_NAME'])) {
    $filterConditions['%SERVICE_NAME'] = $filterData['SERVICE_NAME'];
}
if (!empty($filterData['STATUS'])) {
    $filterConditions['STATUS'] = $filterData['STATUS'];
}
if (!empty($filterData['KEY_LOCATION'])) {
    $filterConditions['KEY_LOCATION'] = $filterData['KEY_LOCATION'];
}
if (!empty($filterData['KEY_PARAM_NAME'])) {
    $filterConditions['%KEY_PARAM_NAME'] = $filterData['KEY_PARAM_NAME'];
}

$totalCount = ApiKeysTable::getCount($filterConditions);

$res = ApiKeysTable::getList([
    'filter' => $filterConditions,
    'select' => ['*'],
    'order' => $sort['sort'],
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
]);

$apiKeys = [];
while ($key = $res->fetch()) {
    $apiKeys[] = [
        'data' => $key,
        'actions' => [
            [
                'text' => 'Изменить',
                'onclick' => "document.location.href='kplab.api_keys_edit.php?ID=" . $key['ID'] . "'"
            ],
            [
                'text' => 'Удалить',
                'onclick' => "if(confirm('Удалить ключ?')){ document.location.href='?delete=" . $key['ID'] . "&" . bitrix_sessid_get() . "'; }"
            ],
        ],
    ];
}
$nav->setRecordCount($totalCount);

?>
    <div class="adm-toolbar-panel-container">
        <div class="adm-toolbar-panel-align-right">
            <button onclick="document.location.href='kplab.api_keys_add.php'" class="ui-btn ui-btn-primary">Добавить API-ключ</button>
        </div>
    </div>
<?php

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $gridID,
        'COLUMNS' => [
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
            ['id' => 'API_KEY', 'name' => 'API Key', 'sort' => 'API_KEY', 'default' => true],
            ['id' => 'USER_ID', 'name' => 'User ID', 'sort' => 'USER_ID', 'default' => true],
            ['id' => 'SERVICE_NAME', 'name' => 'Service Name', 'sort' => 'SERVICE_NAME', 'default' => true],
            ['id' => 'STATUS', 'name' => 'Status', 'sort' => 'STATUS', 'default' => true],
            ['id' => 'KEY_LOCATION', 'name' => 'Где передавать ключ', 'sort' => 'KEY_LOCATION', 'default' => true],
            ['id' => 'KEY_PARAM_NAME', 'name' => 'Параметр передачи ключа', 'sort' => 'KEY_PARAM_NAME', 'default' => true],
            ['id' => 'CREATED_AT', 'name' => 'Created At', 'sort' => 'CREATED_AT', 'default' => true],
            ['id' => 'LAST_USED_AT', 'name' => 'Last Used', 'sort' => 'LAST_USED_AT', 'default' => true],
        ],
        'ROWS' => $apiKeys,
        'NAV_OBJECT' => $nav,
        'AJAX_MODE' => 'Y',
        'SHOW_ROW_ACTIONS_MENU' => true,
        'SHOW_NAVIGATION_PANEL' => true,
        'SHOW_PAGINATION' => true,
        'SHOW_TOTAL_COUNTER' => true,
        'ALLOW_SORT' => true,
    ]
);

// Удаление API-ключа
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete']) && check_bitrix_sessid()) {
    $keyId = intval($_GET['delete']);
    ApiKeysTable::delete($keyId);
    LocalRedirect($APPLICATION->GetCurPageParam("", ["delete", "sessid"]));
}

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
