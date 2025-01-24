<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arComponentDescription = array(
  "NAME" => GetMessage("T_COMPONENT_NAME"),
  "DESCRIPTION" => GetMessage("T_COMPONENT_DESC"),
  "CACHE_PATH" => "Y",
  "SORT" => 10,
  "PATH" => array(
    "ID" => "whatasoft",
    "NAME" => GetMessage("T_DEVELOPER_SECTION_NAME"),
    "SORT" => 10,
    "CHILD" => array(
      "ID" => "requests",
      "NAME" => GetMessage("T_PARENT_COMPONENT_NAME"),
      "SORT" => 10,
    )
  )
);
?>