<?php

namespace Whatasoft\Asterisk;

use Whatasoft\Providers\Asterisk\Api;
use Whatasoft\Storage\IBlockElementStorage;

class IncomingQueueSync
{
  private $api;
  private $storage;
  
  public function __construct()
  {
    $this->api = new Api();
    $this->storage = new IBlockElementStorage(IBLOCK_INCOMING_ASTERISK_QUEUE_ID);
  }
  
  public function sync()
  {
    try {
      $apiQueueList = $this->api->getIncomingQueueList();
      $asteriskQueueList = $this->reformatIncomingQueueList($apiQueueList);
      $asteriskQueueIdList = array_keys($asteriskQueueList);
      $arOrder = [];
      $arFilter = [
        'PROPERTY_ASTERISK_QUEUE_ID' => $asteriskQueueIdList, 
      ];
      $arSelect = [
        'ID', 'NAME', 'PROPERTY_ASTERISK_QUEUE_ID',
      ];
      $arResult = [];
      $dbResult = $this->storage->getFiltered($arOrder, $arFilter, $arSelect);
      
      while($arRow = $dbResult->GetNext()) {
        $asteriskQueueId = $arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE'] ?? null;
        $arResult[$asteriskQueueId] = $arRow;
      }
      
      $existAsteriskQueueIdList = array_keys($arResult);
      $queueListToCreate = array_diff($asteriskQueueIdList, $existAsteriskQueueIdList);
      $queueListToDelete = array_diff($existAsteriskQueueIdList, $asteriskQueueIdList);

      foreach($queueListToCreate as $asteriskQueueId) {
        $queueName = $asteriskQueueList[$asteriskQueueId];
        $this->createQueue($asteriskQueueId, $queueName);
      }
      
      foreach($queueListToDelete as $asteriskQueueId) {
        $id = $arResult[$asteriskQueueId]['ID'];
        $this->deleteQueue($id);
      }
      
    } catch(\Exception $exception) {
      
    }
  }
  
  private function reformatIncomingQueueList(array $list)
  {
    $requiredArrayKeys = ['type', 'name', 'number'];
    
    $arQueueList = [];
    
    // Выбираем только те элементы, которые являются массивами
    $arFilteredItems = array_map(function($arItem) {
      return array_filter($arItem, function($arItemContains) {
        return is_array($arItemContains);
      });
    }, $list);
    
    $isValidItem = function($arItem) {
      return !empty($arItem['name']) &&
             !empty($arItem['number']) &&
             !empty($arItem['type']);
    };
    
    // Собираем такие элементы в один массив
    foreach($arFilteredItems as $arItem) {
      $arQueueList = array_merge($arQueueList, array_values($arItem));
    }
    
    $arFormattedQueue = [];
    // Оставляем только те элементы в которых есть все необходимые ключи
    $arQueueList = array_filter($arQueueList, $isValidItem);
    
    // Форматируем список по типу ID -> Название
    foreach($arQueueList as $arItem) {
      $arFormattedQueue[$arItem['number']] = $arItem['name'];
    }
    
    return $arFormattedQueue;
  }
  
  private function createQueue($asteriskQueueId, $name)
  {
    $fields = ['NAME' => $asteriskQueueId . ' ' . $name];
    $props = ['ASTERISK_QUEUE_ID' => $asteriskQueueId];
    return $this->storage->create($fields, $props);
  }
  
  private function deleteQueue($id)
  {
    return $this->storage->delete($id);
  }
}