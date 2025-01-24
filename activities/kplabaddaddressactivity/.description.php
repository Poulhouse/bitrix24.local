<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$arActivityDescription = [
	"NAME" => Loc::getMessage("CRM_ADD_ADDRESS_NAME"),
	"DESCRIPTION" => Loc::getMessage("CRM_ADD_ADDRESS_DESCRIPTION"),
	"TYPE" => "activity",
	"CLASS" => "KPLabAddAddressActivity",
	"JSCLASS" => "BizProcActivity",
	"CATEGORY" => [
		"ID" => "other",
	],
	"RETURN" => [],
	"FILTER" => [
		"INCLUDE" => [
			["crm"],
		],
	],
];