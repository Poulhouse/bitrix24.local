<?php

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

define("LOG_API_APP", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/testBotApi.log");

global $USER;
if (!is_object($USER))
    $USER = new \CUser;
// по умолчанию авторизация из-под админа
$USER->Authorize(1);

$context = Application::getInstance()->getContext();
$request = $context->getRequest();
$server = $context->getServer();


Logs\File::AddMessage($server, "server", LOG_API_APP);
Logs\File::AddMessage($request, "request", LOG_API_APP);