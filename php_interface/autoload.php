<?php
//region CRest
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\CRest' => '/local/crest/crest.php',
	'CRest' => '/local/crest/crest.php',
    '\B24Rest' => '/local/classes/B24Rest.php',
    'B24Rest' => '/local/classes/B24Rest.php',

	'\RestTest' => '/local/php_interface/class-resttest.php',
]);
//endregion CRest

//region Seller_Engine
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	//'KPLab\BalancePlatformRequest' => '/local/classes/balanceplatform/balanceplatform.php',
	'\KPLab\SellerEngine' => '/local/classes/seller_engine/se.php',
	'\KPLab\SellerCapitalLK' => '/local/classes/seller_engine/lk.php',
    '\KPLab\SellerCapitalOSK' => '/local/classes/seller_engine/osk.php',
]);
//endregion Seller_Engine

//region OneC
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'KPLab\Integration\OneC' => '/local/classes/integration.php',

	'KPLab\OneC\Sync' => '/local/classes/onec/sync.php',
	'KPLab\OneC\ContactPersons' => '/local/classes/onec/contactpersons.php',

	'KPLab\OneC\Export\Company' => '/local/classes/onec/export/company.php',
	'KPLab\OneC\Export\Contact' => '/local/classes/onec/export/contact.php',
	'KPLab\OneC\Export\Zayavka' => '/local/classes/onec/export/zayavka.php',

	'KPLab\OneC\Import\Contact' => '/local/classes/onec/import/contact.php',
]);
//endregion OneC

//region OrdLab
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'KPLab\OrdLab\Entity' => '/local/classes/ordlab/entity.php',
	'KPLab\OrdLab\Organizations' => '/local/classes/ordlab/organizations.php',
	'KPLab\OrdLab\Contracts' => '/local/classes/ordlab/contracts.php',
	'KPLab\OrdLab\Creatives' => '/local/classes/ordlab/creatives.php',
	'KPLab\OrdLab\Invoices' => '/local/classes/ordlab/invoices.php',
    'KPLab\OrdLab\KKTU' => '/local/classes/ordlab/ktu.php',
    'KPLab\OrdLab\LoadFromORD' => '/local/classes/ordlab/loadFromORD.php',
	'KPLab\OrdLab\DeleteFromORD' => '/local/classes/ordlab/deleteFromORD.php',
	'KPLab\OrdLab\Platforms' => '/local/classes/ordlab/platforms.php',

]);
//endregion OrdLab

//region DiadocApi
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\KPLab\DiadocApi' => '/local/classes/diadoc/diadocapi.php',
	'\KPLab\DiadocApi\DocumentAttachment' => '/local/classes/diadoc/documentattachment.php',
	'\KPLab\DiadocApi\MessageToPost' => '/local/classes/diadoc/messagetopost.php',
]);
//endregion DiadocApi

//region SbisApi
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\KPLab\SbisApi' => '/local/classes/sbis/sbisapi.php'
]);
//endregion SbisApi

//region Pep
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\KPLab\Pep' => '/local/classes/pep/pep.php'
]);
//endregion SbisApi

//region TimeMan
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'KPLab\TimeMan\WorkTime' => '/local/classes/timeman/worktime.php',
]);
//endregion TimeMan

//region CustomAgents
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\KPLab\CustomAgents\Leads' => '/local/classes/agents.php',
	'\KPLab\CustomAgents\ORD' => '/local/classes/agents.php'
]);
//endregion CustomAgents

//region Curl
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'KPLab\Curl' => '/local/classes/curl.php',
]);
//endregion Curl

//region ApiRequest
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'KPLab\ApiRequest' => '/local/classes/api/request.php',
]);
//endregion ApiRequest

//region Logs
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'KPLab\Logs\File' => '/local/classes/logs.php',
	'KPLab\Logs\TimeData' => '/local/classes/logs.php',
]);
//endregion Logs

//region VBR
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	'\KPLab\VBR\PostBack' => '/local/classes/vbr/postback.php',
]);
//endregion VBR

//region Lib
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'KPLab\Lib\Generate' => '/local/classes/Lib/Generate.php',
]);
//endregion Lib

//region CRM
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'KPLab\CRM\Company' => '/local/classes/CRM/Company.php',
    'KPLab\CRM\AddressTable' => '/local/classes/CRM/AddressTable.php',
    'KPLab\CRM\Deal' => '/local/classes/CRM/Deal.php',
	'KPLab\CRM\DeleteFromB24' => '/local/classes/CRM/deleteFromB24.php',
	'KPLab\CRM\TaskManager' => '/local/classes/CRM/TaskManager.php',
	'KPLab\CRM\MergerFactory' => '/local/classes/CRM/merger_test.php',
]);
//endregion CRM


//region General
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
	// ключ - имя класса с простанством имен, значение - путь относительно корня сайта к файлу
	'\MyClass' => '/local/classes/myclass.php',
	'\MergePDF' => '/local/classes/merge.php',
    'KPLab\CRM\Company' => '/local/classes/crm/company.php',
    '\KPLab\Tasks\Handler' => '/local/classes/Tasks/Handler.php',
	'KPLab\Authentication' => '/local/classes/authentication.php',
	'KPLab\Slots' => '/local/classes/slots.php',
	'\Bitrix24API' => '/local/classes/bitrix24_api.php',
	'\KPLab\API\V2\Controller\BalancePlatform' => '/local/modules/kplab.api/controller/Balanceplatform.php',
	'\KPLab\Sellers' => '/local/classes/Sellers/class.php',
	'\KPLab\Helpers\Address' => '/local/classes/Helpers/Address.php'
]);
//endregion General