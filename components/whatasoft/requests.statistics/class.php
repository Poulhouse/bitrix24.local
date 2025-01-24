<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime as BDateTime;
use Bitrix\Main\Entity;
use Bitrix\Crm\DealTable;
use Bitrix\Highloadblock as HL; 


class RequestsStatisticsComponent extends CBitrixComponent{
  private static $LANG_PREFIX = "WAS_REQUESTS_STATISTICS_";

  public function onPrepareComponentParams($arParams){

    $from = DateTime::createFromFormat('d.m.Y', $arParams['FROM']);

    if(!$from){
      $from = new DateTime;
    }
    $arParams['FROM'] = BDateTime::createFromPhp($from)->setTime(0, 0, 0);

    $to = DateTime::createFromFormat('d.m.Y', $arParams['TO']);
    if($to){
      $arParams['TO'] = BDateTime::createFromPhp($to->setTime(23, 59, 59));
    }else{
      $arParams['TO'] = false;
    }

    return $arParams;
  }

  public function executeComponent(){
    global $APPLICATION, $USER;
    
    Loader::IncludeModule('iblock');
    Loader::IncludeModule('crm');
    
    $this->arResult = array();
    $this->user_id = $USER->GetID();
    $this->user_is_admin = $USER->IsAdmin();

    $this->prepareData();
    $this->includeComponentTemplate();
  }

  private function prepareQueueGroups(){

  $entity = isset($this->arParams['ENTITY']) ? $this->arParams['ENTITY'] : 'internet';

    $this->queue_group_ids = array();
    $this->arResult['QUEUE_GROUPS'] = array();
    $arFilter = array(
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
    );

    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    'PROPERTY_TYPE'
    );

    $dbQueueGroups = CIBlockElement::GetList(array($_GET['by']=>$_GET['order']), $arFilter, false, false, $arSelect);

