<?php

namespace Whatasoft\Asterisk;

use Whatasoft\Providers\Asterisk\Api;

class CallTransfer extends Base
{
  protected static $log_file_name = "call_transfer_log.txt";
  
  /**
   * Перевод звонка с сопровождением
   * @param $bxUserIdFrom - ID пользователя Bitrix от которого совершается перевод
   * @param $to - ID пользователя Bitrix или номер телефона для перевода
   */
  public function callTransferWithAccompaniment($bxUserIdFrom, $to)
  {
    $request = ['from' => $bxUserIdFrom, 'to' => $to];
    $response = $this->api->callTransferWithAccompaniment($bxUserIdFrom, $to);
    $this->handleResponse($response, $request, __FUNCTION__);
    return $response;
  }
  
  /**
   * Перевод звонка без сопровождения
   * @param $bxUserIdFrom - ID пользователя Bitrix от которого совершается перевод
   * @param $to - ID пользователя Bitrix или номер телефона для перевода
   */
  public function callTransferWithoutAccompaniment($bxUserIdFrom, $to)
  {
    $request = ['from' => $bxUserIdFrom, 'to' => $to];
    $response = $this->api->callTransferWithoutAccompaniment($bxUserIdFrom, $to);
    $this->handleResponse($response, $request, __FUNCTION__);
    return $response;
  }
}