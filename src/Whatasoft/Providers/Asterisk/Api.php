<?php

namespace Whatasoft\Providers\Asterisk;

class Api
{
  private $request;
  
  public function __construct()
  {
    $this->request = new Request();
  }
  
  // Возвращает список очередей в Asterisk
  public function getQueueList()
  {
    $queryParams = [
      'action' => 'getdata',
      'type' => 'queue',
    ];
    
    $json = $this->request->get($queryParams);
    $list = json_decode($json, true);
    
    if (!is_array($list)) {
      throw new \Exception('Bad API response');
    }
    
    return $list;
  }
  
  // Возвращает список очередей в Asterisk для входящих звонков
  public function getIncomingQueueList()
  {
    $queryParams = [
      'action' => 'getdata',
      'type' => 'ivr',
    ];
    
    $json = $this->request->get($queryParams);
    $list = json_decode($json, true);
    
    if (!is_array($list)) {
      throw new \Exception('Bad API response');
    }
    
    return $list;
  }
  
  /**
   * Добавляет список телефонов в очередь обзвона Asterisk
   * @param $queueId - Внутрений ID очереди Asterisk (Полученый через API)
   * @param array $phones - список телефонов
   */
  public function addPhonesToQueue($queueId, array $phones)
  {
    $params = [
      'action' => 'setaction',
      'type' => 'initcall',
      'src' => $queueId,
      'dst' => $phones,
    ];
    
    return $this->request->post($params);
  }
  
  /**
   * Изымает список телефонов из очереди обзвона Asterisk
   * @param $queueId - Внутрений ID очереди Asterisk (Полученый через API)
   * @param array $phones - список телефонов
   */
  public function removePhonesFromQueue($queueId, array $phones)
  {
    $params = [
      'action' => 'setaction',
      'type' => 'hangupcall',
      'src' => $queueId,
      'dst' => $phones,
    ];
    
    return $this->request->post($params);
  }
  
  /**
   * Добавляет оператора в очередь обзвона Asterisk 
   * @param $operatorId - Внутрений ID оператора Asterisk
   * @param $queueId - Внутрений ID очереди Asterisk
   */
  public function registerOperatorInQueue($operatorId, $queueId)
  {
    $params = [
      'action' => 'setaction',
      'type' => 'addmember',
      'queue' => $queueId,
      'members' => [$operatorId],
    ];
	
    return $this->request->post($params);
  }
  
  /**
   * Изымает оператора из очереди обзвона Asterisk 
   * @param $operatorId - Внутрений ID оператора Asterisk
   * @param $queueId - Внутрений ID очереди Asterisk
   */
  public function removeOperatorFromQueue($operatorId, $queueId)
  {
    $params = [
      'action' => 'setaction',
      'type' => 'delmember',
      'queue' => $queueId,
      'members' => [$operatorId],
    ];
    
    return $this->request->post($params);
  }
  
  /**
   * Перевод звонка с сопровождением
   * @param $bxUserIdFrom - ID пользователя Bitrix от которого совершается перевод
   * @param $to - ID пользователя Bitrix или номер телефона для перевода
   */
  public function callTransferWithAccompaniment($bxUserIdFrom, $to)
  {
    $methodUri = 'CallMeAtTransfer.php';
    $queryParams = [
      'id_in' => $bxUserIdFrom,
      'id_out' => $to,
    ];
    return $this->request->transfer_get_request($methodUri, $queryParams);
  }
  
  /**
   * Перевод звонка без сопровождения
   * @param $bxUserIdFrom - ID пользователя Bitrix от которого совершается перевод
   * @param $to - ID пользователя Bitrix или номер телефона для перевода
   */
  public function callTransferWithoutAccompaniment($bxUserIdFrom, $to)
  {
    $methodUri = 'CallMeBlTransfer.php';
    $queryParams = [
      'id_in' => $bxUserIdFrom,
      'id_out' => $to,
    ];
    return $this->request->transfer_get_request($methodUri, $queryParams);
  }
}