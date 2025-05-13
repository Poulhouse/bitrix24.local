<?php
$arActivityDescription = [
    "NAME" => "KPLab Get Query",
    "DESCRIPTION" => "Формирование GET-запроса с возможностью логирования",
    "TYPE" => ["activity"],
    "CLASS" => "KPLabGetQuery",
    'ICON' => "/local/activities/kplabgetquery/icon.png",
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
