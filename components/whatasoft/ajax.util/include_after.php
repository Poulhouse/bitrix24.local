<?
$response["exec_time"] = (microtime(true) - $response["exec_time"]);
$response = $APPLICATION->ConvertCharsetArray($response, SITE_CHARSET, "UTF-8");

header("Content-Type: application/json");
echo json_encode($response);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>