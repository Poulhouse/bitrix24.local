<?php
namespace KPLab\GitBx\Api\ActionFilter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use KPLab\GitBx\Api\Auth;

class Authentication extends Base
{
    public function onBeforeAction(Event $event)
    {
        // Проверяем токен
        if (!Auth::checkBearerToken()) {

            // Отдаём корректный JSON-ответ Bitrix API
            $this->addError(new Error(
                'invalid_token',
                'unauthorized'
            ));

            $response = Context::getCurrent()->getResponse();
            $response->setStatus(401);

            return new EventResult(
                EventResult::ERROR,
                null,
                null,
                $this
            );
        }

        // OK
        return null;
    }
}
