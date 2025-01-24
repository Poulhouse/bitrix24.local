<?php

namespace Whatasoft\Helpers\Crm;

use Whatasoft\Helpers\Traits\ModuleLoader;

class Utils
{
  use ModuleLoader;
  
  private $requiredModules = ['crm'];
  
  public function __construct()
  {
    $this->includeModules();
  }
  
  public function getContactCallEventsList($contactId, array $arOrder = [])
  {
    $arFilter = [
      'BINDINGS' => [[
        'OWNER_ID' => $contactId,
        'OWNER_TYPE_ID' => \CCrmOwnerType::Contact,
      ]],
    ];
    $arNavParams = ['nTopCount' => 10];

    $arResult = [];    
    $dbResult = \CCrmActivity::GetList($arOrder, $arFilter, false, $arNavParams);
    
    while($arEventRow = $dbResult->Fetch()) {
      $arResult[] = array_merge($arEventRow, [
        'HTML' => $this->renderCallEventHtml($arEventRow),
      ]);
    }
    
    return $arResult;
  }
  
  public function incrementDealCallAttempts($dealId)
  {
    $obCrmDeal = new \CCrmDeal(false);
    $arDeal = $obCrmDeal->GetListEx([], ['ID' => $dealId], false, false, ['UF_CRM_1614173441'])->Fetch();
    
    if (is_null($arDeal)) {
      return;
    }
    
    $callAttempts = (int)$arDeal['UF_CRM_1614173441'];
    $nextValue = $callAttempts + 1;
    $arFields = ['UF_CRM_1614173441' => $nextValue];
    
    $obCrmDeal->Update($dealId, $arFields);
  }
  
  protected function renderCallEventHtml(array $arEvent)
  {
    $responsible = trim($arEvent['RESPONSIBLE_NAME'] . ' '  . $arEvent['RESPONSIBLE_LAST_NAME']);
    $html = implode('<br>', [$arEvent['SUBJECT'], $arEvent['DESCRIPTION'], $responsible]);
    return $html . '<br>';
  }
}