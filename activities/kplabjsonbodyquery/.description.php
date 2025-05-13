<?php
$arActivityDescription = [
    "NAME" => "KPLab JSON Body Query",
    "DESCRIPTION" => "Формирование JSON-запроса с возможностью тестирования и логирования",
    "TYPE" => ["activity"],
    'ICON' => "/local/activities/kplabjsonbodyquery/icon.png",
    "CLASS" => "KPLabJSONBodyQuery",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => ["ID" => "other"],
    "RETURN" => [
        "ResponseStatus" => [
            "NAME" => "Статус ответа на запрос",
            "TYPE" => "text",
        ],
        "ResponseData" => [
            "NAME" => "Результат запроса",
            "TYPE" => "text",
        ],
    ],
    'ADDITIONAL_RESULT' => ['ResponseStatus','ResponseData'],
];
?>
