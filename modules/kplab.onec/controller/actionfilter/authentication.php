<? namespace KPLab\OneC\Controller\ActionFilter;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Web\JWT;

define("TOKEN_ONEC_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwb2ludCI6IjFDX0JYIn0.pjioDJHkvil35XIgncYS4FZZso0wx4Vodi-P-Ul7uYc");

final class Authentication extends Base
{

	public function onBeforeAction(Event $event)
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$apikey = json_decode($request->getInput(),true)['token'];

		if ($apikey)
		{
			if ($apikey !== TOKEN_ONEC_KEY)
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