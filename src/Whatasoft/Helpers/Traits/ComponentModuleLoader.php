<?php

namespace Whatasoft\Helpers\Traits;

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;

trait ComponentModuleLoader
{
  protected function includeModules()
  {
    if (!isset($this->requiredModules)) {
      return;
    }
    
    if (!is_array($this->requiredModules)) {
      return;
    }
    
    foreach ($this->requiredModules as $moduleName) {
      if (!Loader::includeModule($moduleName)) {
        throw new SystemException("Cannot load {$moduleName} module");
      }
    }
  }
}