<?php

namespace Whatasoft\IBlock\Fields;

use Bitrix\Main\Loader;
use CCrmContact;
use CCrmDeal;

class Manager {
  protected $requireModules = ['crm', 'iblock'];
  
  public function __construct(){
    $this->includeModules();
  }
  
  protected function includeModules(){
    foreach($this->requireModules as $moduleName){
      Loader::includeModule($moduleName);
    }
  }
  
  public function getCrmContactDefFields(){
    $arFields = array(
      'ID',
      'POST',
      'ADDRESS',
      //'COMMENTS',
      'NAME',
      'SECOND_NAME',
      'LAST_NAME',
      'FULL_NAME',
      'BIRTHDATE',
		'TYPE_ID'
    );
    
    return $arFields;
  }
  
  public function getCrmContactExcludeFields(){
    $arFields = array(
      'ID',
      'UF_CRM_TYPE',
    );
    
    return $arFields;
  }
  
  public function getCrmContactFields(){
    $arOprions = ['LANG' => 'ru'];
    $defFields = $this->getCrmContactDefFields();
    $fields = CCrmContact::GetFields($arOprions);

    foreach($fields as $key => $field){
      if(!in_array($key, $defFields)){
        unset($fields[$key]);
      }else{
        $fields[$key]['NAME'] = $this->getCrmContactFieldCapiton($key);
      }
    }
    
    return $fields;
  }
  
  public function getCrmDealDefFields(){
    $arFields = array(
      'ID',
      'DATE_CREATE',
      'TITLE',
      //'COMMENTS',
      'ADDITIONAL_INFO',
      'CATEGORY_ID',
    );
    
    return $arFields;
  }
  
  public function getCrmDealExcludeFields(){
    $arFields = array(
      'ID',
      'UF_CRM_PLANNED_CALL',
      'UF_CRM_QUEUE_GROUP',
      'UF_CRM_CITY',
      'UF_CRM_LOCKED_BY',
      'UF_CRM_LOCKED_FROM',
      'UF_CRM_STATUS',
      'UF_CRM_DECLINE',
      'UF_CRM_MEET_OFFICE',
      'UF_CRM_MEET_DATE',
      'UF_CRM_CALL_DATE',
      'UF_CRM_CALL_RETRIES',
      'UF_CRM_SPECIAL_OFFER',
    );
    
    return $arFields;
  }
  
  public function getCrmDealFields(){
    $arOprions = ['LANG' => 'ru'];
    $defFields = $this->getCrmDealDefFields();
    $fields = CCrmDeal::GetFields($arOprions);
    foreach($fields as $key => $field){
      if(!in_array($key, $defFields)){
        unset($fields[$key]);
      }else{
        $fields[$key]['NAME'] = $this->getCrmDealFieldCapiton($key);
      }
    }
    
    return $fields;
  }
  
  public function getCrmContactUserFields(){
    return $this->getUserFields('CRM_CONTACT');
  }
  
  public function getCrmDealUserFields(){
    return $this->getUserFields('CRM_DEAL');
  }
  
  public function getUserFields(string $entity_id){
    $arOrder = ["NAME" => "ASC"];
    $arFilter = ["ENTITY_ID" => $entity_id, "LANG" => "ru"];
    return \CUserTypeEntity::GetList($arOrder, $arFilter);
  }
  
  public function getCrmContactFieldCapiton(string $field_name){
    if($field_name == 'FULL_NAME'){
      return 'ФИО';
    }
    
    return CCrmContact::GetFieldCaption($field_name);
  }
  
  public function getCrmDealFieldCapiton(string $field_name){
    return CCrmDeal::GetFieldCaption($field_name);
  }
  
  public function getCrmFields($_type){
    if($_type == 'deal'){
      return $this->getCrmDealFields();
    }
    return $this->getCrmContactFields();
  }
  
  public function getCrmExcludeFields($_type){
    if($_type == 'deal'){
      return $this->getCrmDealExcludeFields();
    }
    return $this->getCrmContactExcludeFields();
  }
  
  public function getCrmUserFields($_type){
    if($_type == 'deal'){
      return $this->getCrmDealUserFields();
    }
    return $this->getCrmContactUserFields();
  }

	public function getCrmContactUserEnumFieldVariants(string $propCode)
	{
		return $this->getUserEnumFieldVariants('CRM_CONTACT', $propCode);
	}
	
	public function getCrmDealUserEnumFieldVariants(string $propCode)
	{
		return $this->getUserEnumFieldVariants('CRM_DEAL', $propCode);
	}
	
	public function getUserEnumFieldVariants(string $entityId, string $propCode)
	{
		$arEnumFieldVariants = [];
		$arUserFields = $this->getUserFieldsAsArray($entityId);
		$arUserProp = $arUserFields[$propCode] ?? [];
		
		if (!empty($arUserProp)) {
			$obEnum = new \CUserFieldEnum;
			$rsEnum = $obEnum->GetList([], ["USER_FIELD_ID" => $arUserProp["ID"]]);
			while($arEnum = $rsEnum->GetNext()) {
				$arEnumFieldVariants[$arEnum['ID']] = $arEnum['VALUE'];
			}
		}


		return $arEnumFieldVariants;
	}
	
	public function getIBlockElementNameList(int $iBlockId, array $arElementId = [])
	{
		$arElementNameList = [];
		$arOrder = ["NAME" => "ASC"];
		$arFilter = ["IBLOCK_ID" => $iBlockId, "ID" => $arElementId];
		$arSelect = ["ID", "NAME"];
		$dbResult = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
		while($arResult = $dbResult->Fetch()) {
			$arElementNameList[$arResult['ID']] = $arResult['NAME'];
		}
		
		return $arElementNameList;
	}
	
	public function getUserFieldsAsArray(string $entityId)
  {
		$arUserFields = [];
		$rsUserFields = $this->getUserFields($entityId);
        while ($arResult = $rsUserFields->Fetch()) {
			$arUserFields[$arResult['FIELD_NAME']] = $arResult;
		}
		
		return $arUserFields;
  }
}