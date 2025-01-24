<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
	'NAME' => Loc::getMessage('BPGLDA_DESCR_NAME_1'),
	'DESCRIPTION' => Loc::getMessage('BPGLDA_DESCR_DESCR_MSGVER_1'),
	'TYPE' => ['activity', 'robot_activity'],
	'CLASS' => 'KPLabGetCRMLinkElementIblock',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => [
		'ID' => 'document',
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
    ),
	//'ADDITIONAL_RESULT' => ['ElementsId'],
	'ROBOT_SETTINGS' => [
		'CATEGORY' => 'employee',
		'GROUP' => ['modificationData'],
		'SORT' => 5000,
		'IS_SUPPORTING_ROBOT' => true,
	],
];