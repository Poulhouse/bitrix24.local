<?php

namespace Whatasoft\Mail;

use Whatasoft\Providers\ISmsProvider;

class CrmSmsSender extends CrmMailer {
  
  private $provider;
  
  public function __construct(ISmsProvider $provider)
  {
    $this->provider = $provider;
  }
  
  public function send($phone, $templateId) 
  {
    $template = $this->getTemplateById($templateId);
    
    if (empty($template)) {
      throw new \Exception("Почтовый шаблон не найден");
    }

    $message = trim($template['~BODY']);
    
    if (!strlen($message)) {
      throw new \Exception("Тело почтового шаблона не заполнено");
    }        
    
    return $this->provider->send($phone, $message);
  }
}