<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock;
use Bitrix\Main\Type\DateTime as BDateTime;
use Whatasoft\IBlock\Fields\Manager;
use Whatasoft\Cache\CacheParams;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;


class RequestsTelemarketingEditComponent extends CBitrixComponent{
  private static $LANG_PREFIX = "WAS_REQUESTS_EDIT_";
  
  public function onPrepareComponentParams($arParams){
    $arParams['COMPONENT_RETURNS_DATA'] = 'Y';
    
    return $arParams;
  }

  public function executeComponent() {
    global $APPLICATION, $USER;
    
    Loader::IncludeModule('iblock');
    Loader::includeModule("highloadblock");
    Loader::IncludeModule('crm');
    
    $this->arResult = array();
    $this->useLogging = true;
    $this->user_id = $USER->GetID();
    $this->user_is_admin = $USER->IsAdmin();
    $this->locked_minutes = QUEUE_DEAL_LOCKED_MINUTES;
    $this->field_manager = new Manager();
    $this->crm_contact_def_fields = $this->field_manager->getCrmContactFields();
    $this->crm_deal_def_fields = $this->field_manager->getCrmDealFields();
    
    $this->contact_special_user_fields = $this->field_manager->getCrmContactExcludeFields();
    $this->deal_special_user_fields = $this->field_manager->getCrmDealExcludeFields();
    $this->campaiganQueueId = null;
    $this->callQueueStorage = new \Whatasoft\Campaign\CampaignCallQueue();
    $this->callQueueApi = new \Whatasoft\Asterisk\Queue(true);
    $this->callQueueEventHandler = new \Whatasoft\Campaign\CallQueueEventHandler();
    $this->crmFieldManager = new \Whatasoft\Statistic\Helpers\CrmFieldManager();
    $this->httpRequest = new \Whatasoft\Helpers\Http\Request();
    $this->crmUtils = new \Whatasoft\Helpers\Crm\Utils();

    if($this->arParams["WAS_FROM_AJAX"] == "Y"){
      return $this->processRequest();
    }
    
    $this->defaultTemplate();
  }
  
  private function prepareAjax(){
    \CJSCore::Init(array("fx", "jquery", "ajax", "ui"));
    $cache_id = CacheParams::SetCache($this->getName(), $this->getTemplateName(), $this->arParams);
    $this->arResult["AJAX_CACHE_ID"] = $cache_id;
  }
  
  private function defaultTemplate(){
    $this->prepareAjax();
    $this->includeComponentTemplate();
  }
  
