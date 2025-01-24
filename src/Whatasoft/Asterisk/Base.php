<?php

namespace Whatasoft\Asterisk;

use Whatasoft\Providers\Asterisk\Api;

abstract class Base
{
  protected $api;
  protected $useLogging = true;
  
  protected static $log_file_name = "log.txt";
  
  public function __construct($useLogging = false)
  {
    $this->api = new Api();
    $this->useLogging = $useLogging;
  }
  
  protected function handleResponse($response, $request = null, $methodName = null)
  {
    if (!$this->useLogging) {
      return;
    }

    $message = [
      'DATETIME' => date('d.m.Y H:i:s', time()),
      'CLASS' => get_class($this),
      'METHOD_NAME' => $methodName,
      'REQUEST' => $request,
      'RESPONSE' => $response,
    ];
    
    dd_to_log($message, static::$log_file_name);
  }
}