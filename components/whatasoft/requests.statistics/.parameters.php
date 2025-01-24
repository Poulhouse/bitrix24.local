<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

if(!Loader::includeModule("iblock")){
  return;
}

$l_prefix = "WAS_REQUESTS_STATISTICS_";


$arComponentParameters = array(
  "GROUPS" => array(
  ),
  "PARAMETERS" => array(
    "FROM" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."FROM"),
      "TYPE" => "STRING",
      "DEFAULT" => "",
    ),
    "TO" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."TO"),
      "TYPE" => "STRING",
      "DEFAULT" => "",
    ),
  ),
);