  private function processRequest(){
    global $USER_FIELD_MANAGER;
    $result = array();
    $result['success'] = false;
    $result['message'] = '';
    
    if(!isset($_POST['action'])){
      $result['message'] = 'Неверный запрос';
      return $result;
    }
    
    $action = trim($_POST['action']);
    
    switch($action) {
      case 'call_transfer_accompaniment':
        // Перевод звонка с сопровождением
        $handler = new \Whatasoft\Asterisk\CallTransfer();
        $user_id = trim($this->httpRequest->get('USER_ID', ''));
        $phone = trim($this->httpRequest->get('USER_PHONE', ''));
        $user_id = ($user_id) ? $user_id : false; 
        $user = CUser::GetByID($user_id)->Fetch();
        
        if (!$user_id && !$phone) {
          $result['message'] = "Неверные параметры вызова";
          return $result;
        }
        
        if ($user_id && !$user) {
          $result['message'] = "Пользователь с ID: {$user_id} не найден";
          return $result;
        }
        
        if ($user) {
          $handler->callTransferWithAccompaniment($this->user_id, $user_id);
          $result['success'] = true;
          return $result;
        }
        
        if (!$phone) {
          $result['message'] = "Не указан телефон";
          return $result;
        }
        
        $result['success'] = true;
        $handler->callTransferWithAccompaniment($this->user_id, $phone);
        break;
        
      case 'call_transfer':
        // Перевод звонка без сопровождения
        $handler = new \Whatasoft\Asterisk\CallTransfer();
        $user_id = trim($this->httpRequest->get('USER_ID', ''));
        $phone = trim($this->httpRequest->get('USER_PHONE', ''));
        $user_id = ($user_id) ? $user_id : false; 
        $user = CUser::GetByID($user_id)->Fetch();
        
        if (!$user_id && !$phone) {
          $result['message'] = "Неверные параметры вызова";
          return $result;
        }
        
        if ($user_id && !$user) {
          $result['message'] = "Пользователь с ID: {$user_id} не найден";
          return $result;
        }
        
        if ($user) {
          $handler->callTransferWithoutAccompaniment($this->user_id, $user_id);
          $result['success'] = true;
          return $result;
        }
        
        if (!$phone) {
          $result['message'] = "Не указан телефон";
          return $result;
        }
        
        $result['success'] = true;
        $handler->callTransferWithoutAccompaniment($this->user_id, $phone);
        break;
        
      case 'get_deal':
        // Получение карточки по её ID
        $this->deal_id = intval($_POST['deal_id']);
        $this->prepareUserQueueGroupIds();
        $html = $this->prepareDealHtml();
        
        if (!$this->arResult['DEAL']) {
          $error = 'Запрошенная сделка не существует, либо не доступна';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $result['success'] = true;
        $result['html'] = $html;
        $result['deal_id'] = $this->deal_id;
        $result['phone'] = $this->arResult['DEAL']['CONTACT_PHONE'];
        $result['submit_interval'] = $this->arResult['GROUP']['SUBMIT_INTERVAL'];
        break;
      
      // Возвращает следующую по очереди карточку
      case 'get_next':
        $group_ids = $_POST['group_ids'];
        
        if (!is_array($group_ids)) {
          $group_ids = array();
        }
        
        $this->prepareUserQueueGroupIds();
        $this->queue_group_ids = array_intersect($this->queue_group_ids, $group_ids);

        if (!count($this->queue_group_ids)) {
          $result['message'] = 'Не выбрана ни одна очередь';
          return $result;
        }
      
        // Получение всех сделок из очереди обзвона кампаний
        $storage = new Whatasoft\Storage\HlBlockStorage(HLBLOCK_CAMPAIGIN_QUEUE_ID);
        $arOrder = ['ID' => 'ASC'];
        $arFilter = [
          'UF_QUEUE_ID' => $this->queue_group_ids,
          'UF_ACTIVE' => 'Y',
          '!UF_IS_EXEC' => 'Y',
        ];
        $arSelect = ['ID', 'UF_DEAL_ID'];
        $arQueueList = $storage->getFiltered($arOrder, $arFilter, $arSelect)->fetchAll();
        // Выбираем все идентификаторы сделок
        $arQueueDealIds = array_unique(array_map(function($arRow) {
          return $arRow['UF_DEAL_ID'];
        }, $arQueueList));
        $arQueueDealIds = count($arQueueDealIds) ? $arQueueDealIds : [-1];
        // end - Получение всех сделок из очереди обзвона кампаний
      
        // Выбираем все идентификаторы доступных сделок
        // Сделка может быть недоступна, если по ней уже звонит другой оператор
        $arAvailableDeals = [];
        $now = BDateTime::createFromPhp(new DateTime());
        $unlocked_time = BDateTime::createFromPhp(new DateTime());
        $unlocked_time->add('-T'. $this->locked_minutes .'M');
        $arOrder = ['ID' => 'ASC'];
        $arFilter = array(
          'ID' => $arQueueDealIds,
          'CHECK_PERMISSIONS' => 'N',
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
        
        //$this->errorToLog('Данные фильтра', $arFilter, $action);
        
        $arSelect = array('ID');
        $dbDeals = CCrmDeal::GetListEx($arOrder, $arFilter, false, false, $arSelect);
        while ($arRow = $dbDeals->Fetch()) {
          $arAvailableDeals[] = $arRow['ID'];
        }
        // end - Выбираем все идентификаторы доступных сделок
        
        // Выбираем первую по очереди доступную сделку
        $arNextQueueRow = null;
        foreach ($arQueueList as $arQueue) {
          if (in_array($arQueue['UF_DEAL_ID'], $arAvailableDeals)) {
            $arNextQueueRow = $arQueue;
            break;
          }
        }
        
        if (empty($arNextQueueRow)) {
          $result['message'] = 'Доступных сделок нет';
          return $result;
        }

        $this->deal_id = intval($arNextQueueRow['UF_DEAL_ID']);
        $this->crmUtils->incrementDealCallAttempts($this->deal_id);
        
        $html = $this->prepareDealHtml();
        
        $result['success'] = true;
        $result['html'] = $html;
        $result['deal_id'] = $this->deal_id;
        $result['phone'] = $this->arResult['DEAL']['CONTACT_PHONE'];
        $result['submit_interval'] = $this->arResult['GROUP']['SUBMIT_INTERVAL'];
        break;
      case 'update_lock':
        $this->deal_id = intval($_POST['deal_id']);
        $arFilter = array(
          'CHECK_PERMISSIONS' => 'N',
          'ID' => $this->deal_id,
          'UF_CRM_LOCKED_BY' => $this->user_id,
        );
        
        $arSelect = array(
          'ID',
        );
        
        $dbDeals = CCrmDeal::GetListEx(array('ID' => 'ASC'), $arFilter, false, false, $arSelect);
        $arDeal = $dbDeals->GetNext();
        if(!$arDeal){
        // $result['message'] = 'Неверный идентификатор задачи';
      // return $result;
        }
        
        $now = BDateTime::createFromPhp(new DateTime());
        $arFields = array(
          'UF_CRM_LOCKED_BY' => $this->user_id,
          'UF_CRM_LOCKED_FROM' => $now,
        );
        $crmDeal = new CCrmDeal(false);
        $res = $crmDeal->Update($arDeal['ID'], $arFields);
        if (!$res) {
          //error
        }
        
        $result['success'] = true;
        break;
      case 'redial':
        $this->deal_id = intval($_POST['deal_id']);

        if (is_array($_POST['deal_id'])) {
          $this->deal_id = intval(array_shift($_POST['deal_id']));
        }

        $this->callLog('redial');
        
        $arFilter = array(
          'CHECK_PERMISSIONS' => 'N',
          'ID' => $this->deal_id,
          'UF_CRM_LOCKED_BY' => $this->user_id,
        );
        
        $arSelect = array(
          'ID',
          'UF_CRM_QUEUE_GROUP',
          'UF_CRM_CALL_RETRIES',
        );
        
        $dbDeals = CCrmDeal::GetListEx(array('ID' => 'ASC'), $arFilter, false, false, $arSelect);
        $arDeal = $dbDeals->GetNext();
        
        if (!$arDeal) {
      //     $result['message'] = 'Неверный идентификатор задачи';
      // return $result;
        }

        $arDealCallQueue = $this->callQueueStorage->getActiveCallQueueForDeal($this->deal_id);
        
        if (empty($arDealCallQueue)) {
          $error = 'Не найдена очередь обзвона для сделки';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        // Устанавливаем статус "Недозвонились" в очереди кампании
        try {
          $this->handleUnsuccessfulCall($arDealCallQueue['ID']);
        } catch (\Exception $exception) {
          $result['message'] = $exception->getMessage();
          return $result;
        }
        
        $result['success'] = true;
        $this->saveCallReport();
        break;
      case 'hangup':
        $this->callLog('hangup');
        $this->requestHangup();
        
        $result['success'] = true;
        break;
      case 'update_deal_category':
        $dealId = intval($_POST['deal_id']);
        $categoryId = $_POST['category_id'];
        $arCategory = $this->getCategoryById($categoryId, ["ID"]);
        $arDeal = $this->getDealById($dealId, ['ID']);
        
        if (!$arCategory) {
          $error = 'Запрошенная категория не найдена';
          $errorInfo = ['CATEGOTY_ID' => $categoryId, 'DEAL_ID' => $dealId];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        if (!$arDeal) {
          $error = 'Запрошенная сделка не существует, либо не доступна';
          $errorInfo = ['DEAL_ID' => $dealId];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        try {
          $this->updateDealCategory($dealId, $categoryId);
        } catch(Exception $exception) {
          $result['message'] = $exception->getMessage();
          return $result;
        }
      
        $this->deal_id = $dealId;
        $this->arResult['DEAL'] = $this->getDealById($dealId);
                
        if (!$this->arResult['DEAL']) {
          $error = 'Запрошенная сделка не существует, либо не доступна';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $arDealCallQueue = $this->setDealCallQueue($dealId)->getDealCallQueue();
        
        if (empty($arDealCallQueue)) {
          $error = 'Сделка не находится в активной очереди обзвона кампании';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $this->setDealAdditionalFields();

        $result['success'] = true;
        $result['html'] = $this->getDealHtml();
        break;
      case 'send_sms':
        $phone = $_POST['to'] ?? '';
        $smsTemplateId = $_POST['template_id'] ?? false;
        try {
          $this->sendSms($phone, $smsTemplateId);
        } catch(Exception $exception) {
          $result['message'] = $exception->getMessage();
          return $result;
        }
        $result['success'] = true;
        break;
      case 'send_email':
        $email = $_POST['to'] ?? '';
        $templateId = $_POST['template_id'] ?? false;
        try {
          $this->sendEmail($email, $templateId);
        } catch(Exception $exception) {
          $result['message'] = $exception->getMessage();
          return $result;
        }
        $result['success'] = true;
        break;
      case 'save_deal':
        $this->deal_id = intval($_POST['DEAL_ID']);
        $this->setDealCallQueue($this->deal_id);
        $this->requestHangup();

        $this->callLog('save_deal');
        
        $arFilter = array(
          'CHECK_PERMISSIONS' => 'N',
          'ID' => $this->deal_id,
          '=IS_RECURRING' => 'N',
          'UF_CRM_LOCKED_BY' => $this->user_id,
        );
        
        $arSelect = array_merge(array_keys($this->crm_deal_def_fields), array(
          'CONTACT_ID',
          'TYPE_ID',
          'CATEGORY_ID',
          'UF_*',
        ));
        
        $dbDeals = CCrmDeal::GetListEx(array('ID' => 'ASC'), $arFilter, false, false, $arSelect);
        $arDeal = $dbDeals->GetNext();
        
        if(!$arDeal){
          $error = 'Запрошенная сделка не существует, либо не доступна';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $arDealCallQueue = $this->getDealCallQueue();
        
        if (empty($arDealCallQueue)) {
          $error = 'Сделка отсутствует в активной очереди обзвона кампаний';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $queueGroupId = $arDealCallQueue['UF_QUEUE_ID']
          ? $arDealCallQueue['UF_QUEUE_ID']
          : $arDeal['UF_CRM_QUEUE_GROUP'];
        
        $arFilter = array(
          'ID' => $queueGroupId,
          'IBLOCK_ID' => IBLOCK_QUEUE_ID,
        );
        $arSelect = array(
          'ID',
          'IBLOCK_ID',
          'NAME',
        );
        
        $dbQueueGroups = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
        $obQueueGroup = $dbQueueGroups->GetNextElement();
        
        if (!$obQueueGroup) {
          $error = 'Не найдена группа сделки';
          $errorInfo = ['DEAL_ID' => $this->deal_id];
          $this->errorToLog($error, $errorInfo, $action);
          $result['message'] = $error;
          return $result;
        }
        
        $arQueueGroup = $obQueueGroup->GetFields();
        $arQueueGroup['PROPERTIES'] = $obQueueGroup->GetProperties();
        
        $arQueueGroup['CONFIGS'] = array(
          'EDIT' => array(
            'contact' => array(),
            'deal' => array(),
          ),
        );
        $config_id = intval($arQueueGroup['PROPERTIES']['VIEW_CONFIG_ID']['VALUE']);
        if($config_id){
          $arFilter = array(
            'ID' => $config_id,
          );
          $arSelect = array(
            'ID',
            'IBLOCK_ID',
            'NAME',
          );
          $dbConfigs = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
          if($obConfig = $dbConfigs->GetNextElement()){
            $arConfig = $obConfig->GetFields();
            $arConfig['PROPERTIES'] = $obConfig->GetProperties();
            
            $config_edit = $arConfig['PROPERTIES']['DETAIL_EDIT_FIELDS']['VALUE'];
            
            if(is_array($config_edit) && isset($config_edit['contact'])){
              $arQueueGroup['CONFIGS']['EDIT'] = $config_edit;
            }
          }
        }
        
        $settings_edit_fields = array(
          'CONTACT' => array_keys($arQueueGroup['CONFIGS']['EDIT']['contact']),
          'DEAL' => array_keys($arQueueGroup['CONFIGS']['EDIT']['deal']),
        );
        $settings_edit_fields['DEAL'] = array_diff($settings_edit_fields['DEAL'], $this->deal_special_user_fields);
        $settings_edit_fields['CONTACT'] = array_diff($settings_edit_fields['CONTACT'], $this->contact_special_user_fields);
        
        $edit_fields = array(
          'CONTACT' => array(),
          'DEAL' => array(),
        );

        $deal_user_fields = $USER_FIELD_MANAGER->GetUserFields('CRM_DEAL', $arDeal['ID'], 'ru');
        foreach($deal_user_fields as $key => $arUserField){
          if(in_array($arUserField['FIELD_NAME'], $settings_edit_fields['DEAL'])){
            $edit_fields['DEAL'][] = $arUserField['FIELD_NAME'];
          }
        }
        foreach($settings_edit_fields['DEAL'] as $code){
          if(array_key_exists($code, $this->crm_deal_def_fields)){
            $edit_fields['DEAL'][] = $code;
          }
        }
        
        $contact_user_fields = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $arDeal['CONTACT_ID'], 'ru');
        foreach($contact_user_fields as $key => $arUserField){
          if(in_array($arUserField['FIELD_NAME'], $settings_edit_fields['CONTACT'])){
            $edit_fields['CONTACT'][] = $arUserField['FIELD_NAME'];
          }
        }
        foreach($settings_edit_fields['CONTACT'] as $code){
          if(array_key_exists($code, $this->crm_contact_def_fields)){
            $edit_fields['CONTACT'][] = $code;
          }
        }
        
        $arDealFields = array();
        foreach($edit_fields['DEAL'] as $code){
          if(isset($_POST['deal'][$code])){
            $arDealFields[$code] = $_POST['deal'][$code];
          }
        }
        
        $arContactFields = array();
        foreach($edit_fields['CONTACT'] as $code){
          if(isset($_POST['contact'][$code])){
            $arContactFields[$code] = $_POST['contact'][$code];
          }
        }
        
        $submit_types = array('default', 'appointment', 'call_back', 'decline');
        $type = $_POST['TYPE'];
        $deal_category_id = $arDeal['CATEGORY_ID'];
        
        if(!in_array($type, $submit_types)){
          $type = 'default';
        }
        
        switch($type){
          case 'default':
            $arDealFields['STAGE_ID'] = QUEUE_DEAL_STAGE_DEFAULT;
            break;
          case 'appointment':
            $arStage = $this->crmFieldManager->getDealCategoryStageByName($deal_category_id, QUEUE_DEAL_STAGE_APPOINTMENT_NAME);
            $arStage = $arStage ?? [];
            $stageId = $arStage['STATUS_ID'] ?? QUEUE_DEAL_STAGE_APPOINTMENT;
          
            $office_id = intval($_POST['UF_CRM_1615633559']);
            $date = trim($_POST['MEET_DATE']);
            $time = intval($_POST['MEET_TIME']);
            $time = min($time, 18);
            $time = max($time, 9);
            
            $obj_date = DateTime::createFromFormat('d.m.Y', $date, new \DateTimeZone('+0500'));

            if(!$obj_date){
              $result['message'] = 'Неверный формат даты встречи';
              return $result;
            }

            $obj_date->setTime($time, 0, 0);
            $bdate = BDateTime::createFromPhp($obj_date);
            
            $arDealFields['UF_CRM_MEET_DATE'] = $bdate;
            $arDealFields['UF_CRM_1615633559'] = $office_id;
            $arDealFields['STAGE_ID'] = $stageId;
            break;
          case 'call_back':
            $date = trim($_POST['CALL_BACK_DATE']);
            $time = intval($_POST['CALL_BACK_TIME']);
            $time = min($time, 18);
            $time = max($time, 9);
            
            $obj_date = DateTime::createFromFormat('d.m.Y', $date, new \DateTimeZone('+0500'));
            if(!$obj_date){
              $result['message'] = 'Неверный формат даты встречи';
              return $result;
            }
            //$obj_date->setTimeZone(new \DateTimeZone('+0500'));
            $obj_date->setTime($time, 0, 0);
            $bdate = BDateTime::createFromPhp($obj_date);
            
            $arDealFields['UF_CRM_PLANNED_CALL'] = $bdate;
            break;
          case 'decline':
            $arStage = $this->crmFieldManager->getDealCategoryStageByName($deal_category_id, QUEUE_DEAL_STAGE_DECLINE_NAME);
            $arStage = $arStage ?? [];
            $stageId = $arStage['STATUS_ID'] ?? QUEUE_DEAL_STAGE_DECLINE;
          
            $decline = trim($_POST['UF_CRM_DECLINE']);
            $arDealFields['UF_CRM_DECLINE'] = $decline;
            $arDealFields['STAGE_ID'] = $stageId;
            break;
        }        
        
        // Устанавливаем статус дозвона для сделки
        if (!$this->user_is_admin) 
        {
          try {
            if ($type === 'call_back') {
              $this->handleRecall($arDealCallQueue);
            } else {
              $this->handleCallResult($arDealCallQueue);
            }
          } catch (\Exception $exception) {
            
          }
        }  
        
        $arDealFields['UF_CRM_STATUS'] = trim($_POST['UF_CRM_STATUS']);
        if(isset($_POST['COMMENTS'])){
          $arDealFields['COMMENTS'] = trim($_POST['COMMENTS']);
        }
        $arDealFields['UF_CRM_LOCKED_BY'] = null;
        $arDealFields['UF_CRM_LOCKED_FROM'] = null;
        
        
        $contactSaveSuccess = true;
        $dealSaveSuccess = true;
        $result['message'] = '';
        
        $crmContact = new CCrmContact(false);
        $crmDeal = new CCrmDeal(false);
        
        if (count($arContactFields)) {
          $contactSaveSuccess = $crmContact->Update($arDeal['CONTACT_ID'], $arContactFields);
          if (!$contactSaveSuccess) {
            $result['message'] .= $crmContact->LAST_ERROR . ' ';
          }
        }
        
        if (count($arDealFields)) {
          $crmDeal = new CCrmDeal(false);
          $dealSaveSuccess = $crmDeal->Update($arDeal['ID'], $arDealFields);
          if (!$dealSaveSuccess) {
            $result['message'] .= $crmDeal->LAST_ERROR;
          }
        }
        
        $this->saveCallReport();
        
        $result['success'] = ($contactSaveSuccess && $dealSaveSuccess);
        break;
    }
    
    return $result;
  }
  
  private function handleUnsuccessfulCall($callQueueId)
  {
    $setAsExecuted = 'N';
    $this->callQueueEventHandler->handleUnsuccessfulCall($callQueueId, $setAsExecuted);
  }
  
  private function handleRecall(array $arDealCallQueue)
  {
    $this->callQueueEventHandler->handleRecall($arDealCallQueue['ID']);
  }
  
  private function handleCallResult(array $arDealCallQueue)
  {
    if (true || $arDealCallQueue['UF_SUCCESS_CALL'] === 'Y') {
      $this->callQueueEventHandler->handleSuccessfulCall($arDealCallQueue['ID']);
    } else {
      $setAsExecuted = 'Y';
      $this->callQueueEventHandler->handleUnsuccessfulCall($arDealCallQueue['ID'], $setAsExecuted);
    }
  }
  
  private function saveCallReport()
  {
    try {
      $reportTypeCode = \Whatasoft\Statistic\CallReport::REPORT_TYPE_TELEMARKETING;
      $report = (new \Whatasoft\Statistic\CallReport($this->deal_id, $this->user_id, $reportTypeCode))->getReport();
      $storage = new \Whatasoft\Statistic\CallReportStorage();
      $storage->saveReport($report);
    } catch(\Exception $ex) {
      
    }
  }
  
  private function getOperatorId()
  {
    $obUser = new CUser();
    $arUser = $obUser->GetByID($this->user_id)->Fetch();
    return $arUser['UF_PHONE_INNER'] ?? false;
  }
  
  private function getDealFromCallQueueByPhone($phone)
  {
    $arSelect = ['UF_DEAL_ID'];
    $arResult = $this->callQueueStorage->getActiveCallQueueByPhone($phone, $arSelect);
    return is_array($arResult) 
      ? $arResult['UF_DEAL_ID'] ?? false
      : false;
  }
  
  private function getAsteriskQueueIdFromQueue($queueId)
  {
    $queueStorage = new \Whatasoft\Storage\IBlockElementStorage(IBLOCK_QUEUE_ID);
    $asteriskQueueStorage = new \Whatasoft\Storage\IBlockElementStorage(IBLOCK_ASTERISK_ID);
    $obQueue = $queueStorage->getFiltered([], ['ID' => $queueId])->GetNextElement();
    
    if (!$obQueue) {
      throw new \Exception("Очередь с ID {$queueId} не найдена");
    }
    
    $arQueueProps = $obQueue->GetProperties();
    $id = $arQueueProps['ASTERISK_QUEUE_ID']['VALUE'] ?? false;
    $obAsteriskQueue = $asteriskQueueStorage->getFiltered([], ['ID' => $id])->GetNextElement();
    
    if (!$obAsteriskQueue) {
      throw new \Exception("Очередь Астериск с ID {$id} не найдена");
    }
    
    $arAsteriskQueueProps = $obAsteriskQueue->GetProperties();
    $asteriskQueueId = $arAsteriskQueueProps['ASTERISK_QUEUE_ID']['VALUE'] ?? false;
    
    if (!$asteriskQueueId) {
      throw new \Exception("Внешняя очередь Астериск не найдена");
    }
    
    return $asteriskQueueId;
  }
  
  private function setDealCallQueue($dealId)
  {
    $this->arResult['DEAL_CALL_QUEUE'] = $this->callQueueStorage->getActiveCallQueueForDeal($dealId);
    return $this;
  }
  
  private function getDealCallQueue()
  {
    return $this->arResult['DEAL_CALL_QUEUE'] ?? false;
  }
  
  private function getCategoryById($categoryId, $arSelect = ["*"]){
    $arFilter = [
      'ID' => $categoryId,
      'IBLOCK_ID' => CATEGORIES_IBLOCK_ID,
    ];
    
    return CIBlockElement::GetList([], $arFilter, false, false, $arSelect)->Fetch();
  }
  
  private function updateDealCategory($dealId, $categoryId)
  {
    \Bitrix\Main\Loader::includeModule("iblock");
    \Bitrix\Main\Loader::includeModule("calendar");

    $needChangeQueue = true;
    $deal = CCrmDeal::GetList([], ['ID' => $dealId, 'CHECK_PERMISSIONS' => 'N']);
    if ($deal = $deal->GetNext()){
      $queueId = $deal['UF_CRM_QUEUE_GROUP'];
      $queueData = CIBlockElement::GetProperty(QUEUE_LIST_IBLOCK_ID, $queueId, [], ['CODE'=> 'CATEGORIES']);
      while($fieldsEl = $queueData->Fetch()){
        if ($fieldsEl['VALUE'] == $categoryId){
          $needChangeQueue = false;
        }
      }
    }
    
    $arUpdateFields = [
      PROP_DEAL_CATEGORIES => $categoryId,
    ];
    
    if ($needChangeQueue) {
      $arOrder = ["PROPERTY_ORDER" => "ASC"];
      $arFilter = [
        "IBLOCK_ID" => QUEUE_LIST_IBLOCK_ID,
        "PROPERTY_CATEGORIES.ID" => $categoryId,
        "PROPERTY_TYPE" => 102
      ];
      $arQueue = CIBlockElement::GetList($arOrder, $arFilter)->Fetch();
      if (!$arQueue) {
        throw new \Exception("Не найдена очередь для переданной категории");
      }
      $arUpdateFields['UF_CRM_QUEUE_GROUP'] = $arQueue['ID'];
    }
    
    $crmDeal = new CCrmDeal(false);
    $res = $crmDeal->Update($dealId, $arUpdateFields);
    
    if (!$res) {
      throw new \Exception($crmDeal->LAST_ERROR);
    }
    
    
    
  }
  
  private function requestHangup(){
    $handler = curl_init();
    $data = array(
      CURLOPT_POST => false,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_URL => ASTERISK_HANGUP_URL .'?id='. $this->user_id,
      CURLOPT_HEADER => 0,
    );
    curl_setopt_array($handler, $data);
    
    $response = curl_exec($handler);
    curl_close($handler);
  }
  
  private function prepareUserQueueGroupIds($_filter = array()){
    $this->queue_group_ids = array();
    $arFilter = array(
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
      'PROPERTY_ACTIVE' => 'Y',
      'PROPERTY_OPERATOR_IDS' => $this->user_id,
    );
    $arFilter = array_merge($arFilter, $_filter);
    if($this->user_is_admin){
      unset($arFilter['PROPERTY_OPERATOR_IDS']);
    }
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbQueueGroups = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while($arQueueGroup = $dbQueueGroups->GetNext()){
      $this->queue_group_ids[] = $arQueueGroup['ID'];
    }
  }
  
  private function getDealById($id, $arSelect = []){
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $id,
    ];
    
    $arDefaultSelect = array_merge(array_keys($this->crm_deal_def_fields), array(
      'CONTACT_ID',
      'CONTACT_FULL_NAME',
      'UF_*',
    ));
    
    $arSelectFields = (!empty($arSelect)) 
      ? $arSelect 
      : $arDefaultSelect;
    
    $arDeal = CCrmDeal::GetListEx([], $arFilter, false, false, $arSelectFields)->Fetch();
    return $arDeal;
  }
  
  private function getDealHtml(){
    if (!$this->arResult['DEAL']) {
      return false;
    }

    ob_start();
    $this->includeComponentTemplate('ajax');
    $html = ob_get_contents();
    ob_end_clean();
    
    return $html;
  }
  
  private function prepareDealHtml(){
    $this->prepareDeal();
    if(!$this->arResult['DEAL']){
      return false;
    }
    
    ob_start();
    $this->includeComponentTemplate('ajax');
    $html = ob_get_contents();
    ob_end_clean();
    
    return $html;
  }
  
  private function prepareDeal(){
    $this->arResult['DEAL'] = false;
    
    if (!$this->deal_id) {
      return;
    }
    
    $this->setDealCallQueue($this->deal_id);
    
    if (empty($this->getDealCallQueue())) {
      return;
    }
    
    $now = BDateTime::createFromPhp(new DateTime());
    $unlocked_time = BDateTime::createFromPhp(new DateTime());
    $unlocked_time->add('-T'. $this->locked_minutes .'M');
    
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $this->deal_id,
      '=IS_RECURRING' => 'N',
      array(
        'LOGIC' => 'OR',
        array('UF_CRM_LOCKED_BY' => $this->user_id),
        array('UF_CRM_LOCKED_FROM' => false),
        array('<=UF_CRM_LOCKED_FROM' => $unlocked_time),
      ),
    ];
    
    if (!$this->user_is_admin) {
      $arFilter['<=UF_CRM_PLANNED_CALL'] = $now;
    }    
    
    $arSelect = array_merge(array_keys($this->crm_deal_def_fields), array(
      'CONTACT_ID',
      'CONTACT_FULL_NAME',
      'UF_*',
    ));
    
    $dbDeals = CCrmDeal::GetListEx(array('UF_CRM_PLANNED_CALL' => 'ASC', 'ID' => 'ASC'), $arFilter, false, false, $arSelect);
    $arDeal = $dbDeals->GetNext();
    if(!$arDeal){
      return false;
    }
    
    $this->arResult['DEAL'] = $arDeal;
    
    $now = BDateTime::createFromPhp(new DateTime());
    $arFields = array(
      'UF_CRM_LOCKED_BY' => $this->user_id,
      'UF_CRM_LOCKED_FROM' => $now,
      'UF_CRM_CALL_DATE' => $now,
    );
    $crmDeal = new CCrmDeal(false);
    $res = $crmDeal->Update($arDeal['ID'], $arFields);
    if(!$res){
      //error
    }
    
    //created valentin
    $contactID = $arDeal['CONTACT_ID'];
    /*\Bitrix\Main\Loader::IncludeModule('calendar');
        $resEvents = CCalendarEvent::GetList(['limit' => 3,]);*/
    /*$arOrder = ['ID' => 'ASC'];
    $arFilter = [
      'ASSOCIATED_ENTITY_ID' => $arDeal['CONTACT_ID'],
      'ASSOCIATED_ENTITY_TYPE_ID' => CCrmOwnerType::Contact
    ];
    $arParams = [
      'order' => $arOrder,
      'filter' => $arFilter,
      'limit' => 10,
    ];*/
    //$this->arResult['EVENTS_LIST'] =  \Bitrix\Crm\Timeline\Entity\TimelineTable::getList($arParams)->fetchAll();
    $this->arResult['EVENTS_LIST'] = [];
    $this->setDealAdditionalFields();
  }

  private function setDealAdditionalFields(){
    $this->prepareDealSpecialOffer();
    $this->prepareDealContact();
    $this->prepareDealPhone();
    $this->prepareDealCity();
    $this->prepareDealGroup();
    $this->prepareDealDisplayFields();
    $this->prepareDealScript();
    $this->prepareAdditional();
    $this->prepareDealCategories();
    $this->prepareDealEmails();
    $this->prepareDealEmailTemplates();
    $this->prepareDealSmsTemplates();
    $this->prepareContactCallsEventsList();
  }
  
  private function prepareDealSpecialOffer(){
    $this->arResult['DEAL']['SPECIAL_OFFER'] = $this->arResult['DEAL']['UF_CRM_SPECIAL_OFFER'];
  }
  
  private function prepareDealContact(){
    if(!intval($this->arResult['DEAL']['CONTACT_ID'])){
      return;
    }
    
    $arFilter = array(
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $this->arResult['DEAL']['CONTACT_ID'],
    );
    $arSelect = array_merge(array_keys($this->crm_contact_def_fields), array(
      'UF_*',
    ));
    $dbContacts = CCrmContact::GetList(array(), $arFilter, $arSelect);
    if($arContact = $dbContacts->Fetch()){
      $this->arResult['DEAL']['CONTACT'] = $arContact;
      $this->prepareDealContactType();
    }
  }
  
  private function prepareDealContactType(){
    $this->arResult['DEAL']['CONTACT']['TYPE'] = '';
    
    if(!intval($this->arResult['DEAL']['CONTACT']['UF_CRM_TYPE'])){
      return;
    }
    
    $arOrder = array('NAME' => 'ASC');
    $arFilter = array(
      'ENTITY_ID' => 'CRM_CONTACT',
      'FIELD_NAME' => 'UF_CRM_TYPE',
      'LANG' => 'ru'
    );
    $dbUserFields = CUserTypeEntity::GetList($arOrder, $arFilter);
    $arUserField = $dbUserFields->Fetch();
    if(!$arUserField){
      return;
    }
    
    $arFilter = array(
      'USER_FIELD_ID' => $arUserField['ID'],
      'ID' => $this->arResult['DEAL']['CONTACT']['UF_CRM_TYPE'],
    );
    $obEnum = new CUserFieldEnum;
    $dbEnums = $obEnum->GetList(array(), $arFilter);
    $arEnum = $dbEnums->GetNext();
    if($arEnum){
      $this->arResult['DEAL']['CONTACT']['TYPE'] = $arEnum['VALUE'];
    }
  }
  
  private function prepareDealEmails(){
    if(!intval($this->arResult['DEAL']['CONTACT_ID'])){
      return;
    }
    
    $this->arResult['DEAL']['CONTACT_EMAIL'] = null;
    $this->arResult['DEAL']['EMAILS'] = array();
    
    $arOrder = array('ID' => 'ASC');
    $arFilter = array(
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $this->arResult['DEAL']['CONTACT_ID'],
      'TYPE_ID' => 'EMAIL',
    );
    $dbResult = CCrmFieldMulti::GetList($arOrder, $arFilter);
    while($arRow = $dbResult->Fetch()){
      if($this->arResult['DEAL']['CONTACT_EMAIL'] == null){
        $this->arResult['DEAL']['CONTACT_EMAIL'] = $arRow['VALUE'];
      }
      $type_name = '';
      switch($arRow['VALUE_TYPE']){
        case 'OTHER':
          $type_name = 'другой';
          break;
        case 'HOME':
          $type_name = 'частный';
          break;
        case 'WORK':
          $type_name = 'рабочий';
          break;
        case 'MAILING':
          $type_name = 'для рассылок';
          break;
      }
      $this->arResult['DEAL']['EMAILS'][] = array(
        'NAME' => $type_name,
        'VALUE' => $arRow['VALUE'],
      );
    }
  }
  
  private function prepareDealPhone(){
    
    $arDealCallQueue = $this->getDealCallQueue();
    $callQueuePhone = $arDealCallQueue['UF_PHONE'] ?? false;
    
    if(!intval($this->arResult['DEAL']['CONTACT_ID'])){
      return;
    }
    
    $this->arResult['DEAL']['CONTACT_PHONE'] = null;
    $this->arResult['DEAL']['PHONES'] = array();
    
    $arOrder = array('ID' => 'ASC');
    $arFilter = array(
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $this->arResult['DEAL']['CONTACT_ID'],
      'TYPE_ID' => 'PHONE',
    );
    $dbResult = CCrmFieldMulti::GetList($arOrder, $arFilter);
    while($arRow = $dbResult->Fetch()){
      if($this->arResult['DEAL']['CONTACT_PHONE'] == null){
        $this->arResult['DEAL']['CONTACT_PHONE'] = $arRow['VALUE'];
      }
      $type_name = '';
      switch($arRow['VALUE_TYPE']){
        case 'MOBILE':
          $type_name = 'мобильный';
          break;
        case 'HOME':
          $type_name = 'домашний';
          break;
        case 'WORK':
          $type_name = 'рабочий';
          break;
        case 'FAX':
          $type_name = 'факс';
          break;
      }
      $this->arResult['DEAL']['PHONES'][] = array(
        'NAME' => $type_name,
        'VALUE' => $arRow['VALUE'],
      );
    }
    
    if ($callQueuePhone) {
      $this->arResult['DEAL']['CONTACT_PHONE'] = $callQueuePhone;
    }
  }
  
  private function sendSms($phone, $templateId){
    $smsProvider = new \Whatasoft\Providers\BeelineSmsProvider();
    $sender = new \Whatasoft\Mail\CrmSmsSender($smsProvider);
    return $sender->send($phone, $templateId);
  }
  
  private function sendEmail($email, $templateId){
    $sender = new \Whatasoft\Mail\CrmEmailSender();
    return $sender->send($this->user_id, $email, $templateId);
  }
  
  private function prepareDealEmailTemplates(){
    $provider = new \Whatasoft\Mail\CrmMailer();
    $arSelect = ["ID", "TITLE"];
    $this->arResult['EMAIL_TEMPLATES'] = $provider->getEmailTemplates($arSelect);
  }
  
  private function prepareDealSmsTemplates(){
    $provider = new \Whatasoft\Mail\CrmMailer();
    $arSelect = ["ID", "TITLE"];
    $this->arResult['SMS_TEMPLATES'] = $provider->getSmsTemplates($arSelect);
  }
  
  private function prepareContactCallsEventsList()
  {
    $contactId = $this->arResult['DEAL']['CONTACT_ID'];
    $arCallsEventsList = [];
    $this->arResult['EVENTS_LIST_CALLS'] = [];
    $utils = new \Whatasoft\Helpers\Crm\Utils();
    
    if ($contactId) {
      $arOrder = ['CREATED' => 'DESC'];
      $arCallsEventsList = $utils->getContactCallEventsList($contactId, $arOrder);
    }
    
    $this->arResult['EVENTS_LIST_CALLS'] = $arCallsEventsList;
  }
  
  private function prepareDealCity(){
    if(!intval($this->arResult['DEAL']['UF_CRM_CITY'])){
      return;
    }
    
    $arCities = array();
    $arFilter = array(
      'IBLOCK_ID' => CITIES_IBLOCK_ID,
      'ID' => $this->arResult['DEAL']['UF_CRM_CITY'],
    );
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbCities = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    if($arCity = $dbCities->GetNext()){
      $this->arResult['DEAL']['CITY'] = $arCity['NAME'];
    }
  }
  
  private function prepareDealCategories()
  {
    $arFilter = ['IBLOCK_ID' => CATEGORIES_IBLOCK_ID];
    $arSelect = ['ID', 'NAME'];
    $dbResult = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
    $arResult = [];
    
    while($arRow = $dbResult->GetNext()) {
      $arResult[$arRow['ID']] = $arRow;
    }
    
    $this->arResult['CRM_CATEGORIES'] = $arResult;
    $this->arResult['DEAL_CATEGORY_ID'] = $this->arResult['DEAL'][PROP_DEAL_CATEGORIES] ?? null;
  }
    
  private function prepareDealGroup(){
    
    $arDealCallQueue = $this->getDealCallQueue();
    $callDealGroup = $arDealCallQueue['UF_QUEUE_ID'] ?? false;
    $groupId = ($callDealGroup) ? $callDealGroup : $this->arResult['DEAL']['UF_CRM_QUEUE_GROUP'];
    
    $this->arResult['GROUP'] = null;
    $arFilter = array(
      'ID' => $groupId,
      'IBLOCK_ID' => IBLOCK_QUEUE_ID,
    );
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbQueueGroups = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    if($obQueueGroup = $dbQueueGroups->GetNextElement()){
      $arQueueGroup = $obQueueGroup->GetFields();
      $arQueueGroup['PROPERTIES'] = $obQueueGroup->GetProperties();
      $arQueueGroup['SUBMIT_INTERVAL'] = intval($arQueueGroup['PROPERTIES']['CALL_PROCESS_TIME']['VALUE']);
      if($arQueueGroup['SUBMIT_INTERVAL'] <= 5){
        $arQueueGroup['SUBMIT_INTERVAL'] = QUEUE_DEAL_SUBMIT_INTERVAL_TELEMARKETING;
      }
      
      $arQueueGroup['CONFIGS'] = array(
        'VIEW' => array(
          'contact' => array(),
          'deal' => array(),
        ),
        'EDIT' => array(
          'contact' => array(),
          'deal' => array(),
        ),
      );
      $config_id = intval($arQueueGroup['PROPERTIES']['VIEW_CONFIG_ID']['VALUE']);
      if($config_id){
        $arFilter = array(
          'ID' => $config_id,
        );
        $arSelect = array(
          'ID',
          'IBLOCK_ID',
          'NAME',
        );
        $dbConfigs = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
        if($obConfig = $dbConfigs->GetNextElement()){
          $arConfig = $obConfig->GetFields();
          $arConfig['PROPERTIES'] = $obConfig->GetProperties();
          
          $config_view = $arConfig['PROPERTIES']['DETAIL_VIEW_FIELDS']['VALUE'];
          if(is_array($config_view) && isset($config_view['contact'])){
            $arQueueGroup['CONFIGS']['VIEW'] = $config_view;
          }
          
          $config_edit = $arConfig['PROPERTIES']['DETAIL_EDIT_FIELDS']['VALUE'];
          if(is_array($config_edit) && isset($config_edit['contact'])){
            $arQueueGroup['CONFIGS']['EDIT'] = $config_edit;
          }
        }
      }
      
      $this->arResult['GROUP'] = $arQueueGroup;
    }
  }
  
  private function prepareDealDisplayFields(){
    global $USER_FIELD_MANAGER;
    
    $this->arResult['VIEW_FIELDS'] = array();
    $this->arResult['VIEW_FIELDS']['CONTACT'] = array();
    $this->arResult['VIEW_FIELDS']['DEAL'] = array();
    $this->arResult['EDIT_FIELDS'] = array();
    $this->arResult['EDIT_FIELDS']['CONTACT'] = array();
    $this->arResult['EDIT_FIELDS']['DEAL'] = array();
    
    $this->detail_view_fields = array();
    $this->detail_edit_fields = array();
    if($this->arResult['GROUP']){
      $this->detail_view_fields = array(
        'CONTACT' => $this->arResult['GROUP']['CONFIGS']['VIEW']['contact'],
        'DEAL' => $this->arResult['GROUP']['CONFIGS']['VIEW']['deal'],
      );
      
      $this->detail_edit_fields = array(
        'CONTACT' => $this->arResult['GROUP']['CONFIGS']['EDIT']['contact'],
        'DEAL' => $this->arResult['GROUP']['CONFIGS']['EDIT']['deal'],
      );
    }
    
    $contact_names = array();
    foreach($this->crm_contact_def_fields as $key => $val){
      $contact_names[$key] = $val['NAME'];
    }
    $contact_user_fields = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $this->arResult['DEAL']['CONTACT_ID'], 'ru');
    
    foreach($this->detail_view_fields['CONTACT'] as $code){
      if(array_key_exists($code, $this->arResult['DEAL']['CONTACT'])){
        $fields = array(
          '_CODE' => $code,
          '_TYPE' => 'f',
          '_EMPTY' => empty($this->arResult['DEAL']['CONTACT'][$code]),
          'VALUE' => !empty($this->arResult['DEAL']['CONTACT'][$code]) ? $this->arResult['DEAL']['CONTACT'][$code] : '',
        );
        if(isset($contact_user_fields[$code])){
          $fields['_TYPE'] = 'uf';
          $fields = array_merge($contact_user_fields[$code], $fields);
        }else{
          $fields['NAME'] = $contact_names[$code];
        }
        if($code == 'BIRTHDATE' && !empty($this->arResult['DEAL']['CONTACT'][$code])){
          $date = DateTime::createFromFormat('d.m.Y H:i:s', $fields['VALUE']);
          if($date){
            $now = new DateTime("now");
            $interval = $now->diff($date);
            $fields['VALUE'] = $date->format('d.m.Y') .' ('. $interval->format('%y') .')';
          }
        }
        $this->arResult['VIEW_FIELDS']['CONTACT'][] = $fields;
      }
    }
    
    foreach($this->detail_edit_fields['CONTACT'] as $code => $params){
      if(array_key_exists($code, $this->arResult['DEAL']['CONTACT'])){
        $fields = array(
          '_ID' => 'contact|'. $code,
          '_CODE' => $code,
          '_TYPE' => 'f',
          'CONDITION' => isset($params['COND']) ? $params['COND'] : null,
          'CONDITION_VALUE' => isset($params['VAL']) ? $params['VAL'] : null,
        );
        if(isset($contact_user_fields[$code])){
          $fields['_TYPE'] = 'uf';
          $fields['FIELD_NAME'] = 'contact['.$contact_user_fields[$code]['FIELD_NAME'].']';
          $fields = array_merge($contact_user_fields[$code], $fields);
        }else{
          $fields['NAME'] = $contact_names[$code];
          $fields['CODE'] = 'contact['.$code.']';
          $fields['VALUE'] = !empty($this->arResult['DEAL']['CONTACT'][$code]) ? $this->arResult['DEAL']['CONTACT'][$code] : '';
        }
        $this->arResult['EDIT_FIELDS']['CONTACT'][] = $fields;
      }
    }
    
    $deal_names = array();
    foreach($this->crm_deal_def_fields as $key => $val){
      $deal_names[$key] = $val['NAME'];
    }
    $deal_user_fields = $USER_FIELD_MANAGER->GetUserFields('CRM_DEAL', $this->arResult['DEAL']['ID'], 'ru');
    
    foreach($this->detail_view_fields['DEAL'] as $code){
      if(array_key_exists($code, $this->arResult['DEAL'])){
        $fields = array(
          '_CODE' => $code,
          '_TYPE' => 'f',
          '_EMPTY' => empty($this->arResult['DEAL'][$code]),
          'VALUE' => !empty($this->arResult['DEAL'][$code]) ? $this->arResult['DEAL'][$code] : '',
        );
        if(isset($deal_user_fields[$code])){
          $fields['_TYPE'] = 'uf';
          $fields = array_merge($deal_user_fields[$code], $fields);
        }else{
          $fields['NAME'] = $deal_names[$code];
        }
        $this->arResult['VIEW_FIELDS']['DEAL'][] = $fields;
      }
    }
    
    foreach($this->detail_edit_fields['DEAL'] as $code => $params){
      if(array_key_exists($code, $this->arResult['DEAL'])){
        $fields = array(
          '_ID' => 'deal|'. $code,
          '_CODE' => $code,
          '_TYPE' => 'f',
          'CONDITION' => isset($params['COND']) ? $params['COND'] : null,
          'CONDITION_VALUE' => isset($params['VAL']) ? $params['VAL'] : null,
        );
        if(isset($deal_user_fields[$code])){
          $fields['_TYPE'] = 'uf';
          $fields['FIELD_NAME'] = 'deal['.$deal_user_fields[$code]['FIELD_NAME'].']';
          $fields = array_merge($deal_user_fields[$code], $fields);
        }else{
          $fields['NAME'] = $deal_names[$code];
          $fields['CODE'] = 'deal['.$code.']';
          $fields['VALUE'] = !empty($this->arResult['DEAL'][$code]) ? $this->arResult['DEAL'][$code] : '';
        }
        $this->arResult['EDIT_FIELDS']['DEAL'][] = $fields;
      }
    }
    
    $this->arResult['SYSTEM_UF'] = array();
    $this->arResult['SYSTEM_UF']['STATUS'] = $deal_user_fields['UF_CRM_STATUS'];
    $this->arResult['SYSTEM_UF']['DECLINE'] = $deal_user_fields['UF_CRM_DECLINE'];
    $this->arResult['SYSTEM_UF']['CRM_1615633559'] = $deal_user_fields['UF_CRM_1615633559'];
    $this->arResult['SYSTEM_UF']['MEET_DATE'] = $deal_user_fields['UF_CRM_MEET_DATE'];
  }

  private function getOrderedScriptBlockIds($fieldValue){
    if (isset($fieldValue['ord']) && isset($fieldValue['field'])){
      $ids = [];
      asort($fieldValue['ord']);
      foreach($fieldValue['ord'] as $kord=>$ord){
        if (isset($fieldValue['field'][$kord]) && $fieldValue['field'][$kord]>0){
          $ids[] = $fieldValue['field'][$kord];
        }
      }
      return $ids;
    }
    return [];
  }

  private function prepareDealScript(){
    $this->arResult['SCRIPT'] = null;
    $this->arResult['SCRIPT_PHRASES'] = array();
    $this->arResult['SCRIPT_OBJECTIONS'] = array();
    if(!$this->arResult['GROUP'] || !$this->arResult['GROUP']['PROPERTIES']['SCRIPT']['VALUE']){
      return;
    }
    
    $arFilter = array(
      'ID' => $this->arResult['GROUP']['PROPERTIES']['SCRIPT']['VALUE'],
      'IBLOCK_ID' => IBLOCK_SCRIPT_ID,
    );
    $arSelect = array(
      'ID',
      'IBLOCK_ID',
      'NAME',
    );
    $dbScripts = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    $obScript = $dbScripts->GetNextElement();
    if(!$obScript){
      return;
    }
    
    $arScript = $obScript->GetFields();
    $arScript['PROPERTIES'] = $obScript->GetProperties();
    $arScript['SECTIONS'] = array();
    $arScriptSectionIds = !empty($arScript['PROPERTIES']['SVYAZANNYE_BLOKI']['VALUE'])
      ? $arScript['PROPERTIES']['SVYAZANNYE_BLOKI']['VALUE']
      : [-1];
  $arScriptSectionIds = $this->getOrderedScriptBlockIds($arScriptSectionIds);
    $section_ids = array();
    $arFilter = array(
      'ACTIVE' => 'Y',
      'IBLOCK_ID' => IBLOCK_SCRIPT_ITEM_ID,
      'ID' => $arScriptSectionIds,
      //'UF_PARENT_ID' => $arScript['ID'],
    );
    $dbSections = CIBlockSection::GetList(array('SORT' => 'ASC'), $arFilter);
    while($arSection = $dbSections->GetNext()){
      $arSection['ITEMS'] = array();
      $arScript['SECTIONS'][$arSection['ID']] = $arSection;
      $section_ids[] = $arSection['ID'];
    }
  $orderedScriptSections = [];
  foreach($arScriptSectionIds as $scriptSectionId){
    if (isset($arScript['SECTIONS'][$scriptSectionId])){
      $orderedScriptSections[$scriptSectionId] = $arScript['SECTIONS'][$scriptSectionId];
    }
  }
  $arScript['SECTIONS'] = $orderedScriptSections;
    if(count($section_ids)){
      $arFilter = array(
        'ACTIVE' => 'Y',
        'SECTION_ID' => $section_ids,
        'IBLOCK_ID' => IBLOCK_SCRIPT_ITEM_ID,
      );
      $arSelect = array(
        'ID',
        'IBLOCK_ID',
        'IBLOCK_SECTION_ID',
        'NAME',
        'DETAIL_TEXT',
      );
      $dbScriptItems = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
      while($obScriptItem = $dbScriptItems->GetNextElement()){
        $arScriptItem = $obScriptItem->GetFields();
        if(isset($arScript['SECTIONS'][$arScriptItem['IBLOCK_SECTION_ID']])){
          $arScript['SECTIONS'][$arScriptItem['IBLOCK_SECTION_ID']]['ITEMS'][] = $arScriptItem;
        }
      }
    }

    $this->arResult['SCRIPT'] = $arScript;
    
    $phrase_ids = $arScript['PROPERTIES']['PHRASE_IDS']['VALUE'];
    if(is_array($phrase_ids) && count($phrase_ids)){
      $arFilter = array(
        'ACTIVE' => 'Y',
        'ID' => $phrase_ids,
        'IBLOCK_ID' => IBLOCK_SCRIPT_PHRASE_ID,
      );
      $arSelect = array(
        'ID',
        'IBLOCK_ID',
        'NAME',
        'DETAIL_TEXT',
      );
      $dbScriptPhrases = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
      while($obScriptPhrase = $dbScriptPhrases->GetNextElement()){
        $arScriptPhrase = $obScriptPhrase->GetFields();
        $this->arResult['SCRIPT_PHRASES'][] = $arScriptPhrase;
      }
    }
    
    $objection_ids = $arScript['PROPERTIES']['OBJECTION_IDS']['VALUE'];
    if(is_array($objection_ids) && count($objection_ids)){
      $arFilter = array(
        'ACTIVE' => 'Y',
        'ID' => $objection_ids,
        'IBLOCK_ID' => IBLOCK_SCRIPT_OBJECTION_ID,
      );
      $arSelect = array(
        'ID',
        'IBLOCK_ID',
        'NAME',
        'DETAIL_TEXT',
      );
      $dbScriptObjections = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
      while($obScriptObjection = $dbScriptObjections->GetNextElement()){
        $arScriptObjection = $obScriptObjection->GetFields();
        $this->arResult['SCRIPT_OBJECTIONS'][] = $arScriptObjection;
      }
    }
  }

  private function callLog($methodName)
  {
    $dealId = $this->deal_id ?? 'None';
    $dateTime = date('d.m.Y H:i:s', time());
    
    $data = [
      'METHOD' => $methodName,
      'DEAL_ID' => $dealId,
      'DATETIME' => $dateTime,
    ];
    
    
    dd_to_log($data, 'call_log_telemarketing.txt');
  }
  
  private function errorToLog(string $error, array $data = [], string $action = '')
  {
    if (!$this->useLogging) {
      return;
    }    
    
    $data = array_merge($data, ['ERROR' => $error, 'ACTION' => $action]);
    dd_to_log($data, 'telemarketing_log.txt');
  }
  
  private function prepareAdditional(){
    $this->arResult['TIME_LIST'] = array(
      array(
        'NAME' => 'В первой половине дня',
        'VALUE' => 9,
      ),
      array(
        'NAME' => 'Во второй половине дня',
        'VALUE' => 14,
      ),
      array(
        'NAME' => '9 - 10',
        'VALUE' => 9,
      ),
      array(
        'NAME' => '10 - 11',
        'VALUE' => 10,
      ),
      array(
        'NAME' => '11 - 12',
        'VALUE' => 11,
      ),
      array(
        'NAME' => '12 - 13',
        'VALUE' => 12,
      ),
      array(
        'NAME' => '13 - 14',
        'VALUE' => 13,
      ),
      array(
        'NAME' => '14 - 15',
        'VALUE' => 14,
      ),
      array(
        'NAME' => '15 - 16',
        'VALUE' => 15,
      ),
      array(
        'NAME' => '16 - 17',
        'VALUE' => 16,
      ),
      array(
        'NAME' => '17 - 18',
        'VALUE' => 17,
      ),
    );
    
    $now = new DateTime;
    $tomorrow = new DateTime;
    $tomorrow->add(new DateInterval('P1D'));
    $this->arResult['TODAY'] = $now->format('d.m.Y');
    $this->arResult['TOMORROW'] = $tomorrow->format('d.m.Y');
  }
  
}