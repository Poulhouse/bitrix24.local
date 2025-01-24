<?php

namespace Whatasoft\Campaign;

use Whatasoft\Storage\HlBlockStorage;

class CampaignCallQueue
{
  private $storage;
  
  public function __construct()
  {
    $this->storage = new HlBlockStorage(HLBLOCK_CAMPAIGIN_QUEUE_ID);
  }
  
  public function createCampaignQueueRow($queueId, $campaignId, $asteriskQueueId, $dealId, $phone, $active = true)
  {
    $arFields = [
      'UF_QUEUE_ID' => $queueId,
      'UF_CAMAPAIGN_ID' => $campaignId,
      'UF_DEAL_ID' => $dealId,
      'UF_ASTERISK_QUEUE_ID' => $asteriskQueueId,
      'UF_PHONE' => $phone,
      'UF_ACTIVE' => ($active) ? 'Y' : 'N',
      'UF_IS_EXEC' => 'N',
    ];
    
    $this->storage->create($arFields);
  }
  
  public function getStorage()
  {
    return $this->storage;
  }
  
  public function removeCampaignFromQueue($campaignId)
  {
    $arResult = $this->getCampaignQueueRows($campaignId, ['ID']);
    foreach($arResult as $arRow) {
      $this->storage->delete($arRow['ID']);
    }
  }
  
  public function deactivateCampaignInQueue($campaignId)
  {
    $arResult = $this->getCampaignQueueRows($campaignId, ['ID']);
    $arFields = ['UF_ACTIVE' => 'N'];
    foreach($arResult as $arRow) {
      $this->storage->update($arRow['ID'], $arFields);
    }
  }
  
  public function activateCampaignInQueue($campaignId)
  {
    $arResult = $this->getCampaignQueueRows($campaignId, ['ID']);
    $arFields = ['UF_ACTIVE' => 'Y'];
    foreach($arResult as $arRow) {
      $this->storage->update($arRow['ID'], $arFields);
    }
  }
  
  public function getCampaignQueueRows($campaignId, array $arSelect = ['*'])
  {
    $arFilter = ['UF_CAMAPAIGN_ID' => $campaignId];
    return $this->storage->getFiltered([], $arFilter, $arSelect)->fetchAll();
  }
  
  public function getCallQueueRowsByDealQueueId($dealQueueId, array $arSelect = ['*'])
  {
    $arFilter = [
      'UF_QUEUE_ID' => $dealQueueId,
      'UF_ACTIVE' => 'Y',
      '!UF_IS_EXEC' => 'Y',
    ];
    
    return $this->storage->getFiltered([], $arFilter, $arSelect)->fetchAll();
  }
  
  public function getActiveCallQueueForDeal($dealId, array $arSelect = ['*'])
  {
    $arFilter = [
      'UF_DEAL_ID' => $dealId,
      'UF_ACTIVE' => 'Y',
      '!UF_IS_EXEC' => 'Y',
    ];
    
    return $this->storage->getFiltered([], $arFilter, $arSelect)->fetch();
  }
  
  public function getActiveCallQueueByPhone($phone, $arSelect = ['*'])
  {
    $arFilter = ['UF_PHONE' => $phone, 'UF_ACTIVE' => 'Y'];
    return $this->storage->getFiltered([], $arFilter, $arSelect)->fetch();
  }
  
  public function setCallResult($callQueueId, $success, $callDateTime, $isExec = 'Y')
  {
    $arFields = [
      'UF_IS_EXEC' => $isExec,
      'UF_SUCCESS_CALL' => ($success === 'Y') ? 'Y' : 'N',
      'UF_CALL_DATETIME' => $callDateTime,
    ];
    
    return $this->storage->update($callQueueId, $arFields);
  }
}
