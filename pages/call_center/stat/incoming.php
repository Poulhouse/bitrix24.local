<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Статистика обработки");
?>
<?$APPLICATION->IncludeComponent("whatasoft:requests.statistics",
  "",
  Array(
	"ENTITY" => 'incoming',
    "FROM" => $_GET['FROM'],
    "TO" => $_GET['TO'],
  ),
  false
);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>