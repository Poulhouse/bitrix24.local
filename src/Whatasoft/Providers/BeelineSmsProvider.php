<?php

namespace Whatasoft\Providers;

use Whatasoft\Beeline\BeeSms;

class BeelineSmsProvider implements ISmsProvider 
{
  const MAX_MESSAGE_SIZE = 480;
  
  protected $sender;
  protected $provider;
  
  public function __construct()
  {
    $this->sender = BEELINE_SENDER_NAME;
    $this->provider = $this->getProvider();
  }
  
  public function setSender($sender)
  {
    $this->sender = $sender;
  }
  
  public function send($phone, $message)
  {
    $phone = $this->getValidatedPhone($phone);
    $message = substr($message, 0, self::MAX_MESSAGE_SIZE);
    $sender = $this->sender;

    if (!strlen($sender)) {
      throw new \Exception("Не установлен отправитель SMS сообщения");
    }
    
    if (BEELINE_SMS_ACTIVE) {
      return $this->provider->post_message($message, $phone, $this->sender);
    }
    
    throw new \Exception('Отправка SMS сообщений Beeline выключена в настройках');
  }
  
  public function getSmsStatus($smsId)
  {
    return $this->provider->status_sms_id($smsId);
  }
  
  public function getValidatedPhone($phone)
  {
    $digits = $this->getOnlyDigits($phone);
    
    switch(strlen($digits)) {
      case 11: 
        //89999999999 -> +79999999999
        return '+7' . substr($digits, 1, 10);
      case 10:
        //9999999999 -> +79999999999
        return '+7' . $digits;
      default:
        throw new \Exception("Неверный формат телефона");
    }
  }
  
  private function getOnlyDigits($string)
  {
    return preg_replace('/\D/', '', $string);
  }
  
  public function getProvider()
  {
    return new BeeSms(BEELINE_LOGIN, BEELINE_PASSWORD, BEELINE_HOSTNAME);
  }
}