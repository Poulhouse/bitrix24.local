<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

CUtil::InitJSCore(['jquery', 'fx', 'ajax']);
?>

<form>
  <input type="text" name="FROM" value="<?=$arParams['FROM']->format('d.m.Y')?>" onclick="BX.calendar({node: this, field: this, bTime: false});">
  <input type="text" name="TO" value="<?=($arParams['TO'] ? $arParams['TO']->format('d.m.Y') : '')?>" onclick="BX.calendar({node: this, field: this, bTime: false});">
  <button>Применить</button>
</form>

<div class="buttons">
<?
	$appendix = isset($_GET['FROM'])? '&FROM=' . $_GET['FROM']: '';
$appendix .= isset($_GET['TO'])? '&TO=' . $_GET['TO'] : '';
?>
  <a href="/local/pages/call_center/stat/index.php?file=Y<?=$appendix?>" class="btn btn-primary">Выгрузить в Excel сводный отчет</a>
</div>

<?/*
<table>
  <tr>
    <th>Название</th>
    <th>Активность</th>
    <th>Количество в очереди</th>
    <th>Количество переданных в офисы</th>
    <th>Количество забракованных</th>
    <th>Количество просроченных</th>
  </tr>
  <?foreach($arResult['QUEUE_GROUPS'] as $arGroup){?>
  <tr>
    <td><?=$arGroup['NAME']?></td>
    <td><?=($arGroup['PROPERTIES']['ACTIVE']['VALUE'] == 'Y' ? 'Да' : 'Нет')?></td>
    <td><?=$arGroup['TOTAL']?></td>
    <td><?=$arGroup['APPOINTMENTS']?></td>
    <td><?=$arGroup['DECLINES']?></td>
    <td><?=$arGroup['EXPIRED']?></td>
  </tr>
  <?}?>
</table>

*/?>

<?
$grid_options = new Bitrix\Main\Grid\Options('report_list');
$sort = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$nav_params = $grid_options->GetNavParams();

$nav = new Bitrix\Main\UI\PageNavigation('report_list');
$nav->allowAllRecords(false)->initFromUri();

