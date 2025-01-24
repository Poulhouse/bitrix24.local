<?php

namespace Whatasoft\Mail;

use Whatasoft\Statistic\Helpers\CrmFieldManager;

class CrmEmailSender extends CrmMailer {
  
  const EMAIL_EVENT_CODE = 'PHONECARD_EMAIL_SEND';
  
  public function send($managerId, $to, $templateId) {
    $crmFieldManager = new CrmFieldManager();
    $template = $this->getTemplateById($templateId);
    $arManager = $crmFieldManager->getManagerById($managerId, []);
    $managerEmail = $arManager['EMAIL'] ?? '';
    
    if (empty($template)) {
      throw new \Exception("Почтовый шаблон не найден");
    }
    
    $defaultSiteEmail = \COption::GetOptionString('main', 'email_from', '');
    $templateBody = trim($template['~BODY']);
    $templateSubject = trim($template['SUBJECT'] ?? '');
    $emailFrom = trim($template['EMAIL_FROM'] ?? '');
    $emailFrom = (strlen($emailFrom)) ? $emailFrom : $managerEmail;
    $emailTo = trim($to);
    
    if (!strlen($templateBody)) {
      throw new \Exception("Пустой шаблон письма");
    }
    
    if (!strlen($emailFrom)) {
      throw new \Exception("Не указан отправитель письма");
    }
    
    if (!strlen($emailTo)) {
      throw new \Exception("Не указан получатель письма");
    }
    
    $arEventFields = [
      'EMAIL_SUBJECT' => $templateSubject,
      'EMAIL_BODY' => $templateBody,
      'EMAIL_FROM' => $emailFrom,
      'EMAIL_TO' => $emailTo,
    ];

    return \CEvent::Send(self::EMAIL_EVENT_CODE, SITE_ID, $arEventFields);
  }
  
}