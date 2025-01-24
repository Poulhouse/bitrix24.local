<?php

use \Bitrix\Main\EventManager;
use \Whatasoft\IBlock\Properties\Custom\DealFields;
use \Whatasoft\IBlock\Properties\Custom\ShowFields;
use \Whatasoft\IBlock\Properties\Custom\ConditionFields;
use \Whatasoft\IBlock\Properties\Custom\OrderedIBlockSectionFields;
use \Whatasoft\Campaign\CampaignEventHandler;
$eventManager = EventManager::getInstance();

// Кастомное свойство элемента инфоблока
$eventManager->addEventHandler(
  'iblock',
  'OnIBlockPropertyBuildList',
  [DealFields::class, 'GetUserTypeDescription']
);

$eventManager->addEventHandler(
  'iblock',
  'OnIBlockPropertyBuildList',
  [ShowFields::class, 'GetUserTypeDescription']
);

$eventManager->addEventHandler(
  'iblock',
  'OnIBlockPropertyBuildList',
  [ConditionFields::class, 'GetUserTypeDescription']
);

$eventManager->addEventHandler(
  'iblock',
  'OnIBlockPropertyBuildList',
  [OrderedIBlockSectionFields::class, 'GetUserTypeDescription']
);


$eventManager->addEventHandler(
  'iblock',
  'OnIBlockElementDelete',
  [CampaignEventHandler::class, 'OnBeforeCampaignDelete']
);

$eventManager->addEventHandler(
  'crm',
  'OnBeforeCrmDealAdd',
  array("\Whatasoft\CrmDeal\Helper", "OnBeforeCrmDealAdd")
);

$eventManager->addEventHandler(
  'crm',
  'OnAfterCrmDealAdd',
  array("\Whatasoft\CrmDeal\Helper", "OnAfterCrmDealAdd")
);

