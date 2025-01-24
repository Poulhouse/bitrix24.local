<?php
$arActivityDescription = array(
    "NAME" => "Поиск компании по ИНН",  // Название активности
    "DESCRIPTION" => "Активность для поиска и добавления реквизитов компании по ИНН",
    "TYPE" => "activity",
    "CLASS" => "KPLabAddRqByINNActivity",  // Класс, который описывает логику активности
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",  // Категория активности
    ),
    "RETURN" => array(
        "RequisiteId" => array(
            "NAME" => "ID реквизита",
            "TYPE" => "int",
        ),
    ),
);
?>
