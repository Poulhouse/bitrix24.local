<?php

namespace Whatasoft\Campaign;

class CallQueueEventHandler 
{
  private $storage;
  private $stageHandler;
  
  public function __construct()
  {
    $this->storage = new \Whatasoft\Campaign\CampaignCallQueue();
    $this->stageHandler = new \Whatasoft\Campaign\CampaignStageHandler();
  }
  
  public function handleSuccessfulCall($callQueueId, $isExec = 'Y')
  {
    $arCallQueueRow = $this->getCallQueueRow($callQueueId);
    $this->setCallResult($callQueueId, 'Y', $isExec);
    $this->handleCompleteCampaignStage($arCallQueueRow);
  }
  
  public function handleUnsuccessfulCall($callQueueId, $isExec = 'Y')
  {
    $arCallQueueRow = $this->getCallQueueRow($callQueueId);
    $this->setCallResult($callQueueId, 'N', $isExec);
    $this->handleCompleteCampaignStage($arCallQueueRow);
  }

  public function handleRecall($callQueueId)
  {
    $arCallQueueRow = $this->getCallQueueRow($callQueueId);
    $this->setCallResult($callQueueId, 'N', 'N');
    $this->handleCompleteCampaignStage($arCallQueueRow);
  }
    
  private function getCallQueueRow($callQueueId)
  {
    $arResult = $this->storage->getStorage()->getById($callQueueId);
    
    if (empty($arResult)) {
      throw new \Exception('Очередь не найдена');
    }
    
    return $arResult;
  }
  
  private function setCallResult($callQueueId, $success, $isExec = 'Y')
  {
    $callDateTime = new \Bitrix\Main\Type\DateTime();
    return $this->storage->setCallResult($callQueueId, $success, $callDateTime, $isExec);
  }
  
  private function handleCompleteCampaignStage(array $arCallQueueRow)
  {
    $campaignId = $arCallQueueRow['UF_CAMAPAIGN_ID']
      ? $arCallQueueRow['UF_CAMAPAIGN_ID'] 
      : false;
      
    if ($this->isCompleteCampaignStage($campaignId)) {
      $this->stageHandler->handleNextStageAction($campaignId, Entity::CAMPAIGN_STATE_COMPLETED);
    }
  }
  
  private function isCompleteCampaignStage($campaignId)
  {
    $arFilter = [
      'UF_CAMAPAIGN_ID' => $campaignId,
      'UF_ACTIVE' => 'Y',
      '!UF_IS_EXEC' => 'Y',
    ];
    
    return $this->storage->getStorage()->getCount($arFilter) === 0;
  }
}