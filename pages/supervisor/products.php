<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Настройка очередей");



require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/intranet/public/services/lists/index.php");
$APPLICATION->SetTitle(GetMessage("SERVICES_TITLE"));
?>

<?$APPLICATION->IncludeComponent(
	"bitrix:lists", 
	".default", 
	array(
		"IBLOCK_TYPE_ID" => "lists",
		"SEF_MODE" => "N",
		"SEF_FOLDER" => "/local/pages/supervisor/",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000000",
		"COMPONENT_TEMPLATE" => ".default",
		"VARIABLE_ALIASES" => array(
			"list_id" => "list_id",
			"field_id" => "field_id",
			"section_id" => "section_id",
			"element_id" => "element_id",
			"file_id" => "file_id",
			"mode" => "mode",
			"document_state_id" => "document_state_id",
			"task_id" => "task_id",
			"ID" => "ID",
		)
	),
	false
);
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>

