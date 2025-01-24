<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use \Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\PageNavigation;


CModule::IncludeModule("iblock");
$list_id = 'queue_list';
$grid_options = new GridOptions($list_id);
$sort = $grid_options->GetSorting(['sort' => ['DATE_CREATE' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$nav_params = $grid_options->GetNavParams();
$nav = new PageNavigation($list_id);
$nav->allowAllRecords(true)
  ->setPageSize($nav_params['nPageSize'])
  ->initFromUri();
if ($nav->allRecordsShown()) {
  $nav_params = false;
} else {
  $nav_params['iNumPage'] = $nav->getCurrentPage();
}

//////////////////////
$arResult = array();
$arResult['IBLOCK_ID'] = 37;
$arResult['GRID_ID'] = $list_id;
$arResult['FILTER_ID'] = $list_id;
$obList = new CList($arResult['IBLOCK_ID']);
$listFields = $obList->GetFields();

$filterable = array("ID" => "");
$dateFilter = array();
$customFilter = array();
$listNotFilterField = array("PREVIEW_PICTURE", "DETAIL_PICTURE", "F", "S:DiskFile");
$arResult["FILTER"] = array();
$arResult["FILTER_CUSTOM_ENTITY"] = array();
foreach($listFields as $fieldId => $field){
  if(in_array($field["TYPE"], $listNotFilterField)) continue;
  
  if(is_array($field["PROPERTY_USER_TYPE"]) && array_key_exists("GetPublicFilterHTML", $field["PROPERTY_USER_TYPE"])){
    $field["GRID_ID"] = $arResult["GRID_ID"];
    $field["FILTER_ID"] = $arResult["FILTER_ID"];
  }
  
  // todo Temporary condition
  if($field["TYPE"] == "S:ECrm" && !empty($field["USER_TYPE_SETTINGS"]["VISIBLE"])){
    unset($field["USER_TYPE_SETTINGS"]["VISIBLE"]);
  }
  
  $preparedField = Bitrix\Lists\Field::prepareFieldDataForFilter($field);
  $filterable[$preparedField["id"]] = $preparedField["filterable"];
  if(!empty($preparedField["dateFilter"])){
    $dateFilter[$preparedField["id"]] = true;
  }
  if(!empty($preparedField["customFilter"])){
    $customFilter[$preparedField["id"]] = $preparedField["customFilter"];
  }
  if($preparedField["type"] == "custom_entity"){
    if(!empty($field["PROPERTY_USER_TYPE"]["USER_TYPE"])){
      $fieldType = $field["PROPERTY_USER_TYPE"]["USER_TYPE"];
    }else{
      $fieldType = $field["TYPE"];
    }
    $field["IBLOCK_ID"] = $arResult["IBLOCK_ID"];
    $field["IBLOCK_TYPE_ID"] = $arParams["IBLOCK_TYPE_ID"];
    if(!is_array($arResult["FILTER_CUSTOM_ENTITY"][$fieldType])){
      $arResult["FILTER_CUSTOM_ENTITY"][$fieldType] = array();
    }
    $arResult["FILTER_CUSTOM_ENTITY"][$fieldType][] = $field;
  }
  if($preparedField["type"] == "custom" && isset($field['PROPERTY_USER_TYPE']['GetUIFilterProperty'])){
    call_user_func_array(
      $field['PROPERTY_USER_TYPE']['GetUIFilterProperty'],
      array(
        $field,
        array(
          'VALUE' => $field['FIELD_ID'],
          'FORM_NAME'=>'filter_'.$field['GRID_ID'],
          'GRID_ID' => $field['GRID_ID']
        ),
        &$preparedField
      )
    );
  }
  
  $arResult["FILTER"][] = $preparedField;
}
//////////////////////

$bodyClass = $APPLICATION->GetPageProperty("BodyClass");
$APPLICATION->SetPageProperty("BodyClass", ($bodyClass ? $bodyClass." " : "")."pagetitle-toolbar-field-view");
$APPLICATION->SetTitle("Список очередей");
?>

<?ob_start();?>
<div class="pagetitle-container pagetitle-flexible-space">
  <?$APPLICATION->IncludeComponent(
    "bitrix:main.ui.filter",
    "",
    array(
      "FILTER_ID" => $arResult["FILTER_ID"],
      "GRID_ID" => $arResult["GRID_ID"],
      "FILTER" => $arResult["FILTER"],
      "ENABLE_LABEL" => true,
      "ENABLE_LIVE_SEARCH" => true
    )
  );?>
</div>
<div class="pagetitle-container pagetitle-align-right-container">
  <div class="ui-btn-split ui-btn-primary">
    <a href="/local/pages/supervisor/?mode=edit&amp;list_id=<?=$arResult['IBLOCK_ID']?>&amp;section_id=0&amp;element_id=0&amp;list_section_id=" id="lists-title-action-add" class="ui-btn-main">Добавить</a>
  </div>
</div>
<?$APPLICATION->AddViewContent('inside_pagetitle', ob_get_clean());?>

<style type="text/css">
  .main-grid-head-title{
    word-break: break-word;
    white-space: normal !important;

  }
</style>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function(){ 
    var divs = document.querySelectorAll('.togglebig');
    for (i = 0; i < divs.length; ++i) {
      var height = parseInt(window.getComputedStyle(divs[i]).height);
      if (height > 50){
        divs[i].style.height = "50px";
        divs[i].style.overflow = "hidden";
        divs[i].innerHTML += "<span style='position:absolute;top:10px;right:-10px'>&#x2195;</span>";
        divs[i].addEventListener('click', function(){
          if (this.style.height == "50px"){
            this.style.height = "";
            this.style.overflow = "auto";
          } else {
            this.style.height = "50px";
            this.style.overflow = "hidden";
          }
        });
      }
    }
  })
</script>

<?php
$allOperators = [];
$allCatsRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 36));
$allCats = [];
while($cat = $allCatsRes->GetNext()){
  $allCats[$cat['ID']] = '<a href="/local/pages/supervisor/products.php?mode=edit&list_id=36&section_id=0&element_id=' . $cat["ID"] . '&list_section_id=" target="_blank" onclick="event.stopPropagation();">' . $cat['NAME'] . '</a>';
}

