<?php

namespace Whatasoft\Incoming;

use Whatasoft\Helpers\Traits\ModuleLoader;

class CallHandler 
{
  use ModuleLoader;
  
  private $requiredModules = ['crm', 'iblock'];
  
  public function __construct()
  {
    $this->includeModules();
  }
  
  public function createDealFromContact($contactId, $queueId)
  {
    $arContact = $this->getContact($contactId);
    return $this->createDeal($arContact, $queueId);
  }
  
  private function getContact($contactId)
  {
    $crmContact = new \CCrmContact(false);
    $arFilter = ['ID' => $contactId, 'CHECK_PERMISSIONS' => 'N'];
    $arSelect = ['ID', 'FULL_NAME'];
    $arContact = $crmContact->GetListEx(['ID' => 'ASC'], $arFilter, false, false, $arSelect)->Fetch();
    
    if (empty($arContact)) {
      throw new \Exception("Контакт с ID {$contactId} не найден в системе");
    }
    
    return $arContact;
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

  private function createDeal(array $arContact, $queueId)
  {
    $crmDeal = new \CCrmDeal(false);
    $userId = (new \CUser())->GetID();
    $arFields = [
        'TITLE' => $arContact['FULL_NAME'],
        'CONTACT_ID' => $arContact['ID'],
        'ASSIGNED_BY' => $userId,
        'CREATED_BY' => $userId,
        'UF_CRM_QUEUE_GROUP' => $queueId,
        'SOURCE_ID' => 1, // Агенство недвижимости 
        'TYPE_ID' => $this->getDealTypeIdByQueueId($queueId),
		"CATEGORY_ID" => $this->getDealTypeIdByQueueId($queueId) == 1 ? 0 : 2
    ];
    
    $dealId = $crmDeal->Add($arFields);
    
    if (!$dealId) {
      throw new \Exception($crmDeal->LAST_ERROR);
    }
    
    return $dealId;
  }
}