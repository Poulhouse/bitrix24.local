<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Page\Asset;
use \Whatasoft\Campaign\Entity;

\CJSCore::Init(array("fx", "jquery", "ajax"));
Asset::getInstance()->addCss('/bitrix/css/main/bootstrap.min.css');

$gridId = $arResult['GRID_ID'];
$arTableHeader = $arResult['TABLE_HEADER'];
$arTableRows = $arResult['TABLE_ROWS'];
$nav = $arResult['NAV'];
$filterList = $arResult['FILTER_LIST'];
?>
<div id="errors" class="alert alert-danger" style="display:none"></div>
<?
$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
  'FILTER_ID' => $gridId,
  'GRID_ID' => $gridId,
  'FILTER' => $filterList,
  'ENABLE_LIVE_SEARCH' => true,
  'ENABLE_LABEL' => true
]);

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
  'SHOW_PAGINATION' => false,
  'SHOW_SELECTED_COUNTER' => false,
  'SHOW_TOTAL_COUNTER' => true,
  'SHOW_PAGESIZE' => false,
  'SHOW_ACTION_PANEL' => true,
  'ALLOW_COLUMNS_SORT' => false,
  'ALLOW_COLUMNS_RESIZE' => true,
  'ALLOW_HORIZONTAL_SCROLL' => true,
  'ALLOW_SORT' => true,
  'ALLOW_PIN_HEADER' => true,
  'AJAX_OPTION_HISTORY' => 'N',
  'TOTAL_ROWS_COUNT_HTML' => '<span class="main-grid-panel-content-title">Всего:</span> <span class="main-grid-panel-content-text">' . $nav->getRecordCount() . '</span>',
]);
?>

<script type="text/javascript">
  window.grid_id = <?=CUtil::PhpToJSObject($arResult['GRID_ID'])?>;
  window.END_CAMPAIGN_STAGE = <?=CUtil::PhpToJSObject(Entity::CAMPAIGN_STATE_COMPLETED)?>;
</script>