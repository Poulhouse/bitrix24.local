<?php

namespace Whatasoft\Helpers\Traits;

use Bitrix\Main\Loader;

trait ModuleLoader 
{
  protected function includeModules()
  {
    if (isset($this->requiredModules) && is_array($this->requiredModules)) {
      foreach($this->requiredModules as $moduleName) {
        Loader::includeModule($moduleName);
      }
    }
  }
}