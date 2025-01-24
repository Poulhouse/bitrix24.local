<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class CampaignsListComponent extends CBitrixComponent
{
  const GRID_ID = 'campaigns';

  private $storage;
  private $bxGridOptions;
  private $nav;
  private $campaignEntity;
  private $crmFieldManager;
  private $bxFilterOptions;
  private $bxFilterData = [];
    
  public function onPrepareComponentParams($arParams)
  {
    return $arParams;
  }

  public function executeComponent()
  {
    $this->storage = new Whatasoft\Campaign\CampaignStorage();
    $this->bxGridOptions = new Bitrix\Main\Grid\Options(self::GRID_ID);
    $this->nav = new Bitrix\Main\UI\PageNavigation(self::GRID_ID);
    $this->bxFilterOptions = new Bitrix\Main\UI\Filter\Options(self::GRID_ID);
    $this->bxFilterData = $this->bxFilterOptions->getFilter([]);
    $this->crmFieldManager = new \Whatasoft\Statistic\Helpers\CrmFieldManager();
    $this->campaignEntity = new \Whatasoft\Campaign\Entity();
    $this->campaignQueue = new \Whatasoft\Campaign\CampaignCallQueue();
    
    $this->arResult = [];
    $this->arResult['GRID_ID'] = self::GRID_ID;
    $this->arResult['FILTER'] = [];
    
    $this->processRequest();
    $this->setFilterList();
    $this->setFilter();
    $this->countResultRows();
    $this->setNavObject();
    $this->setBitrixTableHeader();
    $this->setCampaignLists();
    $this->obtainCampaigns($this->getFilter());
    $this->setCampaignStatistics();
    $this->setAdditionalCampaignFields();
    $this->setTableRows();
    $this->includeComponentTemplate();
  }
  
  private function processRequest()
  {
    if (!$this->isActionRequest()) {
      return;
    }
    
    $response['success'] = false;
    $response['message'] = '';
    $action = trim($this->getAction());
    
    switch($this->getAction()) {
      case 'next_stage':
        try {
          $this->handleNextStageAction();
        } catch (\Exception $exception) {
          $response['message'] = $exception->getMessage();
          $this->jsonResponse($response);
        }
        $response['success'] = true;
        break;
      case 'delete_campaign':
        try {
          $this->deleteCampaign();
        } catch (\Exception $exception) {
          $response['message'] = $exception->getMessage();
          $this->jsonResponse($response);
        }
        $response['success'] = true;
        break;
      default;
        break;
    }
    
    return $this->jsonResponse($response);
  }
  
  private function isActionRequest()
  {
    return $this->getAction() && check_bitrix_sessid('session_id');
  }
  
  private function getAction()
  {
    return $_POST['action'] ?? null;
  }  
  
  private function handleNextStageAction()
  {
    $handler = new \Whatasoft\Campaign\CampaignStageHandler();
    $campaignId = $_POST['campaign_id'] ?? false;
    $stageCode = $_POST['stage'] ?? false;
    $handler->handleNextStageAction($campaignId, $stageCode);
  }
  
  private function deleteCampaign()
  {
    $handler = new \Whatasoft\Campaign\CampaignStageHandler();
    $campaignId = $_POST['campaign_id'] ?? false;
    $handler->deleteCampaign($campaignId);
  }
  
  private function jsonResponse(array $data)
  {
    global $APPLICATION;
    $APPLICATION->RestartBuffer();
    echo json_encode($data);
    die();
  }
  
  private function setFilterList()
  {
    $stageItems = [];
    $arStageList = $this->campaignEntity->getCampaignStateList();
    
    foreach ($arStageList as $arStage) {
      $stageItems[$arStage['ID']] = $arStage['VALUE'];
    }
    
    $filterList = [
      [
        "id" => "CAMPAIGN_NAME",
        'type' => 'text',
        "name" => 'Название кампании',
        "default" => true
      ],
      [
        'id' => 'STAGE',
        'type' => 'list',
        'name' => 'Статус',
        'default' => true,
        'items' => $stageItems,
        'params' => ['multiple' => 'Y'],
      ],
    ];
    
    $this->arResult['FILTER_LIST'] = $filterList;
    
    return $this;
  }
  
  private function setFilter()
  {
    $arFilter = [];
    $bxFilter = $this->bxFilterData;
    
    if (!empty($bxFilter['CAMPAIGN_NAME']) && strlen(trim($bxFilter['CAMPAIGN_NAME']))) {
      $arFilter['NAME'] = '%' . $bxFilter['CAMPAIGN_NAME'] . '%';
    }
    
    if (!empty($bxFilter['STAGE']) && is_array($bxFilter['STAGE'])) {
      $arFilter['PROPERTY_CAMPAIGN_STATE'] = $bxFilter['STAGE'];
    }
    
    $this->arResult['FILTER'] = $arFilter;
    
    return $this;
  }
  
  private function getFilter()
  {
    return $this->arResult['FILTER'];
  }
  
  private function countResultRows()
  {
    $this->arResult['COUNT_ROWS'] = $this->storage->getCount($this->getFilter());
    return $this;
  }
  
  private function setNavObject()
  {
    $total = $this->arResult['COUNT_ROWS'];
    $navParams = $this->bxGridOptions->GetNavParams();
    $this->nav->allowAllRecords(true)->setRecordCount($total)->initFromUri();
    $this->arResult['NAV'] = $this->nav;
    
    return $this;
  }
  
  private function setBitrixTableHeader()
  {
    $tableHeader = [];
    $mainFields = $this->getMainHeaderFields();
    
    foreach($mainFields as $fieldCode => $fieldName) {
      $tableHeader[] = [
        'id' => $fieldCode,
        'name' => $fieldName,
        'sort' => $fieldCode,
        'default' => true,
      ];
    }
    
    $this->arResult['TABLE_HEADER'] = $tableHeader;
    
    return $this;
  }
  
  private function getMainHeaderFields()
  {
    return [
      'ID' => 'ID',
      'NAME' => 'Название',
      'STAGE' => 'Действия',
      'STATUS' => 'Статус',
    ];
  }
  
  private function obtainCampaigns($arFilter = [], $usePagination = true)
  {
    $arOrder = ['ID' => 'DESC'];
    $arSelect = ['*'];
    $arResult = [];
    $dbResult = $this->storage->getFiltered($arOrder, $arFilter, $arSelect);
    
    while($obElement = $dbResult->GetNextElement()) {
      $fields = $obElement->GetFields();
      $props = $obElement->GetProperties();
      $id = $fields['ID'];
      
      $arResult[$id] = $fields;
      $arResult[$id]['PROPS'] = $props;
    }
    
    $this->arResult['CAMPAIGNS'] = $arResult;
    
    return $this;
  }
  
  private function setCampaignLists()
  {
    $this->arResult['CAMPAIGN_STATE_LIST'] = $this->campaignEntity->getCampaignStateList();
    $this->arResult['QUEUE_LIST'] = $this->crmFieldManager->getQueueList();
    return $this;
  }
  
  private function setAdditionalCampaignFields()
  {
    $queueList = $this->arResult['QUEUE_LIST'];
    $statistic = $this->arResult['CAMPAIGN_STATISTIC'];
    
    foreach($this->arResult['CAMPAIGNS'] as &$arCampaign) {
      $id = $arCampaign['ID'];
      $queueId = $arCampaign['PROPS']['CAMPAIGN_RELATED_QUEUE']['VALUE'];
      $queueName = $queueList[$queueId]['NAME'] ?? '';
      $campaignStateCode = $arCampaign['PROPS']['CAMPAIGN_STATE']['VALUE_XML_ID'] ?? false;
      $campaignStateName = $arCampaign['PROPS']['CAMPAIGN_STATE']['VALUE'] ?? '';
      
      $arCampaign['QUEUE_ID'] = $queueId;
      $arCampaign['QUEUE_NAME'] = $queueName;
      $arCampaign['STAGE_NAME'] = $campaignStateName;
      $arCampaign['STAGE_CODE'] = $campaignStateCode;
      $arCampaign['STATISTIC'] = $statistic[$id];
    }
    unset($arCampaign);
    
    return $this;
  }
  
  private function setCampaignStatistics()
  {
    $arStatistic = [];
    $arCampaigns = $this->arResult['CAMPAIGNS'];
    $arCampaignKeys = array_keys($arCampaigns);
    $totalDealList = [];
    $campaignDealList = [];
    $arStub = [
      'TOTAL' => 0,
      'PROCESSED' => 0,
      'SUCCESS' => 0,
      'UNSUCCESSFULLY' => 0,
    ];
    
    // Собираем данные по связанным сделкам для всех кампаний
    foreach($arCampaigns as $campaignId => $arCampaign) {
      $arStatistic[$campaignId] = $arStub;
      $arCampaignDeals = $arCampaign['PROPS']['CAMPAIGN_RELATED_DEALS']['VALUE'];
      $campaignDealList[$campaignId] = $arCampaignDeals;
      $totalDealList = array_merge($totalDealList, $arCampaignDeals);
    }
    
    // Выбираем все сделки в БД
    $arSelect = ['ID'];
    $arDealList = $this->crmFieldManager->getDealList($totalDealList, $arSelect);
    $arDealKeys = array_keys($arDealList);
        
    // Считаем кол-во найденых в БД сделок для каждой кампании
    foreach($campaignDealList as $campaignId => $arCampaignDealKeys) {
      $arStatistic[$campaignId]['TOTAL'] = count(array_intersect($arCampaignDealKeys, $arDealKeys));
    }
    
    // Выбираем все связанные записи очередей кампаний
    $arQueueList = $this->campaignQueue->getCampaignQueueRows($arCampaignKeys);
    
    foreach($arQueueList as $arQueue) {
		//	print_r($arQueue);die();
      $campaignId = $arQueue['UF_CAMAPAIGN_ID'];
      $isProcessed = $arQueue['UF_IS_EXEC'] === 'Y';
      $isSuccessCall = $arQueue['UF_SUCCESS_CALL'] === 'Y';
      $isUnsuccessfully = $arQueue['UF_SUCCESS_CALL'] === 'N';
      $arStatistic[$campaignId]['PROCESSED'] += $isProcessed ? 1 : 0;
      $arStatistic[$campaignId]['SUCCESS'] += (int)$isSuccessCall ? 1 : 0;
      $arStatistic[$campaignId]['UNSUCCESSFULLY'] += (int)$isUnsuccessfully ? 1 : 0;
    }

    $this->arResult['CAMPAIGN_STATISTIC'] = $arStatistic;
  }
    
  private function setTableRows()
  {
    $tableRows = [];
    $mainFieldCodes = array_keys($this->getMainHeaderFields());
    
    foreach($this->arResult['CAMPAIGNS'] as $arCampaign) {
      $tableRow = [];
      $tableRow['data'] = [];
      $tableRow['actions'] = [];
      
      $tableRow['data']['ID'] = $arCampaign['ID'];
      $tableRow['data']['NAME'] = $this->renderCampaignName($arCampaign);
      $tableRow['data']['STAGE'] = $this->renderCampaignStage($arCampaign);
      $tableRow['data']['STATUS'] = $this->renderCampaignStatus($arCampaign);
      
      $tableRow['actions'][] = [
        'text'    => 'Удалить',
        'onclick' => "deleteCampaign({$arCampaign['ID']})",
      ];
      
      $tableRow['actions'][] = [
        'text'    => 'Завершить',
        'onclick' => "endCampaign({$arCampaign['ID']})",
      ];
      
      $tableRows[] = $tableRow;
    }
    
    $this->arResult['TABLE_ROWS'] = $tableRows;
    
    return $this;
  }
  
  private function renderCampaignName(array $arCampaign)
  {
    ob_start();
    ?>
    <div class="campaign-name-block">
      <span class="campaign-name"><?=$arCampaign['NAME']?></span>
    </div>
    <div class="campaign-queue-name-block">
      <span class="campaign-queue-name">очередь: <?=mb_strtolower($arCampaign['QUEUE_NAME'])?></span>
    </div>
    <?
    $html = ob_get_clean();
    return $html;
  }
  
  private function renderCampaignStage(array $arCampaign)
  {
    $stageCode = $arCampaign['STAGE_CODE'];
    $isFinalStage = $stageCode === $this->campaignEntity::CAMPAIGN_STATE_COMPLETED;
    $stageActions = [
      $this->campaignEntity::CAMPAIGN_STATE_NOT_STARTED => 'Обработать',
      $this->campaignEntity::CAMPAIGN_STATE_RUNNING => 'На паузу',
      $this->campaignEntity::CAMPAIGN_STATE_PAUSE => 'Продолжить',
      $this->campaignEntity::CAMPAIGN_STATE_COMPLETED => 'Отработано',
    ];
    $actionName = $stageActions[$stageCode] ?? '---';
    ob_start();
    ?>
<div class="campaign-stage-block <?if (!$isFinalStage){?>sender-letter-list-button sender-letter-list-button-green <?}?>">
      <?if($isFinalStage) {?>
        <span class='switch-stage-final'><?=$actionName?></span>
      <?} else {?>
	<span class="sender-letter-list-button-icon <?if ($stageCode == $this->campaignEntity::CAMPAIGN_STATE_RUNNING){?> sender-letter-list-button-icon-pause <?} else {?> sender-letter-list-button-icon-play <?}?>"></span>
        <a href='#' class='switch-stage' 
           data-id=<?=$arCampaign['ID']?>
           data-stage='<?=$arCampaign['STAGE_CODE']?>'><?=$actionName?></a>
      <?}?>
    </div>
    <div class="campaign-created-datetime-block">
      <span>Создана: <?=\FormatDate('d F, H:i', MakeTimeStamp($arCampaign['DATE_CREATE']))?></span>
    </div>
    <?
    $html = ob_get_clean();
    return $html;
  }
  
  private function renderCampaignStatus(array $arCampaign)
  {
    ob_start();
    ?>
    <div class="campaign-status-block">Клиентов к обработке: <?=$arCampaign['STATISTIC']['TOTAL']?></div>
    <div class="campaign-status-block">Обработано: <?=$arCampaign['STATISTIC']['PROCESSED']?></div>
    <div class="campaign-status-block">Успешно: <?=$arCampaign['STATISTIC']['SUCCESS']?></div>
    <div class="campaign-status-block">Неуспешно: <?=$arCampaign['STATISTIC']['UNSUCCESSFULLY']?></div>
    <?
    $html = ob_get_clean();
    return $html;
  }
}