<?php
$host = $_SERVER['HTTP_HOST'];
$handlerDir = '/local/reportPlanFact/handlers';

// Встраивание в CRM Аналитику
$arPlacementAnalytics = [
	'PLACEMENT' => 'CRM_ANALYTICS_MENU',
	'TITLE' => 'План Факт',
	'HANDLER' => 'https://' . $host . $handlerDir . '/index.php',
];

// Встраивание как вкладка в карточке компании
$arPlacementCompanyTab = [
	'PLACEMENT' => 'CRM_COMPANY_DETAIL_TAB',
	'TITLE' => 'Аналитика',
	'HANDLER' => 'https://' . $host . $handlerDir . '/index.php',
];

require_once('include/header.php');
?>

<h1>Приложение - встраиватель отчёта v.1.0</h1>
<p>Приложение появляется в CRM - Аналитика и в карточке компании</p>
<div>
	<p class="text-p" style="cursor:pointer;" onclick="BX24.callMethod('placement.bind', {
			PLACEMENT: '<?=$arPlacementAnalytics['PLACEMENT']?>',
			HANDLER: '<?=$arPlacementAnalytics['HANDLER']?>',
			TITLE: '<?=$arPlacementAnalytics['TITLE']?>'
			})">Установить в CRM Аналитику</p>

	<p class="text-p" style="cursor:pointer;" onclick="BX24.callMethod('placement.bind', {
			PLACEMENT: '<?=$arPlacementCompanyTab['PLACEMENT']?>',
			HANDLER: '<?=$arPlacementCompanyTab['HANDLER']?>',
			TITLE: '<?=$arPlacementCompanyTab['TITLE']?>'
			})">Установить вкладку в карточку компании</p>

</div>
<div>
	<!-- Для удаления -->
	<p class="text-p" style="cursor:pointer;" onclick="BX24.callMethod('placement.unbind', {
			PLACEMENT: '<?=$arPlacementAnalytics['PLACEMENT']?>',
			HANDLER: '<?=$arPlacementAnalytics['HANDLER']?>'
			})">Удалить из CRM Аналитики</p>

	<p class="text-p" style="cursor:pointer;" onclick="BX24.callMethod('placement.unbind', {
			PLACEMENT: '<?=$arPlacementCompanyTab['PLACEMENT']?>',
			HANDLER: '<?=$arPlacementCompanyTab['HANDLER']?>'
			})">Удалить вкладку из карточки компании</p>
</div>


<?php
require_once('include/footer.php');
?>
