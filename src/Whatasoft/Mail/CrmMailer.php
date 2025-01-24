<?php

namespace Whatasoft\Mail;

use \Bitrix\Main\Loader;

class CrmMailer {
  
  const SMS_TEMPLATE_PREFIX = 'SMS';
  
  protected $requiredModules = ['crm'];
  
  private $obUser;
  
  public function __construct()
  {
    $obUser = new \CUser();
    $this->includeModules();
  }
  
  private function includeModules()
  {
    foreach ($this->$requiredModules as $moduleName) {
      Loader::includeModule($moduleName);
    }
  }
  
  public function getEmailTemplates($arSelect = ["*"], $arFilter = [], $checkPermission = false)
  {
    $arFilter["!@ID"] = array_keys($this->getSmsTemplates(['ID']));
    return $this->getCrmEmailTemplates($arSelect, $arFilter, $checkPermission);
  }
  
  public function getSmsTemplates($arSelect = ["*"], $arFilter = [], $checkPermission = false)
  {
    $arFilter["%=TITLE"] = self::SMS_TEMPLATE_PREFIX . "%";
    return $this->getCrmEmailTemplates($arSelect, $arFilter, $checkPermission);
  }
  
  public function getTemplateById($id, $checkPermission = false, $arSelect = ["*"])
  {
    $arFilter['=ID'] = $id;
    $arTemplate = $this->getCrmEmailTemplates($arSelect, $arFilter, $checkPermission);
    return (is_array($arTemplate) && count($arTemplate)) ? array_shift($arTemplate) : null;
  }
  
  private function getCrmEmailTemplates($arSelect = ["*"], $arFilter = [], $checkPermission = false)
  {
    if ($checkPermission) {
      $arFilter['=OWNER_ID'] = $obUser->GetID();
    }
    
    $arSelect = array_merge($arSelect, ['ID']);
    $arTemplates = [];
    $dbResult = \CCrmMailTemplate::GetList([], $arFilter, false, false, $arSelect);
    
    while ($arRow = $dbResult->GetNext()) {
      $arTemplates[$arRow['ID']] = $arRow;
    }
    
    return $arTemplates;
  }
}