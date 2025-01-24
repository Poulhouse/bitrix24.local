<?php

namespace Whatasoft\Asterisk;

use Whatasoft\Providers\Asterisk\Api;
use Whatasoft\Storage\IBlockElementStorage;

class CallQueueSync
{
  private $api;
  private $storage;
  
  public function __construct()
  {
    $this->api = new Api();
    $this->storage = new IBlockElementStorage(IBLOCK_ASTERISK_ID);
  }
  
  public function sync()
  {
    try {	
      $asteriskQueueList = $this->api->getQueueList();

      $asteriskQueueIdList = array_keys($asteriskQueueList);
      $arOrder = [];
      $arFilter = [
		  // 'PROPERTY_ASTERISK_QUEUE_ID' => $asteriskQueueIdList, 
      ];
      $arSelect = [
        'ID', 'NAME', 'PROPERTY_ASTERISK_QUEUE_ID',
      ];
      $arResult = [];
      $dbResult = $this->storage->getFiltered($arOrder, $arFilter, $arSelect);
	  $rowToRename = [];
	  $queueListToDelete = [];
      while($arRow = $dbResult->GetNext()) {
        $asteriskQueueId = $arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE'] ?? null;
        $arResult[$asteriskQueueId] = $arRow;
		  if (isset($asteriskQueueList[$arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE']])
			&& $arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE'] . ' ' . $asteriskQueueList[$arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE']] !=  $arRow['NAME']
			){
				$rowToRename[$arRow['ID']] = $arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE'] . ' ' . $asteriskQueueList[$arRow['PROPERTY_ASTERISK_QUEUE_ID_VALUE']] ;
		  }
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
		foreach($rowToRename as $id=>$name){
			$this->renameQueue($id, $name);
		}

    } catch(\Exception $exception) {
      
    }
  }
  
  private function createQueue($asteriskQueueId, $name)
  {
    $fields = ['NAME' => $name];
    $props = ['ASTERISK_QUEUE_ID' => $asteriskQueueId];
    return $this->storage->create($fields, $props);
  }
	private function renameQueue($id, $name){
 	$fields = ['NAME' => $name];
    return $this->storage->update($id, $fields);
	}
  private function deleteQueue($id)
  {
    return $this->storage->delete($id);
  }
}