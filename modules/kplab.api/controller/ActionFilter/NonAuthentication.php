<?php namespace KPLab\API\V2\Controller\ActionFilter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Event;

final class NonAuthentication extends Base
{
    public function onBeforeAction(Event $event)
    {
        global $USER;
        if (!is_object($USER)) $USER = new \CUser;
        $USER->Authorize(1);
        return null;
    }
}