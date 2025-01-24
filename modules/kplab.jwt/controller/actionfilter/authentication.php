<? namespace KPLab\JWT\Controller\ActionFilter;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Web\JWT;

define("INVALID_TOKEN", 401);
define("KEY", "3lsxt0qfwbns0wve");

final class Authentication extends Base
{

	public function onBeforeAction(Event $event)
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		$authorization = $server->get('REMOTE_USER');
		$token = str_replace('Bearer ', '', $authorization);

		\CModule::IncludeModule('kplab.jwt');

		if ($token) {

			$responseDecodeJWT = JWT::decode($token,"3lsxt0qfwbns0wve",["HS256"]);

			if ($responseDecodeJWT == null) {
				$this->addError(new Error('User not found', 401));
				return new EventResult(EventResult::ERROR, '', 'kplab.jwt', $this);
			} else {


				global $USER;
				if (!is_object($USER)) $USER = new \CUser;
				// по умолчанию авторизация из-под админа
				$USER->Authorize(1);
			}
		}

		return null;
	}
}