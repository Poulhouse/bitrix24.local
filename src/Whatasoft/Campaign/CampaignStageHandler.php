<?php

namespace Whatasoft\Campaign;

use Whatasoft\Storage\IBlockElementStorage;
use Whatasoft\Storage\HlBlockStorage;
use Whatasoft\Statistic\Helpers\CrmFieldManager;
use Whatasoft\Asterisk\Queue as AsteriskQueue;

class CampaignStageHandler
{
  private $callQueue;
  private $asteriskQueue;
  private $crmFieldManager;

  public function __construct()
  {
    $this->entity = new Entity();
    $this->callQueue = new CampaignCallQueue();
    $this->crmFieldManager = new CrmFieldManager();
    $this->asteriskQueue = new AsteriskQueue();
  }
  
  public function deleteCampaign($campaignId)
  {
    $storage = new IBlockElementStorage(IBLOCK_CAMPAIGN_ID);
    $arFilter = ['ID' => $campaignId];
    $arSelect = ['ID'];
    $arResult = $storage->getFiltered([], $arFilter, $arSelect)->Fetch();
    if (empty($arResult['ID'])) {
      throw new \Exception("Кампания с ID {$campaignId} не найдена");
    }
    return $storage->delete($campaignId);
  }
  
  // Переводит кампанию на следующую стадию
  public function handleNextStageAction($campaignId, $stageCode)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $nextActionStageCode = $this->getNextActionStageCode($stageCode);
    $nextDatabaseStageCode = $this->getNextDatabaseStageCode($stageCode);
    $arNextDatabaseStage = $this->getCampaignStageByCode($nextDatabaseStageCode);
    $nextDatabaseStageId = $arNextDatabaseStage['ID'];
    
