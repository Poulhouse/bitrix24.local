<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock;
use Bitrix\Main\Type\DateTime as BDateTime;
use Whatasoft\IBlock\Fields\Manager;
use Whatasoft\Cache\CacheParams;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

class IncomingQueueComponent extends CBitrixComponent{
  const INCOMING_ID = 101;
  
  private static $LANG_PREFIX = "WAS_REQUESTS_QUEUE_";
  
  public function onPrepareComponentParams($arParams){
    $arParams['COMPONENT_RETURNS_DATA'] = 'Y';
    $arParams['NIGHT'] = $arParams['NIGHT'] == 'Y' ? 'Y' : 'N';
    $arParams['TYPE'] = $arParams['TYPE'] == 'incoming' ? 'incoming' : '';
    return $arParams;
  }

  public function executeComponent(){
    global $APPLICATION, $USER;
    
    Loader::IncludeModule('iblock');
    Loader::includeModule("highloadblock");
    Loader::IncludeModule('crm');
    
    $this->arResult = array();
    $this->user_id = $USER->GetID();
    $this->user_is_admin = $USER->IsAdmin();
    $this->user_groups = $USER->GetUserGroupArray();
    $this->locked_minutes = QUEUE_DEAL_LOCKED_MINUTES;
    $this->field_manager = new Manager();
    $this->crm_contact_def_fields = $this->field_manager->getCrmContactFields();
    $this->crm_deal_def_fields = $this->field_manager->getCrmDealFields();
    $this->is_night = $this->arParams['NIGHT'] == 'Y';
    $this->type = $this->arParams['TYPE'];
    if($this->arParams["WAS_FROM_AJAX"] == "Y"){
      return $this->processRequest();
    }
    
    $this->defaultTemplate();
  }
  
  private function prepareAjax(){
    CJSCore::Init(array("fx", "jquery", "ajax"));
    $cache_id = CacheParams::SetCache($this->getName(), $this->getTemplateName(), $this->arParams);
    $this->arResult["AJAX_CACHE_ID"] = $cache_id;
  }
  
  private function defaultTemplate(){
    $this->prepareAjax();
    $this->prepareData();
    $this->includeComponentTemplate();
  }
  
  private function prepareData(){
    global $APPLICATION;
    $this->arResult['FILTER'] = array();
    $this->arResult['FILTER'][] = array(
      'NAME' => 'Дневные',
      'LINK' => $APPLICATION->GetCurPage(false),
      'CURRENT' => $this->is_night ? false : true,
    );
    $this->arResult['FILTER'][] = array(
      'NAME' => 'Ночные',
      'LINK' => $this->arResult['FILTER']['DAY']['LINK'] .'?night=Y',
      'CURRENT' => $this->is_night ? true : false,
    );
    $this->arResult['AUTO_CALL'] = false;
    if(in_array(GROUP_CALL_SPECIALIST_ID, $this->user_groups)){
      $this->arResult['AUTO_CALL'] = true;
    }
  }
  
  private function processRequest(){
    $result = array();
    $result['success'] = false;
    $result['message'] = '';
    
    if(!isset($_POST['action'])){
      $result['message'] = 'Неверный запрос';
      return $result;
    }
    
    $action = trim($_POST['action']);
    
    switch($action){
      case 'get_queue':
        $this->prepareQueueGroups();
        $result['groups'] = $this->prepareResponse();
        $result['success'] = true;
        break;
      case 'update_user_queues':
        if(isset($_POST['cur_groups']) && is_array($_POST['cur_groups'])){
          $hlbl = 3;
          $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
          $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
          $entity_data_class = $entity->getDataClass(); 
          $rsData = $entity_data_class::getList(array(
            "select" => array("*"),
            "filter" => array("UF_USER" => $this->user_id)
          ));
          
          $curUserData = array();
          while($arData = $rsData->Fetch()){
            $curUserData[$arData['UF_QUEUE']] = $arData;
          }
          foreach($_POST['cur_groups'] as $group_id){
            $group_id = intval($group_id);
            $data = array(
              "UF_USER" => $this->user_id,
              "UF_QUEUE" => $group_id,
              "UF_LAST_UPDATE_TIME" => date("d.m.Y H:i:s"),
            );
            if(isset($curUserData[$group_id])){
              $res = $entity_data_class::update($curUserData[$group_id]['ID'], $data);
            }else{
              $res = $entity_data_class::add($data);
            }
          }
          $result['success'] = true;
        }
        break;
    }

    return $result;
  }
  