    while($obQueueGroup = $dbQueueGroups->GetNextElement()){
    //уберите это пожалуйста в arFilter
    /*
    if ($obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] == 102 && $entity == 'internet'){
      continue;
    }
    if ($obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] == 100 && $entity == 'telemarketing'){
      continue;
    }
    if ($obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] == 101 && $entity == 'incoming'){
      continue;
    }*/
    if ($entity == 'internet' && $obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] != 100)
      continue;
    if ($entity == 'telemarketing' && $obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] != 102)
      continue;
    if ($entity == 'incoming' && $obQueueGroup->fields['PROPERTY_TYPE_ENUM_ID'] != 101)
      continue;


      $arQueueGroup = $obQueueGroup->GetFields();
      $arQueueGroup['PROPERTIES'] = $obQueueGroup->GetProperties();
    if ($arQueueGroup['PROPERTIES']['IS_NIGHT']['VALUE'] == 'Y'){
      $arQueueGroup['NAME'] .= ' (ночная)';
    }
      $arQueueGroup['CALL_EXPIRED_TIME'] = !empty($arQueueGroup['PROPERTIES']['CALL_EXPIRED_TIME']['VALUE'])
        ? intval($arQueueGroup['PROPERTIES']['CALL_EXPIRED_TIME']['VALUE']) * 60 : DEFAULT_PLANNED_CALL_EXPIRED_TIME;
      $this->arResult['QUEUE_GROUPS'][$arQueueGroup['ID']] = $arQueueGroup;
    }
  }
  
  private function getTotalDealsInGroup($_group_id){
    $arFields = array();
    $arFields['select'] = array(
      'CNT'
    );
    $arFields['filter'] = array(
      '=IS_RECURRING' => 'N',
      'UF_CRM_QUEUE_GROUP' => $_group_id,
      '>=UF_CRM_PLANNED_CALL' => $this->arParams['FROM'],
    );

    if($this->arParams['TO']){
      $arFields['filter']['<=UF_CRM_PLANNED_CALL'] = $this->arParams['TO'];
    }
    $arFields['runtime'] = array(
      new Entity\ExpressionField('CNT', 'COUNT(*)')
    );
    
    $total = 0;
    $dbResult = DealTable::getList($arFields);
    if($res = $dbResult->fetch()){
      $total = $res['CNT'];
    }
    
    return $total;
  }
  
  private function getAppointmentDealsInGroup($_group_id){
    $arFields = array();
    $arFields['select'] = array(
      'CNT'
    );
    $arFields['filter'] = array(
      '=IS_RECURRING' => 'N',
      'UF_CRM_QUEUE_GROUP' => $_group_id,
      '>=UF_CRM_PLANNED_CALL' => $this->arParams['FROM'],
      'HISTORY.STAGE_ID' => QUEUE_DEAL_STAGE_APPOINTMENT,
    );
    if($this->arParams['TO']){
      $arFields['filter']['<=UF_CRM_PLANNED_CALL'] = $this->arParams['TO'];
    }
    $arFields['group'] = array('ID');
    $arFields['runtime'] = array(
      new Entity\ExpressionField('CNT', 'COUNT(*)')
    );
    
    $total = 0;
    $dbResult = DealTable::getList($arFields);
    if($res = $dbResult->fetch()){
      $total = $res['CNT'];
    }
    
    return $total;
  }
  
  private function getDeclineDealsInGroup($_group_id){
    $arFields = array();
    $arFields['select'] = array(
      'CNT'
    );
    $arFields['filter'] = array(
      '=IS_RECURRING' => 'N',
      'UF_CRM_QUEUE_GROUP' => $_group_id,
      '>=UF_CRM_PLANNED_CALL' => $this->arParams['FROM'],
      'HISTORY.STAGE_ID' => QUEUE_DEAL_STAGE_DECLINE,
    );
    if($this->arParams['TO']){
      $arFields['filter']['<=UF_CRM_PLANNED_CALL'] = $this->arParams['TO'];
    }
    $arFields['group'] = array('ID');
    $arFields['runtime'] = array(
      new Entity\ExpressionField('CNT', 'COUNT(*)')
    );
    
    $total = 0;
    $dbResult = DealTable::getList($arFields);
    if($res = $dbResult->fetch()){
      $total = $res['CNT'];
    }
    
    return $total;
  }

  private function getExpiredDealsInGroup($_group_id){
    $arFilter = array(
      '=IS_RECURRING' => 'N',
      'UF_CRM_QUEUE_GROUP' => $_group_id,
      '>=UF_CRM_PLANNED_CALL' => $this->arParams['FROM'],
    );
    if($this->arParams['TO']){
      $arFilter['<=UF_CRM_PLANNED_CALL'] = $this->arParams['TO'];
    }
    
    $arSelect = array(
      'ID',
      'UF_CRM_PLANNED_CALL',
      'UF_CRM_CALL_DATE',
    );
    
    $total = 0;
    $dbDeals = CCrmDeal::GetListEx(array('ID' => 'ASC'), $arFilter, false, false, $arSelect);
    while($arDeal = $dbDeals->GetNext()){
      $plannedCallDateTime = $arDeal['DATE_CREATE'];
      if(strlen($arDeal['UF_CRM_PLANNED_CALL'])){
        $plannedCallDateTime = $arDeal['UF_CRM_PLANNED_CALL'];
      }
      $plannedTimestamp = strtotime($plannedCallDateTime);

      if(strlen($arDeal['UF_CRM_CALL_DATE'])){
        $callTimestamp = strtotime($arDeal['UF_CRM_CALL_DATE']);
        $expiredTimeInSeconds = $this->arResult['QUEUE_GROUPS'][$group_id]['CALL_EXPIRED_TIME'];
        if($callTimestamp > $plannedTimestamp && (($callTimestamp - $plannedTimestamp) > $expiredTimeInSeconds)){
          $total++;
        }
      }
    }
    
    return $total;
  }
  
  private function getActiveCountDealsInGroup($_group_id){

  $hlbl = 3;
  $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
  $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
  $entity_data_class = $entity->getDataClass(); 
  $rsData = $entity_data_class::getList(array(
     "select" => array("*"),
     "order" => array("ID" => "ASC"),
     "filter" => array("UF_QUEUE"=>$_group_id, ">=UF_LAST_UPDATE_TIME" => date('d.m.Y H:i:s', strtotime('-2 minutes'))) 
  ));
  $total = 0;
  while($arData = $rsData->Fetch()){

     $total++;
  }
    return $total;
  }
  private function getActiveOperatorsInGroup($_group_id){

  $hlbl = 3;
  $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
  $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
  $entity_data_class = $entity->getDataClass();
  $rsData = $entity_data_class::getList(array(
     "select" => array("*"),
     "order" => array("ID" => "ASC"),
     "filter" => array("UF_QUEUE"=>$_group_id, ">=UF_LAST_UPDATE_TIME" => date('d.m.Y H:i:s', strtotime('-2 minutes'))) 
  ));
  $users = [];

  while($arData = $rsData->Fetch()){
	  $us = CUser::GetById($arData['UF_USER'])->GetNext();
	  $users[] = $us['NAME'] . ' ' . $us['LAST_NAME'];
  }
    return $users;
  }
  private function prepareData(){
    $this->prepareQueueGroups();
    foreach($this->arResult['QUEUE_GROUPS'] as &$arGroup){
      $arGroup['TOTAL'] = $this->getTotalDealsInGroup($arGroup['ID']);
      $arGroup['APPOINTMENTS'] = $this->getAppointmentDealsInGroup($arGroup['ID']);
      $arGroup['DECLINES'] = $this->getDeclineDealsInGroup($arGroup['ID']);
      $arGroup['EXPIRED'] = $this->getExpiredDealsInGroup($arGroup['ID']);
      $arGroup['ACTIVE_COUNT'] = $this->getActiveCountDealsInGroup($arGroup['ID']);
		$arGroup['OPERATORS'] = implode('<br />', $this->getActiveOperatorsInGroup($arGroup['ID']));
    }
    unset($arGroup);
  }
}