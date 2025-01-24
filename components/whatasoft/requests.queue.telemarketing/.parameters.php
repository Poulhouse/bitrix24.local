<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

if(!Loader::includeModule("iblock")){
  return;
}

$l_prefix = "WAS_REQUESTS_QUEUE_";


$arComponentParameters = array(
  "GROUPS" => array(
  ),
  "PARAMETERS" => array(
    "NIGHT" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."NIGHT"),
      "TYPE" => "STRING",
      "DEFAULT" => "N",
    ),
    "TYPE" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."TYPE"),
      "TYPE" => "STRING",
      "DEFAULT" => "",
    ),
  ),
);