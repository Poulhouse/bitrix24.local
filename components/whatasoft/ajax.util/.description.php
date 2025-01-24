<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
  "NAME" => GetMessage("T_COMPONENT_NAME"),
  "DESCRIPTION" => GetMessage("T_COMPONENT_DESC"),
  "SORT" => 20,
  "CACHE_PATH" => "Y",
  "PATH" => array(
    "ID" => "whatasoft",
    "NAME" => GetMessage("T_PARENT_COMPONENT_NAME"),
    "SORT" => 10,
  ),
);
?>