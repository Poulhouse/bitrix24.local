<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = array(
	"NAME" => "Получить расчет рабочих минут 2",
	"DESCRIPTION" => "Действие, которое посчитает рабочее время в минутах относительно двух значений с типом Дата/время",
	"TYPE" => "activity",
	"CLASS" => "KPLabWorkTimeActivity",
	"JSCLASS" => "BizProcActivity",
	"CATEGORY" => array(
		"ID" => "crm",
	),
	'ADDITIONAL_RESULT' => array('MyReturn')
);

$this->MyReturn = array(
	'Minutes' => array(
		'Name' => 'Рабочие минуты',
		'Type' => \Bitrix\Bizproc\FieldType::TEXT,
	),
);
?>