$columns = [];
$columns[] = ['id' => 'NAME', 'name' => 'Название', 'sort' => 'NAME', 'default' => true];
$columns[] = ['id' => 'SORT', 'name' => 'Сортировка', 'sort' => 'SORT', 'default' => true];
$columns[] = ['id' => 'ORDER', 'name' => 'Порядок', 'sort' => 'PROPERTY_ORDER_VALUE', 'default' => true];
$columns[] = ['id' => 'ACTIVE', 'name' => 'Активность', 'sort' => 'ACTIVE', 'default' => true];
$columns[] = ['id' => 'DATE_CREATE', 'name' => 'Создана', 'sort' => 'DATE_CREATE', 'default' => true];
$columns[] = ['id' => 'USER_NAME', 'name' => 'Название для оператора'];
$columns[] = ['id' => 'IS_NIGHT', 'name' => 'Ночная'];
$columns[] = ['id' => 'CATEGORIES', 'name' => 'Категории'];
$columns[] = ['id' => 'OPERATOR_IDS', 'name' => 'Операторы'];
$columns[] = ['id' => 'LIST_COLOR', 'name' => 'Цвет'];

////////////////////////////////
$arFilter = array();
$filterOption = new Bitrix\Main\UI\Filter\Options($arResult["FILTER_ID"]);
$filterData = $filterOption->getFilter($arResult["FILTER"]);
foreach($filterData as $key => $value){
  if (is_array($value)){
    if (empty($value))
      continue;
  }elseif(strlen($value) <= 0){
    continue;
  }

  if(substr($key, -5) == "_from"){
    $new_key = substr($key, 0, -5);
    $op = (!empty($filterData[$new_key."_numsel"]) && $filterData[$new_key."_numsel"] == "more") ? ">" : ">=";
  }elseif(substr($key, -3) == "_to"){
    $new_key = substr($key, 0, -3);
    $op = (!empty($filterData[$new_key."_numsel"]) && $filterData[$new_key."_numsel"] == "less") ? "<" : "<=";
    if(array_key_exists($new_key, $dateFilter)){
      $dateFormat = $DB->dateFormatToPHP(Csite::getDateFormat());
      $dateParse = date_parse_from_format($dateFormat, $value);
      if(!strlen($dateParse["hour"]) && !strlen($dateParse["minute"]) && !strlen($dateParse["second"])){
        $timeFormat = $DB->dateFormatToPHP(CSite::getTimeFormat());
        $value .= " ".date($timeFormat, mktime(23, 59, 59, 0, 0, 0));
      }
    }
  }else{
    $op = "";
    $new_key = $key;
  }

  if($key == "CREATED_BY" || $key == "MODIFIED_BY"){
    if(!intval($value)){
      $userId = array();
      $userQuery = CUser::GetList(
        $by = "ID",
        $order = "ASC",
        array("NAME" => $value),
        array("FIELDS" => array("ID"))
      );
      while($user = $userQuery->fetch())
        $userId[] = $user["ID"];
      if(!empty($userId))
        $value = $userId;
    }
  }

  if(array_key_exists($new_key, $filterable)){
    if($op == "")
      $op = $filterable[$new_key];
    $arFilter[$op.$new_key] = $value;
  }

  if($key == "FIND" && trim($value)){
    $op = "*";
    $arFilter[$op."SEARCHABLE_CONTENT"] = $value;
  }
}

