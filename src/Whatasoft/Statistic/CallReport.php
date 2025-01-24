<?php

namespace Whatasoft\Statistic;

use Whatasoft\IBlock\Fields\Manager;
use Whatasoft\Statistic\Helpers\CrmFieldManager;
use Whatasoft\Helpers\Traits\ModuleLoader;

class CallReport {
  
  use ModuleLoader;
  
  private $dealId;
  private $managerId;
  
  private $arDeal = [];
  private $arContact = [];
  private $fieldManager = null;
  private $crmFieldManager = null;
  private $reportType;
  private $requiredModules = ['iblock', 'crm'];
  
  const REPORT_DATETIME_FORMAT = 'd.m.Y H:i:s';
  const REPORT_TYPE_INTERNET_APPLICATIONS = 1000;
  const REPORT_TYPE_TELEMARKETING = 1001;
  const REPORT_TYPE_INCOMING = 1002;
  

  // Поля контакта, которые не будут включены в отчёт
  const EXCLUDED_CONTACT_FIELDS = [
    'ID',
    'POST',
    'NAME',
    'SECOND_NAME',
    'LAST_NAME',
    'UF_CRM_TYPE',
    'UF_CRM_1598003491',
	'UF_CRM_5F9134B05650D'
  ];
  
  // Поля сделки, которые не будут включены в отчёт
  const EXCLUDED_DEAL_FIELDS = [
    'ID',
    'UF_CRM_PLANNED_CALL',
    'UF_CRM_QUEUE_GROUP',
    'UF_CRM_CITY',
    'UF_CRM_LOCKED_BY',
    'UF_CRM_LOCKED_FROM',
	  //    'UF_CRM_STATUS',
	  // 'UF_CRM_DECLINE',
	  //'UF_CRM_MEET_OFFICE',
	  //'UF_CRM_MEET_DATE',
	  //'UF_CRM_CALL_DATE',
	  //'UF_CRM_CALL_RETRIES',
	  //'UF_CRM_SPECIAL_OFFER',
    'UF_CRM_1599218319',
  ];
  
  public function __construct($dealId, $managerId, $reportTypeCode)
  {
    $this->dealId = $dealId;
    $this->managerId = $managerId;
    $this->reportTypeCode = $reportTypeCode;
    $this->fieldManager = new Manager();
    $this->crmFieldManager = new CrmFieldManager();
    $this->includeModules();
  }
  
  public function getReport()
  {
    $arManager = $this->crmFieldManager->getManagerById($this->managerId);
    $arDeal = $this->crmFieldManager->getDealById($this->dealId);
    $arDealStatus = $this->crmFieldManager->getDealStatus($arDeal['STAGE_ID']);
    $arContact = $this->crmFieldManager->getContactById($arDeal['CONTACT_ID']);
    $arQueue = $this->crmFieldManager->getQueueById($arDeal['UF_CRM_QUEUE_GROUP'], ['ID', 'NAME']);
    $phoneToCall = $this->getContactPhoneToCall($arDeal['CONTACT_ID']);
    $obCallDateTime = $this->getPhoneCallDateTime($arDeal);
    $callStatus = $this->getCallStatus($arDeal['UF_CRM_STATUS']);
    $arContactFields = $this->getContactFields($arContact);
    $arDealFields = $this->getDealFields($arDeal);

    return [
      'DEAL_ID' => $this->dealId,
      'CONTACT_ID' => $arContact['ID'],
      'STAGE_ID' => $arDeal['STAGE_ID'],
		'STATUS_NAME' => $arDealStatus,//['NAME'],
      'REPORT_TYPE_CODE' => $this->reportTypeCode,
      'MANAGER_ID' => $this->managerId,
      'MANAGER_NAME' => $this->getManagerFullName($arManager),
      'QUEUE_ID' => $arQueue['ID'],
      'QUEUE_NAME' => $arQueue['NAME'],
      'CALL_DATETIME' => $obCallDateTime->format(self::REPORT_DATETIME_FORMAT),
      'CALL_TIMESTAMP' => $obCallDateTime->getTimestamp(),
      'CLIENT_LAST_NAME' => $arContact['LAST_NAME'],
      'CLIENT_NAME' => $arContact['NAME'],
      'CLIENT_SECOND_NAME' => $arContact['SECOND_NAME'],
      'PHONE_TO_CALL' => $phoneToCall,
      'CALL_STATUS' => $callStatus,
      'CONTACT_FIELDS' => $arContactFields,
      'DEAL_FIELDS' => $arDealFields,
    ];
  }
    
  public function getContactFields(array $arContact)
  {
    return array_merge(
      $this->getContactCommonFields($arContact),
      $this->getContactUserFields($arContact)
    );
  }
  
