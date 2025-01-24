<?php

namespace Whatasoft\Statistic\Helpers;

use Whatasoft\IBlock\Fields\Manager;
use Whatasoft\Helpers\Traits\ModuleLoader;

class CrmFieldManager {
  
  use ModuleLoader;

  const RENDER_MODE_TEXT = 'main.public_text';
  
  private $requiredModules = ['iblock', 'crm'];
  private $fieldManager;
  
  public function __construct()
  {
    $this->fieldManager = new Manager();
    $this->includeModules();
  }
  
  public function getDealById($id, array $arSelect = ['*', 'UF_*'], $default = null)
  {
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $id,
    ];
    $arResult = \CCrmDeal::GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect)->Fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getDealList($id, array $arSelect = ['*', 'UF_*'])
  {
    $arResult = [];
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $id,
    ];
    $dbResult = \CCrmDeal::GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect);
    
    while($arRow = $dbResult->GetNext()) {
      $arResult[$arRow['ID']] = $arRow;
    }
    
    return $arResult;
  }
  
  public function getContactById($id, array $arSelect = ['*', 'UF_*'], $default = null)
  {
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $id,
    ];
    $arResult = \CCrmContact::GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect)->Fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getQueueById($queueId, array $arSelect = ['*'], $default = null)
  {
    $arFilter = [
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
      'ID' => $queueId
    ];
    $arResult = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect)->Fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getContactsPhoneToCall($contactIds)
  {
    $arPhones = [];
    $arOrder = ['ID' => 'ASC'];
    $arFilter = [
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $contactIds,
      'TYPE_ID' => 'PHONE',
    ];
    $dbResult = \CCrmFieldMulti::GetList($arOrder, $arFilter);

    while ($arRow = $dbResult->GetNext()) {
      $contactId = $arRow['ELEMENT_ID'];
      $phone = (string)$arRow['~VALUE'];
      $mobile = \Whatasoft\Helpers\Phone::standardizeMobileNumber($phone);
      $isContactPhoneExist = isset($arPhones[$contactId]);
      
      if (!$isContactPhoneExist && $mobile) {
        $arPhones[$contactId] = $mobile;
      }
      
    }
    
    return $arPhones;
  }
  
  public function getManagerById($managerId, $default = null)
  {
    $storage = new \CUser();
    $arResult = $storage->GetByID($managerId)->Fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getDealStatus($statusId, $default = null)
  {
    //$arFilter = ['STATUS_ID' => $statusId];
    //$arResult = \CCrmStatus::GetList([], $arFilter)->Fetch();
    $arResult = \CCrmStatus::GetStatusList('DEAL_STAGE');

    return is_array($arResult) && isset($arResult[$statusId]) ? $arResult[$statusId] : $default;
  }
  
  public function getQueueList($arOrder = [], $arFilter = [], $arSelect = ['*'])
  {
    $arResult = [];
    $arFilter = array_merge($arFilter, [
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
    ]);
    $dbResult = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
    while($arRow = $dbResult->GetNext()) {
      $arResult[$arRow['ID']] = $arRow;
    }

    return $arResult;
  }
  
  public function getDealStatusList($arOrder = [], $arFilter = [])
  {
    $arResult = [];
    $dbResult = \CCrmStatus::GetList($arOrder, $arFilter);
    while($arRow = $dbResult->GetNext()) {
      $arResult[$arRow['ID']] = $arRow;
    }

    return $arResult;
  }
  
  public function getDealTypeStageByName($dealTypeId, $stageName)
  {
    $entityId = ((int)$dealTypeId > 1) 
    ? 'DEAL_STAGE_' . $dealTypeId 
    : 'DEAL_STAGE';
    
    $arFilter = [
      'ENTITY_ID' => $entityId,
      'NAME' => $stageName,
    ];
    
    $arResult = $this->getDealStatusList([], $arFilter);
    
    if (!count($arResult)) {
      return null;
    }
    
    return array_shift($arResult);
  }
  
  public function getDealCategoryStageByName($dealCategoryId, $stageName)
  {
    $entityId = ((int)$dealCategoryId > 1) 
    ? 'DEAL_STAGE_' . $dealCategoryId
    : 'DEAL_STAGE';
    
    $arFilter = [
      'ENTITY_ID' => $entityId,
      'NAME' => $stageName,
    ];
    
    $arResult = $this->getDealStatusList([], $arFilter);
    
    if (!count($arResult)) {
      return null;
    }
    
    return array_shift($arResult);
  }
  
  public function getContactAllFieldsNames(array $excludedFields = [])
  {
    return array_merge(
      $this->getContactCommonFieldsNames($excludedFields),
      $this->getContactUserFieldsNames($excludedFields)
    );
  }
  
  public function getDealAllFieldsNames(array $excludedFields = [])
  {
    return array_merge(
      $this->getDealCommonFieldsNames($excludedFields),
      $this->getDealUserFieldsNames($excludedFields)
    );
  }
  
  public function getContactCommonFieldsNames(array $excludedFields = [])
  {
    $fields = $this->fieldManager->getCrmContactFields();
    $filtered = $this->excludeFields($fields, $excludedFields);
    return $this->getCommonFieldsNames($filtered);
  }
  
  public function getContactUserFieldsNames(array $excludedFields = [])
  {
    $fields = $this->fieldManager->getUserFieldsAsArray('CRM_CONTACT');
    $filtered = $this->excludeFields($fields, $excludedFields);
    return $this->getUserFieldsNames($filtered);
  }
  
  public function getDealCommonFieldsNames(array $excludedFields = [])
  {
    $fields = $this->fieldManager->getCrmDealFields();
    $filtered = $this->excludeFields($fields, $excludedFields);
    return $this->getCommonFieldsNames($filtered);
  }

  public function getDealUserFieldsNames(array $excludedFields = [])
  {
    $fields = $this->fieldManager->getUserFieldsAsArray('CRM_DEAL');
    $filtered = $this->excludeFields($fields, $excludedFields);
    return $this->getUserFieldsNames($filtered);
  }
  
  public function getCommonFieldsNames(array $fields)
  {
    $fieldNames = [];
    foreach ($fields as $fieldCode => $arField) {
      $fieldNames[$fieldCode] = $arField['NAME'];
    }
    return $fieldNames;
  }
  
  public function getUserFieldsNames(array $fields)
  {
    $fieldNames = [];
    foreach ($fields as $fieldCode => $arField) {
      $fieldNames[$fieldCode] = $arField['EDIT_FORM_LABEL'];
    }
    return $fieldNames;
  }
  
  public function excludeFields(array $fields, array $excludedFields = [])
  {
    return array_filter($fields, function($fieldCode) use ($excludedFields) {
      return !in_array($fieldCode, $excludedFields);
    }, ARRAY_FILTER_USE_KEY);
  }
  
  public function renderUserFieldValue(array $userFieldValue, $mode = self::RENDER_MODE_TEXT, array $parameters = [])
  {
    if (empty($userFieldValue['USER_TYPE'])) {
      return null;
    }
    
    $userType = $userFieldValue['USER_TYPE'];
    $className = $userType['CLASS_NAME'];
    
    if (!class_exists($className)) {
      return null;
    }
    
    $parameters['mode'] = $mode;
    $componentName = $className::RENDER_COMPONENT;
    $componentParams = [
      'userField' => $userFieldValue,
      'additionalParameters' => $parameters,
    ];
    
    global $APPLICATION;
		ob_start();
		$APPLICATION->IncludeComponent(
			$componentName,
			'',
			$componentParams,
      null,
      ["HIDE_ICONS"=>"Y"]
		);
		return trim(ob_get_clean());
  }
  
  
  
}