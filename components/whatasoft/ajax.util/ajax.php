<?
use Whatasoft\Cache\CacheParams;

$need_session_check = true;
$need_cache_check = true;
//$need_not_convert_post = true;
//$need_include_module = "whatasoft.shop";
require_once($_SERVER["DOCUMENT_ROOT"]."/local/components/whatasoft/ajax.util/include_before.php");
global $APPLICATION;

if(!count($response["errors"])){
  $arParams = CacheParams::GetCache($_POST["cache_id"]);
  if(is_array($arParams)){
    $response["status"] = "ok";

    ob_start();
    $result = $APPLICATION->IncludeComponent(
      $arParams["COMPONENT_NAME"],
      $arParams["COMPONENT_TEMPLATE"],
      $arParams,
      null,
      array("HIDE_ICONS" => "Y")
    );
    $buffered = ob_get_contents();
    ob_end_clean();
    
    if(isset($arParams["COMPONENT_RETURNS_DATA"])){
      $response["data"] = $result;
    }else{
      $response["data"] = $buffered;
    }
  }
}

require_once($_SERVER["DOCUMENT_ROOT"]."/local/components/whatasoft/ajax.util/include_after.php");
?>