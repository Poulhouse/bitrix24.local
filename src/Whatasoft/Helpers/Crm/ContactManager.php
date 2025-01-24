<?php

namespace Whatasoft\Helpers\Crm;

use Whatasoft\Helpers\Traits\ModuleLoader;

class ContactManager
{
  use ModuleLoader;
  
  private $requiredModules = ['crm'];
  private $crmContact;
  
  public function __construct()
  {
    $this->includeModules();
    $this->crmContact = new \CCrmContact(false);
  }
  
  public function getContactsList(
    array $arOrder = [],
    array $arFilter = [],
    array $arSelect = ['*', 'UF_*'],
    int $limit = 20
  )
  {
    $arClients = [];
    $arFilter['CHECK_PERMISSIONS'] = 'N';
    $arNavStartParams = ($limit) ? ['nTopCount' => $limit] : false;
    $dbResult = $this->crmContact->GetListEx($arOrder, $arFilter, false, $arNavStartParams, $arSelect);
    while($arRow = $dbResult->GetNext()) {
      $clientId = $arRow['ID'];
      $arClients[$clientId] = $arRow;
    }
    
    return $arClients;
  }
  
  public function getContactById(int $id, array $arSelect = ['*', 'UF_*'])
  {
    $arFilter = [
      'CHECK_PERMISSIONS' => 'N',
      'ID' => $id,
    ];
    return $this->crmContact->GetListEx([], $arFilter, false, false, $arSelect)->Fetch();
  }
  
  public function getContactsMobilePhonesList($contactIds)
  {
    $arContactPhones = [];
    $arOrder = ['ID' => 'ASC'];
    $arFilter = [
      'ENTITY_ID' => 'CONTACT',
      'ELEMENT_ID' => $contactIds,
      'TYPE_ID' => 'PHONE',
    ];
    $dbResult = \CCrmFieldMulti::GetList($arOrder, $arFilter);

    while ($arRow = $dbResult->GetNext()) {
      $contactId = $arRow['ELEMENT_ID'];
      
      if (!isset($arContactPhones[$contactId])) {
        $arContactPhones[$contactId] = [];
      }
      
      $phoneNumber = (string)$arRow['~VALUE'];
      $mobile = \Whatasoft\Helpers\Phone::standardizeMobileNumber($phoneNumber);
      
      if ($mobile) {
        $arContactPhones[$contactId][] = $mobile;
      }
    }
    
    return $arContactPhones;
  }
}