$filterParams = 'apply_filter=Y&fields[UF_CRM_QUEUE_GROUP][0]=#GROUP_ID#&fields[UF_CRM_PLANNED_CALL_datesel]=RANGE&fields[UF_CRM_PLANNED_CALL_from]='. $arParams['FROM']->format('d.m.Y') .'&fields[UF_CRM_PLANNED_CALL_to]='. ($arParams['TO']  ? $arParams['TO']->format('d.m.Y') : '');
$filterParamsAppointment = $filterParams. '&additional[STAGE_ID][0]='. QUEUE_DEAL_STAGE_APPOINTMENT;
$filterParamsDecline = $filterParams. '&additional[STAGE_ID][0]='. QUEUE_DEAL_STAGE_DECLINE;
$reportLink = '/crm/reports/report/view/30/?set_filter=Y&sort_id=0&sort_type=ASC&F_DATE_TYPE=interval&F_DATE_FROM=' . $arParams['FROM']->format('d.m.Y') . '&F_DATE_TO='.($arParams['TO']  ? $arParams['TO']->format('d.m.Y') : '') . '&F_DATE_DAYS=&filter[0][0][]=&filter[0][1][]=';
$list = [];
foreach($arResult['QUEUE_GROUPS'] as $i=>$arGroup){
  $list[] = ['data' => [
      "ID" => $i,
      "NAME" => '<a href="/local/pages/supervisor/?mode=edit&list_id=' . $arGroup['IBLOCK_ID'] . '&section_id=0&element_id=' . $arGroup['ID'] . '&list_section_id=" target="_blank">' . $arGroup['NAME'] . '</a>',
      "ACTIVE" => ($arGroup['PROPERTIES']['ACTIVE']['VALUE'] == 'Y' ? 'Да' : 'Нет'),
      "ACTIVE_COUNT" => $arGroup['ACTIVE_COUNT'],
	  "OPERATORS" => $arGroup['OPERATORS'],
      "TOTAL" => '<a href="#" class="apply_filter" data-filter="'.str_replace('#GROUP_ID#', $arGroup['ID'], $filterParams).'">' . $arGroup['TOTAL'] . '</a>',
      "APPOINTMENTS" => '<a href="#" class="apply_filter" data-filter="'.str_replace('#GROUP_ID#', $arGroup['ID'], $filterParamsAppointment).'">' .$arGroup['APPOINTMENTS'] . '</a>',
      "DECLINES" =>  '<a href="#" class="apply_filter" data-filter="'.str_replace('#GROUP_ID#', $arGroup['ID'], $filterParamsDecline).'">' .$arGroup['DECLINES'] . '</a>',
      "EXPIRED" => $arGroup['EXPIRED']
    ],
  ];
}
if (isset($_GET['file'])){
ob_end_clean();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="queuereport.csv"');
echo "\xEF\xBB\xBF";
$csvRows = [
'NAME' => 'Название',
'ACTIVE' => 'Активность',
'ACTIVE_COUNT' => 'Количество активных Специалистов КЦ', 
'OPERATORS' => 'Специалисты в очереди', 
'TOTAL' => 'Количество заявок в очереди',
'APPOINTMENTS' => 'Количество заявок, переданных в офисы',
'DECLINES' => 'Количество забракованных заявок', 
'EXPIRED' => 'Количество просроченных заявок',
];
	foreach($csvRows as $n=>$v){
		echo $v . ';';
	}
	echo "\n";
	foreach($list as $line){
			foreach($csvRows as $n=>$v){
				echo  strip_tags($line['data'][$n]) . ';';
			}
		echo  "\n";
	}

die();
}

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => 'report_list',
    'COLUMNS' => [
        ['id' => 'NAME', 'name' => 'Название', 'sort' => 'name', 'default' => true],
        ['id' => 'ACTIVE', 'name' => 'Активность', 'sort' => 'active', 'default' => true],
        ['id' => 'ACTIVE_COUNT', 'name' => 'Количество активных Специалистов КЦ', 'sort' => 'ACTIVE_COUNT', 'default' => true],
		['id' => 'OPERATORS', 'name' => 'Специалисты', 'sort' => 'ACTIVE_COUNT', 'default' => true],
        ['id' => 'TOTAL', 'name' => 'Количество заявок в очереди', 'sort' => 'AMOUNT', 'default' => true],
        ['id' => 'APPOINTMENTS', 'name' => 'Количество заявок, переданных в офисы', 'sort' => 'APPOINTMENTS', 'default' => true],
        ['id' => 'DECLINES', 'name' => 'Количество забракованных заявок', 'sort' => 'DECLINES', 'default' => true],
        ['id' => 'EXPIRED', 'name' => 'Количество просроченных заявок', 'sort' => 'EXPIRED', 'default' => true], 
    ],
    'ROWS' => $list,
    'SHOW_ROW_CHECKBOXES' => false,
    'NAV_OBJECT' => $nav,
    'AJAX_MODE' => 'Y',
    'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
    'PAGE_SIZES' => [],
    'AJAX_OPTION_JUMP'          => 'N',
    'SHOW_CHECK_ALL_CHECKBOXES' => false,
    'SHOW_ROW_ACTIONS_MENU'     => false,
    'SHOW_GRID_SETTINGS_MENU'   => true,
    'SHOW_NAVIGATION_PANEL'     => false,
    'SHOW_PAGINATION'           => false,
    'SHOW_SELECTED_COUNTER'     => false,
    'SHOW_TOTAL_COUNTER'        => false,
    'SHOW_PAGESIZE'             => false,
    'SHOW_ACTION_PANEL'         => false,
    'ACTION_PANEL'              => [
        'GROUPS' => [
            'TYPE' => [
                'ITEMS' => [
                ],
            ]
        ],
    ],
    'ALLOW_COLUMNS_SORT'        => true,
    'ALLOW_COLUMNS_RESIZE'      => true,
    'ALLOW_HORIZONTAL_SCROLL'   => true,
    'ALLOW_SORT'                => true,
    'ALLOW_PIN_HEADER'          => true,
    'AJAX_OPTION_HISTORY'       => 'N'
]);