  public function getDealFields(array $arDeal)
  {
    return array_merge(
      $this->getDealCommonFields($arDeal),
      $this->getDealUserFields($arDeal)
    );
  }
  
  public function getContactCommonFields(array $arContact)
  {
    $contactFields = [];
    $excludedFields = self::EXCLUDED_CONTACT_FIELDS;
    $fields = $this->crmFieldManager->getContactCommonFieldsNames($excludedFields);
    
    foreach($fields as $fieldCode => $fieldName) {
      $contactFields[$fieldCode] = $this->makeFieldDescription(
        $fieldName,
        $arContact[$fieldCode] ?? '',
        $arContact[$fieldCode] ?? ''
      );
    }
    
    return $contactFields;
  }
  
  public function getDealCommonFields(array $arDeal)
  {
    $dealFields = [];
    $excludedFields = self::EXCLUDED_DEAL_FIELDS;
    $fields = $this->crmFieldManager->getDealCommonFieldsNames($excludedFields);

    foreach($fields as $fieldCode => $fieldName) {
      $dealFields[$fieldCode] = $this->makeFieldDescription(
        $fieldName,
        $arDeal[$fieldCode] ?? '',
        $arDeal[$fieldCode] ?? ''
      );
    }
    	
    return $dealFields;
  }
  
  public function getContactUserFields(array $arContact)
  {
    global $USER_FIELD_MANAGER;
    $arResult = [];
    $excludedFields = self::EXCLUDED_CONTACT_FIELDS;
    $userFields = $this->crmFieldManager->getContactUserFieldsNames($excludedFields);
    $userFieldsValues = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $arContact['ID']);
    foreach($userFields as $fieldCode => $fieldName) {
      $arResult[$fieldCode] = $this->makeFieldDescription(
        $fieldName,
        $userFieldsValues[$fieldCode]['VALUE'] ?? '',
        $this->crmFieldManager->renderUserFieldValue($userFieldsValues[$fieldCode])
      );
    }
    return $arResult;
  }
  
  public function getDealUserFields(array $arDeal)
  {
    global $USER_FIELD_MANAGER;
    $arResult = [];
	  //	print_r($arDeal);die();
    $excludedFields = self::EXCLUDED_DEAL_FIELDS;
    $userFields = $this->crmFieldManager->getDealUserFieldsNames($excludedFields);
    $userFieldsValues  = $USER_FIELD_MANAGER->GetUserFields('CRM_DEAL', $arDeal['ID']);
    foreach($userFields as $fieldCode => $fieldName) {
      $arResult[$fieldCode] = $this->makeFieldDescription(
        $fieldName,
        $userFieldsValues[$fieldCode]['VALUE'] ?? '',
        $this->crmFieldManager->renderUserFieldValue($userFieldsValues[$fieldCode])
      );
    }
    return $arResult;
  }
    
  private function makeFieldDescription($name, $rawValue, $textValue)
  {
    return [
      'NAME' => $name,
      'RAW_VALUE' => $rawValue,
      'VALUE' => $textValue,
    ];
  }
  
  private function getManagerFullName(array $arManager)
  {
    $lastname = $arManager['LAST_NAME'] ?? '';
    $surname = $arManager['SECOND_NAME'] ?? '';
    $name = $arManager['NAME'] ?? '';
    return trim(implode(' ', array_map('trim', [$lastname, $name, $surname])));
  }
    
  public function getContactPhoneToCall($contactId, $default = '')
  {
    if (!$contactId) {
      return $default;
    }
    $arOrder = ['ID' => 'ASC'];
    $arFilter = [
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $contactId,
      'TYPE_ID' => 'PHONE',
    ];
    $dbResult = \CCrmFieldMulti::GetList($arOrder, $arFilter);
    $arPhone = $dbResult->Fetch();
    return (is_array($arPhone) && !empty($arPhone['VALUE']))
      ? $arPhone['VALUE'] 
      : $default;
  }
  
  public function getPhoneCallDateTime(array $arDeal)
  {
    $strCallDateTime = $arDeal['UF_CRM_CALL_DATE'] ?? '';
    if (!strlen($strCallDateTime)) {
      return \Bitrix\Main\Type\DateTime::createFromPhp(new \DateTime('now'));
    }
    
    return new \Bitrix\Main\Type\DateTime($strCallDateTime, self::REPORT_DATETIME_FORMAT);
  }
  
  public function getCallStatus($statusId, $default = '')
  {
    $arStatuses = $this->fieldManager->getCrmDealUserEnumFieldVariants('UF_CRM_STATUS');
    return $arStatuses[$statusId] ?? $default;
  }
  
}