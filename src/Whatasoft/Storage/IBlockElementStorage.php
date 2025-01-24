<?php

namespace Whatasoft\Storage;

use Whatasoft\Helpers\Traits\ModuleLoader;

class IBlockElementStorage
{
  use ModuleLoader;
  
  private $requiredModules = ['iblock'];
  private $storage;
  private $iBlockId;
  
  public function __construct($iBlockId)
  {
    $this->iBlockId = $iBlockId;
    $this->includeModules();
    $this->storage = $this->getStorage();
  }
  
  public function getStorage()
  {
    return new \CIBlockElement(false);
  }
  
  public function getFiltered(
    array $arOrder = [],
    array $arFilter = [],
    array $arSelect = ['*'],
    array $arNavParams = [])
  {
    $arFilter['IBLOCK_ID'] = $this->iBlockId;
    $navParams = count($arNavParams) ? $arNavParams : false;
    return $this->storage->GetList($arOrder, $arFilter, false, $navParams, $arSelect);
  }
  
  public function create(array $fields, array $props)
  {
    $arFields = array_merge($fields, ['PROPERTY_VALUES' => $props]);
    $arFields['IBLOCK_ID'] = $this->iBlockId;
    $id = $this->storage->Add($arFields);

    if (!$id) {
      throw new \Exception($this->storage->LAST_ERROR);
    }
    
    return $id;
  }
  
  public function update($id, array $fields, array $props = [])
  {
    $success = true;
    
    if (count($fields)) {
      $success = $this->storage->Update($id, $fields);
    }

    if (!$success) {
      throw new \Exception($this->storage->LAST_ERROR);
    }
    
    if (count($props)) {
      $this->storage->SetPropertyValuesEx($id, $this->iBlockId, $props);
    }
    
    return $success;
  }
  
  public function delete($id)
  {
    $this->storage->Delete($id);
  }
}