<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock;
use Bitrix\Main\Type\DateTime as BDateTime;
use Whatasoft\IBlock\Fields\Manager;
use Whatasoft\Cache\CacheParams;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

class RequestsQueueComponent extends CBitrixComponent{
  private static $LANG_PREFIX = "WAS_REQUESTS_QUEUE_";
  
  public function onPrepareComponentParams($arParams){
    $arParams['COMPONENT_RETURNS_DATA'] = 'Y';
    $arParams['NIGHT'] = $arParams['NIGHT'] == 'Y' ? 'Y' : 'N';
    $arParams['TYPE'] = $arParams['TYPE'] == 'telemarketing' ? 'telemarketing' : '';
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
        $this->prepareDeals();
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
    //TODO
    if($this->type == 'telemarketing'){
      $arFilter['PROPERTY_TYPE'] = '102';
    }else{
      $arFilter['PROPERTY_TYPE'] = '100';
    }
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
  
  private function prepareDealPhones(){
    $contact_ids = array();
    foreach($this->arResult['DEALS'] as &$arDeal){
      $arDeal['CONTACT_PHONE'] = '';
      $contact_ids[] = $arDeal['CONTACT_ID'];
    }
    unset($arDeal);
    
    if(!$contact_ids){
      return;
    }
    
    $arPhones = array();
    $arOrder = array("ID" => "ASC");
    $arFilter = array(
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $contact_ids,
      'TYPE_ID' => 'PHONE',
    );
    $dbResult = CCrmFieldMulti::GetList($arOrder, $arFilter);
    while($arRow = $dbResult->Fetch()){
      if(!isset($arPhones[$arRow['ELEMENT_ID']])){
        $arPhones[$arRow['ELEMENT_ID']] = $arRow['VALUE'];
      }
    }
    
    foreach($this->arResult['DEALS'] as &$arDeal){
      if(isset($arPhones[$arDeal['CONTACT_ID']])){
        $arDeal['CONTACT_PHONE'] = $arPhones[$arDeal['CONTACT_ID']];
      }
    }
    unset($arDeal);
  }
  
  private function prepareDealCities(){
    $city_ids = array();
    foreach($this->arResult['DEALS'] as &$arDeal){
      $arDeal['CITY'] = '';
      if($arDeal['UF_CRM_CITY']){
        $city_ids[$arDeal['UF_CRM_CITY']] = $arDeal['UF_CRM_CITY'];
      }
    }
    unset($arDeal);
    
    if(!$city_ids){
      return;
    }
    
    $arCities = array();
    $arFilter = array(
      'IBLOCK_ID' => CITIES_IBLOCK_ID,
      'ID' => $city_ids,
    );
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbCities = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while($arCity = $dbCities->GetNext()){
      $arCities[$arCity['ID']] = $arCity;
    }
    
    foreach($this->arResult['DEALS'] as &$arDeal){
      if($arDeal['UF_CRM_CITY'] && isset($arCities[$arDeal['UF_CRM_CITY']])){
        $arDeal['CITY'] = $arCities[$arDeal['UF_CRM_CITY']]['NAME'];
      }
    }
    unset($arDeal);
  }
  
  private function prepareDealContacts(){
    $contact_ids = array();
    foreach($this->arResult['DEALS'] as $arDeal){
      $contact_ids[] = $arDeal['CONTACT_ID'];
    }
    
    $arContacts = [];
    $arFilter = array(
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $contact_ids,
    );
    $arSelect = array_merge(array_keys($this->crm_contact_def_fields), array(
      'FULL_NAME',
      'UF_*',
    ));
    $dbContacts = CCrmContact::GetList(array(), $arFilter, $arSelect);
    while($arContact = $dbContacts->Fetch()) {
      $arContacts[$arContact['ID']] = $arContact;
    }
    
    foreach($this->arResult['DEALS'] as &$arDeal){
      if(isset($arContacts[$arDeal['CONTACT_ID']])){
        $arDeal['CONTACT'] = $arContacts[$arDeal['CONTACT_ID']];
      }
    }
    unset($arDeal);
  }
  
  private function prepareUserFieldValue($_user_field, $_val){
    if(!is_array($_val)){
      $_val = array($_val);
    }
    
    $arEnums = array();
    if($_user_field['USER_TYPE_ID'] == 'enumeration'){
      $obEnum = new CUserFieldEnum;
      $dbEnums = $obEnum->GetList(array(), array("USER_FIELD_ID" => $_user_field['ID']));
      while($arEnum = $dbEnums->GetNext()){
        $arEnums[$arEnum['ID']] = $arEnum['VALUE'];
      }
      $_user_field["FIELDS"] = $arEnums;
    }
    
    foreach($_val as $key => $res){
      switch($_user_field['USER_TYPE_ID']){
        case 'boolean':
          $res = $res ? 'Да' : 'Нет';
          break;
        case 'double':
          if(strlen($res)>0){
            $res = round(doubleval($res), $_user_field["SETTINGS"]["PRECISION"]);
          }
          break;
        case 'integer':
          $res = intval($res);
          break;
        case 'enumeration':
          $res = strlen($arEnums[$res]) > 0 ? $arEnums[$res] : htmlspecialcharsbx($res);
          break;
        case 'iblock_element':
          if($res > 0){
            $el = \CIBlockElement::GetByID($res)->GetNext();
            if($el){
              $res = $el['NAME'];
            }
          }
          break;
        default:
          if(is_string($res)){
            $res = htmlspecialcharsbx($res);
          }
          break;
      }
      $_val[$key] = $res;
    }

    return implode(', ', $_val);
  }
  
  private function prepareDealDisplayFields(){
    $contact_names = array();
    $contact_ufields = array();
    foreach($this->crm_contact_def_fields as $key => $val){
      $contact_names[$key] = $val['NAME'];
    }
    $arOrder = array('NAME' => 'ASC');
    $arFilter = array('ENTITY_ID' => 'CRM_CONTACT', 'LANG' => 'ru');
    $dbUserFields = CUserTypeEntity::GetList($arOrder, $arFilter);
    while($arUserField = $dbUserFields->Fetch()){
      $contact_names[$arUserField['FIELD_NAME']] = $arUserField['LIST_COLUMN_LABEL'];
      $contact_ufields[$arUserField['FIELD_NAME']] = $arUserField;
    }
    
    $deal_names = array();
    $deal_ufields = array();
    foreach($this->crm_deal_def_fields as $key => $val){
      $deal_names[$key] = $val['NAME'];
    }
    $arOrder = array('NAME' => 'ASC');
    $arFilter = array('ENTITY_ID' => 'CRM_DEAL', 'LANG' => 'ru');
    $dbUserFields = CUserTypeEntity::GetList($arOrder, $arFilter);
    while($arUserField = $dbUserFields->Fetch()){
      $deal_names[$arUserField['FIELD_NAME']] = $arUserField['LIST_COLUMN_LABEL'];
      $deal_ufields[$arUserField['FIELD_NAME']] = $arUserField;
    }

    foreach($this->arResult['DEALS'] as &$arDeal){
      $group_id = $arDeal['QUEUE_GROUP_ID'];
      $arDeal['DISPLAY_FIELDS'] = array();
      $arDeal['DISPLAY_FIELDS']['CONTACT'] = array();
      $arDeal['DISPLAY_FIELDS']['DEAL'] = array();
      $config = $this->arResult['QUEUE_GROUPS'][$group_id]['CONFIG'];
      
      foreach($config['contact'] as $code){
        if(array_key_exists($code, $arDeal['CONTACT'])){
          $val = '';
          if(!empty($arDeal['CONTACT'][$code])){
            $val = $arDeal['CONTACT'][$code];
            if(isset($contact_ufields[$code])){
              $val = $this->prepareUserFieldValue($contact_ufields[$code], $val);
            }
            if($code == 'BIRTHDATE'){
              $date = DateTime::createFromFormat('d.m.Y H:i:s', $val);
              if($date){
                $now = new DateTime("now");
                $interval = $now->diff($date);
                $val = $date->format('d.m.Y') .' ('. $interval->format('%y') .')';
              }
            }
          }
          $arDeal['DISPLAY_FIELDS']['CONTACT'][] = array(
            'NAME' => $contact_names[$code],
            'VALUE' => $val,
          );
        }
      }
      
      foreach($config['deal'] as $code){
        if(array_key_exists($code, $arDeal)){
          $val = '';
          if(!empty($arDeal[$code])){
            $val = $arDeal[$code];
            if(isset($deal_ufields[$code])){
              $val = $this->prepareUserFieldValue($deal_ufields[$code], $val);
            }
          }
          $arDeal['DISPLAY_FIELDS']['DEAL'][] = array(
            'NAME' => $deal_names[$code],
            'VALUE' => $val,
          );
        }
      }
    }
    unset($arDeal);
  }
  
  private function prepareDeals(){
    $this->arResult['DEALS'] = array();
    if(!count($this->queue_group_ids)){
      return;
    }
    
    $now = BDateTime::createFromPhp(new DateTime());
    $unlocked_time = BDateTime::createFromPhp(new DateTime());
    $unlocked_time->add('-T'. $this->locked_minutes .'M');
    
    $arFilter = array(
      'CHECK_PERMISSIONS' => 'N',
      'STAGE_ID' => QUEUE_DEAL_STAGE_NEW,
      '=IS_RECURRING' => 'N',
      'UF_CRM_QUEUE_GROUP' => $this->queue_group_ids,
      '<=UF_CRM_PLANNED_CALL' => $now,
      array(
        "LOGIC" => "OR",
        array("UF_CRM_LOCKED_BY" => $this->user_id),
        array("UF_CRM_LOCKED_FROM" => false),
        array("<=UF_CRM_LOCKED_FROM" => $unlocked_time),
      ),
    );
    
    $arSelect = array_merge(array_keys($this->crm_deal_def_fields), array(
      'CONTACT_ID',
      'UF_*',
    ));
    
    $navListOptions = array(
      'QUERY_OPTIONS' => array(
        'LIMIT' => 200,
        'OFFSET' => 0,
      ),
    );
    
    $dbDeals = CCrmDeal::GetListEx(array('UF_CRM_PLANNED_CALL' => 'ASC', 'ID' => 'ASC'), $arFilter, false, false, $arSelect, $navListOptions);
    while($arDeal = $dbDeals->GetNext()){
      $arDeal['COLOR'] = '#33991a';
      $arDeal['EXPIRED'] = 'N';
      $group_id = $arDeal['UF_CRM_QUEUE_GROUP'];
      $arDeal['QUEUE_GROUP_ID'] = $group_id;
      $arDeal['COLOR'] = $this->arResult['QUEUE_GROUPS'][$group_id]['COLOR'];
      $this->arResult['QUEUE_GROUPS'][$group_id]['TOTAL_DEALS']++;
      $plannedCallDateTime = $arDeal['DATE_CREATE'];
      if(strlen($arDeal['UF_CRM_PLANNED_CALL'])){
        $plannedCallDateTime = $arDeal['UF_CRM_PLANNED_CALL'];
      }
      $plannedDatetime = new DateTime();
      $currentTimeStamp = $plannedDatetime->getTimestamp();
      $timestamp = strtotime($plannedCallDateTime);
      if($timestamp !== false){
        $plannedDatetime->setTimestamp($timestamp);
      }
      $arDeal['PLANNED_CALL'] = $plannedDatetime;
      
      $expiredTimeInSeconds = $this->arResult['QUEUE_GROUPS'][$group_id]['CALL_EXPIRED_TIME'];
      $plannedTimestamp = $plannedDatetime->getTimestamp();
      if($currentTimeStamp > $plannedTimestamp){
        $arDeal['PLANNED_CALL_EXPIRED'] = ($currentTimeStamp - $plannedTimestamp) > $expiredTimeInSeconds;
      }
      
      if($arDeal['PLANNED_CALL_EXPIRED']){
        $this->arResult['QUEUE_GROUPS'][$group_id]['TOTAL_DEALS_EXPIRED']++;
      }
      $this->arResult['DEALS'][$arDeal['ID']] = $arDeal;
    }
    
    $this->prepareDealContacts();
    $this->prepareDealPhones();
    $this->prepareDealCities();
    $this->prepareDealDisplayFields();
  }

  private function prepareResponse(){
    $groups = array();
    
    foreach($this->arResult['QUEUE_GROUPS'] as $arGroup){
      $group = array();
      $group['id'] = $arGroup['ID'];
      $group['name'] = ($arGroup['PROPERTIES']['TYPE']['VALUE'] == 'Телемаркетинг' ? '<b>ТЕЛЕМАРКЕТИНГ</b> ' : '')  . $arGroup['PROPERTIES']['USER_NAME']['VALUE'];
      $group['color'] = $arGroup['COLOR'];
      $group['total_deals'] = $arGroup['TOTAL_DEALS'];
      $group['total_deals_expired'] = $arGroup['TOTAL_DEALS_EXPIRED'];
      $group['items'] = array();
      $groups[$group['id']] = $group;
    }
    
    foreach($this->arResult['DEALS'] as $arDeal){
      $deal = array();
      $deal['id'] = $arDeal['ID'];
      $deal['group_id'] = $arDeal['QUEUE_GROUP_ID'];
      $deal['date_create'] = $arDeal['DATE_CREATE'];
      $deal['contact_phone'] = $arDeal['CONTACT_PHONE'];
      $deal['contact_name'] = $arDeal['CONTACT']['FULL_NAME'];
      $deal['city'] = strlen($arDeal['CITY']) ? $arDeal['CITY'] : 'отсутствует';
      $deal['color'] = $arDeal['COLOR'];
      $deal['planned_call_expired'] = $arDeal['PLANNED_CALL_EXPIRED'];
      $deal['planned_call'] = $arDeal['PLANNED_CALL']->format('Y-m-d H:i:s');
      $deal['display_fields'] = array();
      $deal['display_fields']['contact'] = array();
      $deal['display_fields']['deal'] = array();
      foreach($arDeal['DISPLAY_FIELDS']['CONTACT'] as $arField){
        $deal['display_fields']['contact'][] = array(
          'name' => $arField['NAME'],
          'value' => $arField['VALUE'],
        );
      }
      foreach($arDeal['DISPLAY_FIELDS']['DEAL'] as $arField){
        $deal['display_fields']['deal'][] = array(
          'name' => $arField['NAME'],
          'value' => $arField['VALUE'],
        );
      }
      if(isset($groups[$deal['group_id']])){
        $groups[$deal['group_id']]['items'][] = $deal;
      }
    }
    
    $arr_groups = array();
    foreach($groups as $group){
      $arr_groups[] = $group;
    }
    
    return $arr_groups;
  }
}