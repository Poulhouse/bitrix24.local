<?php

namespace KPLab\OneC;

use Bitrix\Main\Application;
use Bitrix\Main\Event;

class Authentication extends \Bitrix\Main\Engine\ActionFilter\Base
{
    public function onBeforeAction(Event $event)
    {
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();

        global $USER;
        if (!is_object($USER))
            $USER = new \CUser;
        // по умолчанию авторизация из-под админа
        $USER->Authorize(1);

        return null;
    }
}