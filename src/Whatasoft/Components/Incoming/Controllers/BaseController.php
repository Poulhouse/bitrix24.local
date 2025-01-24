<?php

namespace \Whatasoft\Components\Incoming\Controllers;

use Whatasoft\Helpers\Traits\ComponentModuleLoader;
use Whatasoft\Helpers\Http\Request;

class BaseController
{
  use ComponentModuleLoader;
  
  protected $request;
  protected $requiredModules;
  
  public function __construct()
  {
    $this->setRequiredModules();
    $this->setRequest();
    $this->includeModules();
  }
  
  protected function setRequiredModules()
  {
    $this->requiredModules = ['crm', 'iblock'];
  }
  
  protected function setRequest()
  {
    $this->request = new Request();
  }
}