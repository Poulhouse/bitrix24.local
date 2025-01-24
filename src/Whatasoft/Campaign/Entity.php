<?php

namespace Whatasoft\Campaign;

use Whatasoft\Helpers\Traits\ModuleLoader;

class Entity
{
  use ModuleLoader;
  
  const CAMPAIGN_STATE_RUNNING = 'RUNNING';
  const CAMPAIGN_STATE_PAUSE = 'PAUSE';
  const CAMPAIGN_STATE_COMPLETED = 'COMPLETED';
  const CAMPAIGN_STATE_RESUME = 'RESUME';
  const CAMPAIGN_STATE_NOT_STARTED = 'NOT_STARTED';
  
  private $requiredModules = ['crm', 'iblock'];
  private $storage;
  
  public function __construct()
  {
    $this->includeModules();
    $this->storage = new CampaignStorage();
  }
  
  public function create($campaignName, $queueId, array $dealIds)
  {
    $fields = [
      'NAME' => $campaignName,
    ];
    $status = $this->getCampaignStateByCode(self::CAMPAIGN_STATE_NOT_STARTED, []);
    $props = [
      'CAMPAIGN_RELATED_QUEUE' => $queueId,
      'CAMPAIGN_RELATED_DEALS' => $dealIds,
      'CAMPAIGN_STATE' => $status['ID'] ?? 0,
    ];
    return $this->storage->create($fields, $props);
  }
  
  public function createFromContacts($userId, $campaignName, $queueId, array $contactIds)
  {
    $arContacts = [];
    $arFilter = ['ID' => $contactIds];
    $arSelect = ['ID', 'FULL_NAME'];
    $crmContacts = \CCrmContact::GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect);
    
    while($arRow = $crmContacts->GetNext()) {
      $arContacts[$arRow['ID']] = $arRow;
    }
    
    if (empty($arContacts)) {
      throw new \Exception('Список контактов пуст');
    }
    
    $crmDeal = new \CCrmDeal(false);
    $arDealIds = [];
    $typeId = $this->getDealTypeIdByQueueId($queueId);
    foreach($arContacts as $arContact) {
      $arFields = [
        'TITLE' => $arContact['FULL_NAME'],
        'CONTACT_ID' => $arContact['ID'],
        'ASSIGNED_BY' => $userId,
        'CREATED_BY' => $userId,
        'UF_CRM_QUEUE_GROUP' => $queueId,
        'SOURCE_ID' => 1, // Агенство недвижимости 
        'TYPE_ID' => $typeId,
        'UF_CRM_1610713435' =>$campaignName
      ];
            
      $dealId = $crmDeal->Add($arFields);
      
      if ($dealId) {
        $arDealIds[] = $dealId;
      }
      
    }
    
    if (!count($arDealIds)) {
      throw new \Exception('Список созданных сделок пуст');
    }
    
