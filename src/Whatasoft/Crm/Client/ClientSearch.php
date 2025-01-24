<?php

namespace Whatasoft\Crm\Client;

use Whatasoft\Helpers\Traits\ModuleLoader;

class ClientSearch
{
  use ModuleLoader;
  
  private $requiredModules = ['crm'];
  private $crmContactManager;
  
  public function __construct()
  {
    $this->crmContactManager = new \Whatasoft\Helpers\Crm\ContactManager();
  }
  
  public function search(array $arOrder = [], array $arFilter = [], array $arSelect = ['*'], int $limit = 20)
  {
    $arClients = $this->crmContactManager->getContactsList($arOrder, $arFilter, $arSelect, $limit);
    $arClientIds = array_keys($arClients);
    $arClientMobilePhones = $this->crmContactManager->getContactsMobilePhonesList($arClientIds);
    foreach ($arClients as &$arClient) {
      $clientId = $arClient['ID'];
      $arClient['MOBILE_PHONES'] = $arClientMobilePhones[$clientId] ?? [];
    }
    unset($arClient);
    
    return $arClients;
  }
}