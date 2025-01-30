<?php

namespace KPLab\API\V2;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use KPLab\Logs;
define("LOG_API_SYNC_EVENT", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/EventHandler.log");
class EventHandler
{
	public static function disableBitrixAuth()
	{
		try {
			$route = \Bitrix\Main\Application::getInstance()->getCurrentRoute();
			$c = $route->getController();
		} catch (\Throwable $exception) {
			// direct php file doesnt have route
			return;
		}

		if (is_array($c) && !empty($c[0])) {
			//Logs\File::AddMessage($c,"route->getController()", LOG_API_SYNC_EVENT);
			$controller_name = $c[0];
			$is_my_controller = str_starts_with($controller_name, 'KPLab\API\V2\Controller');

			$context = Application::getInstance()->getContext();
			$request = $context->getRequest();
			$server = $context->getServer();
			$authorization = $server->get('REMOTE_USER');
			$token = str_replace('BitrixAuth ', '', $authorization);

			if ($is_my_controller && !empty($token)) { //
				define("NOT_CHECK_PERMISSIONS", true);
				//Logs\File::AddMessage(NOT_CHECK_PERMISSIONS,"API NOT_CHECK_PERMISSIONS disableBitrixAuth", LOG_API_SYNC_EVENT);
			}
		}
	}
}