  private function prepareQueueGroups(){
    $this->queue_group_ids = array();
    $this->arResult['QUEUE_GROUPS'] = array();
    $defaultQueueColor = '10a10b';
    $arFilter = array(
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
      'PROPERTY_ACTIVE' => 'Y',
      'PROPERTY_OPERATOR_IDS' => $this->user_id,
    );
    if($this->is_night){
      $arFilter['PROPERTY_IS_NIGHT'] = 'Y';
    }else{
      $arFilter['!PROPERTY_IS_NIGHT'] = 'Y';
    }
    if($this->user_is_admin){
      unset($arFilter['PROPERTY_OPERATOR_IDS']);
    }
    
    $arFilter['PROPERTY_TYPE'] = self::INCOMING_ID;
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbQueueGroups = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
    while($obQueueGroup = $dbQueueGroups->GetNextElement()){
      $arQueueGroup = $obQueueGroup->GetFields();
      $arQueueGroup['PROPERTIES'] = $obQueueGroup->GetProperties();
      $arQueueGroup['TOTAL_DEALS'] = 0;
      $arQueueGroup['TOTAL_DEALS_EXPIRED'] = 0;
      $arQueueGroup['CALL_EXPIRED_TIME'] = !empty($arQueueGroup['PROPERTIES']['CALL_EXPIRED_TIME']['VALUE'])
        ? intval($arQueueGroup['PROPERTIES']['CALL_EXPIRED_TIME']['VALUE']) * 60 : DEFAULT_PLANNED_CALL_EXPIRED_TIME;
      
      $arQueueGroup['COLOR'] = !empty($arQueueGroup['PROPERTIES']['LIST_COLOR']['VALUE']) ? $arQueueGroup['PROPERTIES']['LIST_COLOR']['VALUE'] : $defaultQueueColor;
      $arQueueGroup['COLOR'] = str_replace('#', '', $arQueueGroup['COLOR']);
      
      $this->arResult['QUEUE_GROUPS'][$arQueueGroup['ID']] = $arQueueGroup;
      $this->queue_group_ids[] = $arQueueGroup['ID'];
    }
    
    $this->prepareGroupConfigs();
  }
  
  private function prepareGroupConfigs(){
    $item_ids = array();
    foreach($this->arResult['QUEUE_GROUPS'] as &$arGroup){
      $arGroup['CONFIG'] = array(
        'contact' => array(),
        'deal' => array(),
      );
      if(intval($arGroup['PROPERTIES']['VIEW_CONFIG_ID']['VALUE'])){
        $item_ids[] = intval($arGroup['PROPERTIES']['VIEW_CONFIG_ID']['VALUE']);
      }
    }
    unset($arGroup);
    
    if(!count($item_ids)){
      return;
    }
    
    $arConfigs = array();
    $arFilter = array(
      'ID' => $item_ids,
    );
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbConfigs = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
    while($obConfig = $dbConfigs->GetNextElement()){
      $arConfig = $obConfig->GetFields();
      $arConfig['PROPERTIES'] = $obConfig->GetProperties();
      $arConfigs[$arConfig['ID']] = $arConfig;
    }
    
    foreach($this->arResult['QUEUE_GROUPS'] as &$arGroup){
      $config_id = $arGroup['PROPERTIES']['VIEW_CONFIG_ID']['VALUE'];
      if(isset($arConfigs[$config_id])){
        $config = $arConfigs[$config_id]['PROPERTIES']['LIST_VIEW_FIELDS']['VALUE'];
        if(is_array($config) && isset($config['contact'])){
          $arGroup['CONFIG'] = $config;
        }
      }
    }
    unset($arGroup);
  }

  private function prepareResponse(){
    $groups = array();
    
    foreach($this->arResult['QUEUE_GROUPS'] as $arGroup){
      $group = array();
      $group['id'] = $arGroup['ID'];
      $group['name'] = ($arGroup['PROPERTIES']['TYPE']['VALUE'] == 'Телемаркетинг' ? '<b>ТЕЛЕМАРКЕТИНГ</b> ' : '')  . $arGroup['PROPERTIES']['USER_NAME']['VALUE'];
      $group['color'] = $arGroup['COLOR'];
      $group['total_deals'] = 0;
      $group['total_deals_expired'] = 0;
      $group['items'] = array();
      $groups[$group['id']] = $group;
    }
    
    $arr_groups = array();
    foreach($groups as $group){
      $arr_groups[] = $group;
    }
    
    return $arr_groups;
  }
}