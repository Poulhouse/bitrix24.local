<?php

namespace KPLab\OneC;

use Bitrix\Main\Application;

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
			$controller_name = $c[0];
			$is_my_controller = str_starts_with($controller_name, 'KPLab\OneC\Controller');

			//$context = Application::getInstance()->getContext();
			//$request = $context->getRequest();
			//$server = $context->getServer();

			//$authorization = $server->get('REMOTE_USER');
			//$token = str_replace('Bearer ', '', $authorization);

			if ($is_my_controller) { //
				define("NOT_CHECK_PERMISSIONS", true);
				//AddMessage2Log(NOT_CHECK_PERMISSIONS,"API NOT_CHECK_PERMISSIONS disableBitrixAuth");
			}
			/*
			if ($is_my_controller && !empty($token)) { //
				define("NOT_CHECK_PERMISSIONS", true);
				//AddMessage2Log(NOT_CHECK_PERMISSIONS,"API NOT_CHECK_PERMISSIONS disableBitrixAuth");
			}
			*/
		}
	}
}