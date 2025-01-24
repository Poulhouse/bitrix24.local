<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = array(
    "NAME" => "Поиск CRM элементов",
    "DESCRIPTION" => "Находит ID элементов CRM по заданному фильтру",
    "TYPE" => "activity",
    "CLASS" => "CrmFilterActivity",
    "JSCLASS" => "BizProcActivity",
    'CATEGORY' => [
        'ID' => 'crm',
    ],
    'FILTER' => [
        'INCLUDE' => [
            ['crm'],
            ['lists'],
        ],
    ],
    "RETURN" => array(
        "ElementsId" => array(
            "NAME" => "ID Элементов",
            "TYPE" => "string",
        ),
    )
);
?>