foreach($customFilter as $fieldId => $callback){
  $filtered = false;
  call_user_func_array($callback, array(
    $arResult["FIELDS"][$fieldId],
    array(
      "VALUE" => $fieldId,
      "FILTER_ID" => $arResult["FILTER_ID"],
    ),
    &$arFilter,
    &$filtered,
  ));
}
$arFilter['IBLOCK_ID'] = $arResult['IBLOCK_ID'];
$arFilter['ACTIVE'] = 'Y';
////////////////////////////////

$res = CIBlockElement::GetList($sort['sort'], $arFilter, false, $nav_params);
$nav->setRecordCount($res->selectedRowsCount());
while($row = $res->GetNextElement()){
  $fields = $row->GetFields();
  $props = $row->GetProperties();
  $categories = [];
  foreach($props['CATEGORIES']['VALUE'] as $cat){
    $categories[] = $allCats[$cat];
  }
  $operators = [];
  foreach($props['OPERATOR_IDS']['VALUE'] as $operator){
    if (!isset($allOperators[$operator])){
      $allOperators[$operator] = CUser::GetById($operator)->GetNext();
    } 

    $operators[] = '<a href="/company/personal/user/' .  $allOperators[$operator]['ID']. '/" target="_blank" onclick="event.stopPropagation();">' . $allOperators[$operator]['LAST_NAME'] . '&nbsp;' . $allOperators[$operator]['NAME'] . '</a>';
  }
  
  $edit_link = '/local/pages/supervisor/?mode=edit&list_id='. $arResult['IBLOCK_ID'] .'&section_id=0&element_id=' . $fields['ID'] . '&list_section_id=';
  
  $list[] = [
    'data' => [
      "NAME" => '<a href="'. $edit_link .'">'. $fields['NAME'] .'</a>',
      "ACTIVE" => $props["ACTIVE"]["VALUE"] == 'Y' ? 'да' : 'нет',
      "SORT" => $fields['SORT'],
      "ORDER" => $props["ORDER"]["VALUE"],
      "DATE_CREATE" => $fields['DATE_CREATE'],
      "USER_NAME" => $props["USER_NAME"]["VALUE"],
      "IS_NIGHT" => $props["IS_NIGHT"]["VALUE"] == 'Y' ? 'да' : 'нет',
      "CATEGORIES" => '<div class="togglebig">' . implode('<br />', $categories) . '</div>',
      "OPERATOR_IDS" => '<div class="togglebig">' . implode('<br />', $operators) . '</div>', 
      "LIST_COLOR" => "<div style='width:20px;height:20px;background-color:#{$props['LIST_COLOR']['VALUE']}'></div>"
    ],
    'actions' => [
      [
        'text'    => 'Просмотр',
        'default' => true,
        'onclick' => 'document.location.href="'. $edit_link .'"'
      ],/* [
        'text'    => 'Удалить',
        'default' => true,
        'onclick' => 'if(confirm("Точно?")){document.location.href="?op=delete&id='.$fields['ID'].'"}'
      ]*/
    ]
  ];
}

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
  'GRID_ID' => $list_id,
  'COLUMNS' => $columns,
  'ROWS' => $list,
  'SHOW_ROW_CHECKBOXES' => false,
  'NAV_OBJECT' => $nav,
  'AJAX_MODE' => 'Y',
  'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
  'PAGE_SIZES' =>  [
    ['NAME' => '20', 'VALUE' => '20'],
    ['NAME' => '50', 'VALUE' => '50'],
    ['NAME' => '100', 'VALUE' => '100']
  ],
  'AJAX_OPTION_JUMP'          => 'N',
  'SHOW_CHECK_ALL_CHECKBOXES' => false,
  'SHOW_ROW_ACTIONS_MENU'     => true,
  'SHOW_GRID_SETTINGS_MENU'   => true,
  'SHOW_NAVIGATION_PANEL'     => true,
  'SHOW_PAGINATION'           => true,
  'SHOW_SELECTED_COUNTER'     => true,
  'SHOW_TOTAL_COUNTER'        => true,
  'SHOW_PAGESIZE'             => true,
  'SHOW_ACTION_PANEL'         => true,
  'ALLOW_COLUMNS_SORT'        => true,
  'ALLOW_COLUMNS_RESIZE'      => true,
  'ALLOW_HORIZONTAL_SCROLL'   => true,
  'ALLOW_SORT'                => true,
  'ALLOW_PIN_HEADER'          => true,
  'AJAX_OPTION_HISTORY'       => 'N'
]);
?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>