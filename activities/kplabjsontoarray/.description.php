<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
	'NAME' => 'Преобразование JSON в массив строк',
	'DESCRIPTION' => 'Преобразование JSON в массив строк',
	'TYPE' => 'activity',
    'ICON' => "/local/activities/kplabjsontoarray/icon.png",
	'CLASS' => 'KPLabJsonToArray',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => [
		'ID' => 'other',
	],
	'FILTER' => [
		'EXCLUDE' => CBPHelper::DISTR_B24,
	],
    "RETURN" => [
        "DecodedArray" => [
            "NAME" => "Результат от декодирования JSON",
            "TYPE" => "customarray"
        ],
        'DecodedArrayPrintable' => [
            'NAME' => 'Результат от декодирования JSON (Printable)',
            'TYPE' => 'text'
        ],
    ],
    'ADDITIONAL_RESULT' => ['DecodedArray','DecodedArrayPrintable'],
];

