<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = array(
    "NAME" => "Postback API Выбери.ру v2",
    "DESCRIPTION" => "Подставьте параметр ClickID, полученный от vbr.ru",
    "TYPE" => "activity",
    "CLASS" => "KPLabVBRPostBack",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",
    ),
    'RETURN' => [
        'HeaderResponse' => [
            'NAME' => "Заголовки ответа от запроса в VBR",
            'TYPE' => 'string',
        ]
    ]
);
?>