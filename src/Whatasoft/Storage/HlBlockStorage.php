<?php

namespace Whatasoft\Storage;

use Bitrix\Highloadblock\HighloadBlockTable;
use Whatasoft\Helpers\Traits\ModuleLoader;

class HlBlockStorage 
{
  use ModuleLoader;
  
  private $requiredModules = ['highloadblock'];
  private $storage;
  private $hlBlockId;
  
  public function __construct($hlBlockId)
  {
    $this->hlBlockId = $hlBlockId;
    $this->includeModules();
    $this->storage = $this->getStorage();
  }
  
  public function getStorage()
  {
    $arFilter = ['ID' => $this->hlBlockId];
    $arParams = ['filter' => $arFilter];
    $block = HighloadBlockTable::getList($arParams)->fetch();
    $entity = HighloadBlockTable::compileEntity($block);
    return $entity->getDataClass();
  }
  
  public function create(array $arFields)
  {
    $success = $this->storage::add($arFields);
    return $success->isSuccess() ? $success->getId() : false;
  }
  
  public function update($id, array $arFields)
  {
    $success = $this->storage::update($id, $arFields);
    return $success->isSuccess() ? $success->getId() : false;
  }
  
  public function delete($id)
  {
    return $this->storage::delete($id);
  }
  
  public function getById($id, array $arSelect = ['*'])
  {
    $arFilter = ['ID' => $id];
    return $this->getFiltered([], $arFilter, $arSelect)->fetch();
  }
  
  public function getFiltered(
    array $arOrder = [],
    array $arFilter = [],
    array $arSelect = ['*'],
    array $arCustomParams = [])
  {
    $arParams = [
      'select' => $arSelect,
      'filter' => $arFilter,
      'order' => $arOrder,
    ];
    
    $arParams = array_merge($arParams, $arCustomParams);
    
    return $this->storage::getList($arParams);
  }
  
  public function getCount(array $arFilter = [])
  {
    return (int)$this->storage::getCount($arFilter);
  }
}