<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = array(
    "NAME" => "Связать CRM со смартом",  // Название активности
    "DESCRIPTION" => "Привязка элемента смарт-процесса к CRM-сущности через динамически выбранное поле PARENT_ID_*",
    "TYPE" => "activity",
    "CLASS" => "KPLabBindingCRMActivity",  // Класс, который описывает логику активности
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "crm",  // Категория активности
    ),
    "RETURN" => []
);
?>
