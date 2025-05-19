<?php namespace KPLab;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

define('API_KEY','4d0e4072-889b-42cd-950c-af8d58221114');

class Authentication extends Base
{
	public function onBeforeAction(Event $event)
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		$apikey = json_decode($request->getInput(),true)['apiKey'];

		if ($apikey)
		{
			if ($apikey !== '4d0e4072-889b-42cd-950c-af8d58221114')
			{
				$this -> addError(new Error('API key not found', 401));
				return new EventResult(EventResult::ERROR, '', '', $this);
			} else
			{
				global $USER;
				if (!is_object($USER))
					$USER = new \CUser;
				// по умолчанию авторизация из-под админа
				$USER->Authorize(1);
			}
		}

		return null;
	}
}
