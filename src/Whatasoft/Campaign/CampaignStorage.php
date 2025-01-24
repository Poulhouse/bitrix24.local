<?php

namespace Whatasoft\Campaign;

use Whatasoft\Helpers\Traits\ModuleLoader;

class CampaignStorage
{
  use ModuleLoader;
  
  private $requiredModules = ['iblock'];
  private $storage;
  
  public function __construct()
  {
    $this->includeModules();
    $this->storage = $this->getStorage();
  }
  
  public function getStorage()
  {
    return new \CIBlockElement(false);
  }
  
  public function getById($id, $default = null)
  {
    $arFilter = ['ID' => $id];
    $arResult = $this->getFiltered([], $arFilter)->Fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getIBlockElementById($id)
  {
    $arFilter = ['ID' => $id];
    return $this->getFiltered([], $arFilter)->GetNextElement();
  }
  
  public function create(array $fields, array $props)
  {
    $arFields = array_merge($fields, ['PROPERTY_VALUES' => $props]);
    $arFields['IBLOCK_ID'] = IBLOCK_CAMPAIGN_ID;
    $id = $this->storage->Add($arFields);

    if (!$id) {
      throw new \Exception($this->storage->LAST_ERROR);
    }
    
    return $id;
  }
  
  public function update($id, array $fields = [], array $props = [])
  {
      $success = true;

      if (count($fields)) {
          $success = $this->storage->Update($id, $fields);
      }

      if (!$success) {
          throw new \Exception($this->storage->LAST_ERROR);
      }

      if (count($props)) {
          $this->storage->SetPropertyValuesEx($id, IBLOCK_CAMPAIGN_ID, $props);
      }

      return $success;
  }
  
  public function getFiltered(
    array $arOrder = [],
    array $arFilter = [],
    array $arSelect = ['*'],
    array $arNavParams = [])
  {
    $arFilter['IBLOCK_ID'] = IBLOCK_CAMPAIGN_ID;
    $navParams = count($arNavParams) ? $arNavParams : false;
    return $this->storage->GetList($arOrder, $arFilter, false, $navParams, $arSelect);
  }
  
  public function getCount(array $arFilter = [])
  {
    return $this->getFiltered([], $arFilter, ['ID'])->SelectedRowsCount();
  }
}