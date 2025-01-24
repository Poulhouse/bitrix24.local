<?php

namespace Whatasoft\Asterisk;

use Whatasoft\Providers\Asterisk\Api;

// Обёртка над API Asterisk для добавления/изъятия операторов и телефонов из очередей
class Queue extends Base
{
  // Кол-во телефонов в одном запросе
  const SINGLE_REQUEST_PHONE_LIMIT = 5;
  
  protected static $log_file_name = "call_queue.txt";
  
  public function addPhonesToQueue($queueId, array $phones)
  {
    $limitedRequestPhones = $this->getSlicedArray($phones, self::SINGLE_REQUEST_PHONE_LIMIT);
    foreach($limitedRequestPhones as $singleRequestPhones) {
      $request = ['QUEUE_ID' => $queueId, 'PHONE' => $singleRequestPhones];
      $response = $this->api->addPhonesToQueue($queueId, $singleRequestPhones);
      $this->handleResponse($response, $request, __FUNCTION__);
    }
  }
  
  public function removePhonesFromQueue($queueId, array $phones)
  {
    $limitedRequestPhones = $this->getSlicedArray($phones, self::SINGLE_REQUEST_PHONE_LIMIT);
    foreach($limitedRequestPhones as $singleRequestPhones) {
      $request = ['QUEUE_ID' => $queueId, 'PHONE' => $singleRequestPhones];
      $response = $this->api->removePhonesFromQueue($queueId, $singleRequestPhones);
      $this->handleResponse($response, $request, __FUNCTION__);
    }
  }
  
  public function addOperatorToAsteriskQueues($operatorId, array $asteriskQueueIds)
  {
    foreach ($asteriskQueueIds as $asteriskQueueId) {
      $request = ['OPERATOR_ID' => $operatorId, 'ASTERISK_QUEUE_ID' => $asteriskQueueId];
      $response = $this->api->registerOperatorInQueue($operatorId, $asteriskQueueId);
      $this->handleResponse($response, $request, __FUNCTION__);
    }
  }
  
  public function removeOperatorFromAsteriskQueues($operatorId, array $asteriskQueueIds)
  {
    foreach ($asteriskQueueIds as $asteriskQueueId) {
      $request = ['OPERATOR_ID' => $operatorId, 'ASTERISK_QUEUE_ID' => $asteriskQueueId];
      $response = $this->api->removeOperatorFromQueue($operatorId, $asteriskQueueId);
      $this->handleResponse($response, $request, __FUNCTION__);
    }
  }
  
  protected function getSlicedArray(array $phones, int $sliceSize)
  {
    $slicedArray = [];
    $offset = 0;
    while (count($arSlice = array_slice($phones, $offset, $sliceSize))) {
      $slicedArray[] = $arSlice;
      $offset += $sliceSize;
    }
    
    return $slicedArray;
  }
}