<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

$gridId = $arResult['GRID_ID'];
$filterList = $arResult['FILTER_LIST'];
$arTableHeader = $arResult['TABLE_HEADER'];
$arTableRows = $arResult['TABLE_ROWS'];
$nav = $arResult['NAV'];

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
  'FILTER_ID' => $gridId,
  'GRID_ID' => $gridId,
  'FILTER' => $filterList,
  'ENABLE_LIVE_SEARCH' => true,
  'ENABLE_LABEL' => true
]);
?>
<div class="buttons">
  <a href="?GET_REPORT=Y" class="btn btn-primary">Выгрузить в Excel отчет по клиентам</a>
</div>
<?

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
  'GRID_ID' => $gridId,
  'COLUMNS' => $arTableHeader,
  'ROWS' => $arTableRows,
  'SHOW_ROW_CHECKBOXES' => false,
  'NAV_OBJECT' => $nav,
  'AJAX_MODE' => 'Y',
  'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
  'PAGE_SIZES' => [
      ['NAME' => '5', 'VALUE' => '5'],
      ['NAME' => '20', 'VALUE' => '20'],
      ['NAME' => '50', 'VALUE' => '50'],
      ['NAME' => '100', 'VALUE' => '100']
  ],
  'AJAX_OPTION_JUMP' => 'N',
  'SHOW_CHECK_ALL_CHECKBOXES' => false,
  'SHOW_ROW_ACTIONS_MENU' => true,
  'SHOW_GRID_SETTINGS_MENU' => true,
  'SHOW_NAVIGATION_PANEL' => true,
  'SHOW_PAGINATION' => true,
  'SHOW_SELECTED_COUNTER' => true,
  'SHOW_TOTAL_COUNTER' => true,
  'SHOW_PAGESIZE' => true,
  'SHOW_ACTION_PANEL' => true,
  'ALLOW_COLUMNS_SORT' => true,
  'ALLOW_COLUMNS_RESIZE' => true,
  'ALLOW_HORIZONTAL_SCROLL' => true,
  'ALLOW_SORT' => true,
  'ALLOW_PIN_HEADER' => true,
  'AJAX_OPTION_HISTORY' => 'N',
  'TOTAL_ROWS_COUNT_HTML' => '<span class="main-grid-panel-content-title">Всего:</span> <span class="main-grid-panel-content-text">' . $nav->getRecordCount() . '</span>',
]);
?>