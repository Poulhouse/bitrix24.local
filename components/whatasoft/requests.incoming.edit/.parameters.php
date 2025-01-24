<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

if(!Loader::includeModule("iblock")){
  return;
}

$l_prefix = "WAS_REQUESTS_EDIT_";


$arComponentParameters = array(
  "GROUPS" => array(
  ),
  "PARAMETERS" => array(
    /*"IBLOCK_TYPE" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."IBLOCK_TYPE"),
      "TYPE" => "LIST",
      "VALUES" => $arTypesEx,
      "DEFAULT" => "news",
      "REFRESH" => "Y",
    ),
    "IBLOCK_ID" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."IBLOCK_ID"),
      "TYPE" => "LIST",
      "VALUES" => $arIBlocks,
      "DEFAULT" => '={$_REQUEST["ID"]}',
      "ADDITIONAL_VALUES" => "Y",
      "REFRESH" => "Y",
    ),
    "ITEMS_COUNT" => array(
      "PARENT" => "BASE",
      "NAME" => GetMessage($l_prefix ."ITEMS_COUNT"),
      "TYPE" => "STRING",
      "DEFAULT" => "20",
    ),*/
  ),
);

//CIBlockParameters::Add404Settings($arComponentParameters, $arCurrentValues);