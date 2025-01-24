<?php

namespace Whatasoft\Helpers;

class Phone
{
  
  public static function getOnlyDigits(string $phone)
  {
    return preg_replace("/[^0-9]/", "", $phone);
  }
  
  public static function standardizeMobileNumber(string $phone)
  {
    $digits = self::getOnlyDigits($phone);
    
    switch(strlen($digits)) {
      case 10:
        return '+7' . $digits;
      case 11:
        return '+7' . substr($digits, 1, 10);
      default:
        return false;
    }
  }
}