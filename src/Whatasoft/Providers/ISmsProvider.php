<?php

namespace Whatasoft\Providers;

interface ISmsProvider {
  
  public function send($phone, $message);
  
}