    return $this->create($campaignName, $queueId, $arDealIds);
  }
  
  
  public function getDealTypeIdByQueueId($queueId){
    $typeId = 1;
    \Bitrix\Main\Loader::includeModule("iblock");
    $queue = \CIblockElement::GetByID($queueId)->GetNextElement();
    if ($queue){
        $prop = $queue->GetProperties();
        if (isset($prop[PROP_QUEUE_TYPE_ID]) 
            && $prop[PROP_QUEUE_TYPE_ID]['VALUE_ENUM_ID'] > 0 
            && isset(NAPRAVLENIE_SOZDAVAEMYKH_SDELOK_TO_TYPE_ID[$prop[PROP_QUEUE_TYPE_ID]['VALUE_ENUM_ID']])){
            var_dump(NAPRAVLENIE_SOZDAVAEMYKH_SDELOK_TO_TYPE_ID[$prop[PROP_QUEUE_TYPE_ID]['VALUE_ENUM_ID']]);
            $typeId = NAPRAVLENIE_SOZDAVAEMYKH_SDELOK_TO_TYPE_ID[$prop[PROP_QUEUE_TYPE_ID]['VALUE_ENUM_ID']];
        }
    }
    return $typeId;
  }
  public function getCampaignStateByCode($statusCode, $default = null)
  {
    $arFilter = [
      'IBLOCK_ID' => IBLOCK_CAMPAIGN_ID,
      'CODE' => 'CAMPAIGN_STATE',
      'XML_ID' => $statusCode,
    ];
    $arResult = \CIBlockPropertyEnum::GetList([], $arFilter)->Fetch();

    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getCampaignStateList()
  {
    $arResult = [];
    $arFilter = [
      'IBLOCK_ID' => IBLOCK_CAMPAIGN_ID,
      'CODE' => 'CAMPAIGN_STATE',
    ];
    $dbResult = \CIBlockPropertyEnum::GetList([], $arFilter);
    while($arRow = $dbResult->GetNext()) {
      $arResult[$arRow['ID']] = $arRow;
    }
    
    return $arResult;
  }
  
  public function addContactsToCampaign($campaignId, array $contactIds)
  {

    $campaign = $this->storage->getIBlockElementById($campaignId);
    if (empty($campaign)) {
      throw new \Exception("Кампания с ID {$campaignId} не существует");
    }
    $props = $campaign->GetProperties();
    $queueId = $props['CAMPAIGN_RELATED_QUEUE']['VALUE'] ?? 0;
    $fields = $campaign->GetFields();
    $arDealIds = $this->createContactDeals($contactIds, $queueId, $fields['NAME']);
    
    if (!count($arDealIds)) {
      throw new \Exception('Список созданных сделок пуст');
    }
    
    $this->addDealsToCampaign($campaignId, $arDealIds);
  }
  
  public function addDealsToCampaign($campaignId, array $dealIds)
  {
    $stageHandler = new CampaignStageHandler();
    $campaign = $this->storage->getIBlockElementById($campaignId);
    
    if (empty($campaign)) {
      throw new \Exception("Кампания с ID {$campaignId} не существует");
    }
    
    $props = $campaign->GetProperties();
    $campaignRelatedDeals = $props['CAMPAIGN_RELATED_DEALS']['VALUE'] ?? [];
    $campaignRelatedDeals = is_array($campaignRelatedDeals) ? $campaignRelatedDeals : [];
    $campaignStageCode = $props['CAMPAIGN_STATE']['VALUE_XML_ID'] ?? false;
    $updatedRelatedDeals = array_unique(array_merge($campaignRelatedDeals, $dealIds));
    $this->storage->update($campaignId, [], ['CAMPAIGN_RELATED_DEALS' => $updatedRelatedDeals]);
    $stageHandler->refreshCampaignCallQueueByStageCode($campaignId, $campaignStageCode);
  }

  private function createContactDeals(array $contactIds, $queueId = 0, $name='')
  {

    $arContacts = [];
    $arFilter = ['ID' => $contactIds];
    $arSelect = ['ID', 'FULL_NAME'];
    $crmContacts = \CCrmContact::GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect);

    while($arRow = $crmContacts->GetNext()) {
      $arContacts[$arRow['ID']] = $arRow;
    }
    
    if (empty($arContacts)) {
      throw new \Exception('Список контактов пуст');
    }
    
    $crmDeal = new \CCrmDeal(false);
    $arDealIds = [];
    $typeId = $this->getDealTypeIdByQueueId($queueId);
    foreach($arContacts as $arContact) {
      $arFields = [
        'TITLE' => $arContact['FULL_NAME'],
        'CONTACT_ID' => $arContact['ID'],
        'ASSIGNED_BY' => $userId,
        'CREATED_BY' => $userId,
        'UF_CRM_QUEUE_GROUP' => $queueId,
        'SOURCE_ID' => 1, // Агенство недвижимости 
        'TYPE_ID' => $typeId,
        'UF_CRM_1610713435' =>$name
		
      ];

      $dealId = $crmDeal->Add($arFields);
      
      if ($dealId) {
        $arDealIds[] = $dealId;
      }
    }
    
    return $arDealIds;
  }
  
}