    $this->runStageAction($campaignId, $nextActionStageCode);
    $this->updateCampaignStage($campaignId, $nextDatabaseStageId);
  }
  
  // Возвращает следующий код действия для переданного кода состояния кампании
  public function getNextActionStageCode($stageCode)
  {
    $nextStageList = [
      Entity::CAMPAIGN_STATE_NOT_STARTED => Entity::CAMPAIGN_STATE_RUNNING,
      Entity::CAMPAIGN_STATE_RUNNING => Entity::CAMPAIGN_STATE_PAUSE,
      Entity::CAMPAIGN_STATE_PAUSE => Entity::CAMPAIGN_STATE_RESUME,
      Entity::CAMPAIGN_STATE_RESUME => Entity::CAMPAIGN_STATE_PAUSE,
    ];
    
    if ($stageCode === Entity::CAMPAIGN_STATE_COMPLETED) {
      return Entity::CAMPAIGN_STATE_COMPLETED;
    }
    
    return $nextStageList[$stageCode] ?? false;
  }
  
  // Возвращает следующий код в базе данных для переданного кода состояния кампании
  public function getNextDatabaseStageCode($stageCode)
  {
    $nextStageList = [
      Entity::CAMPAIGN_STATE_NOT_STARTED => Entity::CAMPAIGN_STATE_RUNNING,
      Entity::CAMPAIGN_STATE_RUNNING => Entity::CAMPAIGN_STATE_PAUSE,
      Entity::CAMPAIGN_STATE_PAUSE => Entity::CAMPAIGN_STATE_RUNNING,
    ];
    
    if ($stageCode === Entity::CAMPAIGN_STATE_COMPLETED) {
      return Entity::CAMPAIGN_STATE_COMPLETED;
    }
    
    return $nextStageList[$stageCode] ?? false;
  }
  
  public function runStageAction($campaignId, $stageCode)
  {
    switch($stageCode) {
      case Entity::CAMPAIGN_STATE_RUNNING:
        return $this->startCampaign($campaignId);
      case Entity::CAMPAIGN_STATE_PAUSE:
        return $this->pauseCampaign($campaignId);
      case Entity::CAMPAIGN_STATE_RESUME:
        return $this->resumeCampaign($campaignId);
      case Entity::CAMPAIGN_STATE_COMPLETED:
        return $this->completeCampaign($campaignId);
      default:
        throw new \Exception('Неверный тип состояния кампании');
    }
  }
  
  public function refreshCampaignCallQueueByStageCode($campaignId, $stageCode)
  {
    switch($stageCode) {
      case Entity::CAMPAIGN_STATE_NOT_STARTED:
        return;
      case Entity::CAMPAIGN_STATE_RUNNING:
        $this->refreshCampaignCallQueue($campaignId, true);
        return;
      case Entity::CAMPAIGN_STATE_PAUSE:
        $this->refreshCampaignCallQueue($campaignId, false);
        return;
      case Entity::CAMPAIGN_STATE_COMPLETED:
        return;
      default:
        throw new \Exception('Неверный тип состояния кампании');
    }
  }
  
  private function startCampaign($campaignId)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $campaignQueueId = $this->getIBlockElementPropValue(
      $arCampaign,
      'CAMPAIGN_RELATED_QUEUE',
      false,
      false
    );
    $campaignDealIds = $this->getIBlockElementPropValue(
      $arCampaign,
      'CAMPAIGN_RELATED_DEALS',
      false,
      false
    );
    $arCampaignQueue = $this->getCampaignQueue($campaignQueueId);
    $arCampaignDeals = $this->getCampaignDeals($campaignDealIds);
    $arDealPhones = $this->getDealsPhonesToCall($arCampaignDeals);
    $asteriskQueueEntityId = $this->getIBlockElementPropValue(
      $arCampaignQueue,
      'ASTERISK_QUEUE_ID',
      false,
      false
    );
    
    $arAsteriskQueue = $this->getAsteriskQueue($asteriskQueueEntityId);
    $asteriskQueueId = $arAsteriskQueue['PROPS']['ASTERISK_QUEUE_ID']['VALUE'];
    $arPhonesToCall = $this->preparePhoneToCallList($arDealPhones);
    
    if (empty($arPhonesToCall)) {
      throw new \Exception('Список номеров для обзвона кампании пуст');
    }
    
    $this->callQueue->removeCampaignFromQueue($campaignId);
    
    foreach($arPhonesToCall as $dealId => $phoneToCall) {
      $this->callQueue->createCampaignQueueRow(
        $campaignQueueId,
        $campaignId,
        $asteriskQueueId,
        $dealId,
        $phoneToCall
      );
    }
  }

  private function pauseCampaign($campaignId)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $this->callQueue->deactivateCampaignInQueue($campaignId);
  }
  
  private function resumeCampaign($campaignId)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $this->callQueue->activateCampaignInQueue($campaignId);
  }
  
  private function completeCampaign($campaignId)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $this->callQueue->deactivateCampaignInQueue($campaignId);
  }
  
  // Обновление очереди обзвона кампании после добавления в неё новых сделок
  private function refreshCampaignCallQueue($campaignId, $setNewAsActive = false)
  {
    $arCampaign = $this->getCampaign($campaignId);
    $arCampaignDealIds = $this->getIBlockElementPropValue(
      $arCampaign,
      'CAMPAIGN_RELATED_DEALS',
      false,
      []
    );
    $campaignQueueId = $this->getIBlockElementPropValue(
      $arCampaign,
      'CAMPAIGN_RELATED_QUEUE',
      false,
      false
    );
    $arCampaignQueue = $this->getCampaignQueue($campaignQueueId);
        
    // Список: ID сделки -> ID связанных очередей
    $arDealCallQueueIds = [];
    $arCampaignCallQueueRows = $this->callQueue->getCampaignQueueRows($campaignId, ['ID', 'UF_DEAL_ID']);
    
    // Список: ID сделки -> ID связанных очередей
    foreach($arCampaignCallQueueRows as $arRow) {
      $dealId = $arRow['UF_DEAL_ID'];
      if (!isset($arDealCallQueueIds[$dealId])) {
        $arDealCallQueueIds[$dealId] = [];
      }
      $arDealCallQueueIds[$dealId][] = $arRow['ID'];
    }    
    
    $arCampaignCallQueueDealIds = array_keys($arDealCallQueueIds);
    $arDealsToAdd = array_diff($arCampaignDealIds, $arCampaignCallQueueDealIds);
    $arDealsToDelete = array_diff($arCampaignCallQueueDealIds, $arCampaignDealIds);
    $arDealsToAdd = count($arDealsToAdd) ? $arDealsToAdd : false;
    $arDealsToDelete = count($arDealsToDelete) ? $arDealsToDelete : false;
    
    if (empty($arDealsToAdd)) {
      return;
    }
    
    // Добавление новых сделок в список обзвона
    $arCampaignDeals = $this->getCampaignDeals($arDealsToAdd);
    $arDealPhones = $this->getDealsPhonesToCall($arCampaignDeals);
    $asteriskQueueEntityId = $this->getIBlockElementPropValue(
      $arCampaignQueue,
      'ASTERISK_QUEUE_ID',
      false,
      false
    );
    
    $arAsteriskQueue = $this->getAsteriskQueue($asteriskQueueEntityId);
    $asteriskQueueId = $arAsteriskQueue['PROPS']['ASTERISK_QUEUE_ID']['VALUE'];
    $arPhonesToCall = $this->preparePhoneToCallList($arDealPhones);
    
    if (empty($arPhonesToCall)) {
      throw new \Exception('Список номеров для обзвона кампании пуст');
    }
    
    foreach($arPhonesToCall as $dealId => $phoneToCall) {
      $this->callQueue->createCampaignQueueRow(
        $campaignQueueId,
        $campaignId,
        $asteriskQueueId,
        $dealId,
        $phoneToCall,
        $setNewAsActive
      );
    }
    
    return count($arDealsToAdd);
  }
    
  private function getCampaign($campaignId): array
  {
    $arCampaign = $this->getIBlockElementById(IBLOCK_CAMPAIGN_ID, $campaignId);
    
    if (empty($arCampaign)) {
      throw new \Exception('Кампания не найдена');
    }
    
    return $arCampaign;
  }
  
  private function getCampaignQueue($queueId): array
  {
    $arQueue = $this->getIBlockElementById(IBLOCK_QUEUE_ID, $queueId);
    
    if (empty($arQueue)) {
      throw new \Exception('Очередь не найдена');
    }
    
    return $arQueue;
  }
  
  private function getAsteriskQueue($asteriskQueueId): array
  {
    $arAsteriskQueue = $this->getIBlockElementById(IBLOCK_ASTERISK_ID, $asteriskQueueId);
    
    if (empty($arAsteriskQueue)) {
      throw new \Exception('Очередь Астериск не найдена');
    }
    
    return $arAsteriskQueue;
  }
  
  private function getCampaignDeals($dealIds): array
  {
    $arSelect = ['*'];
    $arDealList = $this->crmFieldManager->getDealList($dealIds, $arSelect);
    
    if (empty($arDealList)) {
      throw new \Exception('Список сделок кампании пуст');
    }
    
    return $arDealList;
  }
  
  private function getCampaignStageByCode($stageCode)
  {
    $stage = $this->entity->getCampaignStateByCode($stageCode);
    
    if (!$stage) {
      throw new \Exception('Не найдена следующая стадия кампании');
    }
    
    return $stage;
  }
  
  private function getDealsPhonesToCall(array $arDeals)
  {
    $arDealPhones = [];
    $arDealContactIds = array_map(function($arDeal) {
      return $arDeal['CONTACT_ID'];
    }, $arDeals);

    $arContactPhones = $this->crmFieldManager->getContactsPhoneToCall($arDealContactIds);
    
    foreach($arDeals as $dealId => $arDeal) {
      $contactId = $arDeal['CONTACT_ID'];
      $arDealPhones[$dealId] = $arContactPhones[$contactId] ?? '';
    }
    
    return $arDealPhones;
  }
  
  private function getIBlockElementById($iBlockId, $elementId)
  {
    $storage = new IBlockElementStorage($iBlockId);
    $arFilter = ['ID' => $elementId];
    $obElement = $storage->getFiltered([], $arFilter)->GetNextElement();
    
    if (empty($obElement)) {
      return null;
    }
    
    return [
      'FIELDS' => $obElement->GetFields(),
      'PROPS' => $obElement->GetProperties(),
    ];
  }
  
  private function getIBlockElementFieldValue(
    array $iBlockElement,
    string $fieldName,
    bool $allowEmpty = true,
    $default = null)
  {
    if (!$allowEmpty && empty($iBlockElement['FIELDS'][$fieldName])) {
      return $default;
    }
        
    return $iBlockElement['FIELDS'][$fieldName] ?? $default;
  }
  
  private function getIBlockElementPropValue(
    array $iBlockElement,
    string $propCode,
    bool $allowEmpty = true,
    $default = null)
  {
    if (!$allowEmpty && empty($iBlockElement['PROPS'][$propCode]['VALUE'])) {
      return $default;
    }
    
    return $iBlockElement['PROPS'][$propCode]['VALUE'] ?? $default;
  }
  
  private function preparePhoneToCallList(array $phones)
  {
    $preparedPhoneList = array_map([$this, 'getPreparedPhoneToCall'], $phones); 
    return array_filter($preparedPhoneList, function($phone) {
      return strlen($phone) > 0;
    });
  }
  
  private function getPreparedPhoneToCall(string $phone)
  {
    return \Whatasoft\Helpers\Phone::standardizeMobileNumber($phone);
  }
  
  private function updateCampaignStage($campaignId, $stageId)
  {
    $storage = new IBlockElementStorage(IBLOCK_CAMPAIGN_ID);
    $props = ['CAMPAIGN_STATE' => $stageId];
    return $storage->update($campaignId, [], $props);
  }
  
  private function getAsteriskQueuePhones($campaignId, string $active = 'Y')
  {
    $storage = new HlBlockStorage(HLBLOCK_CAMPAIGIN_QUEUE_ID);
    $active = ($active === 'Y') ? 'Y' : 'N';
    
    $arFilter = [
      '!UF_IS_EXEC' => 'Y',
      'UF_CAMAPAIGN_ID' => $campaignId,
      'UF_ACTIVE' => $active,
    ];

    $arAsteriskQueuePhones = [];
    $arResult = $storage->getFiltered([], $arFilter)->fetchAll();
    foreach ($arResult as $arRow) {
      $asteriskQueueId = $arRow['UF_ASTERISK_QUEUE_ID'];
      
      if (!isset($arAsteriskQueuePhones[$asteriskQueueId])) {
        $arAsteriskQueuePhones[$asteriskQueueId]= [];
      }
      
      $arAsteriskQueuePhones[$asteriskQueueId][] = $arRow['UF_PHONE'];
    }

    return $arAsteriskQueuePhones;
  }
}