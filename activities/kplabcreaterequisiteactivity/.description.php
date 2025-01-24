<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arActivityDescription = [
	'NAME' => Loc::getMessage('CRM_CREATE_REQUISITE_ACTIVITY_NAME'),
	'DESCRIPTION' => Loc::getMessage('CRM_CREATE_REQUISITE_ACTIVITY_DESC'),
	'TYPE' => 'activity',
	'CLASS' => 'KplabCreateRequisiteActivity',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => [
		'ID' => 'crm',
	],
	'RETURN' => [
		'RequisiteId' => [
			'NAME' => Loc::getMessage('BPRIOA_ACT_PROP_REQUISITE_ID'),
			'TYPE' => 'int',
		],
		'ErrorMessage' => [
			'NAME' => Loc::getMessage('BPRIOA_ACT_PROP_ERROR_MESSAGE'),
			'TYPE' => 'string',
		],
	],
];
?>
