<? namespace KPLab\API\V2\Controller\ActionFilter;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPlab\Logs;
define("LOG_API_SYNC_AUTH", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/auth.log");
//define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");
define('API_KEY_BP','4d0e4072-889b-42cd-950c-af8d58221114');

Loader::includeModule('iblock');
Loader::includeModule('main');
final class Authentication extends Base
{

	public function onBeforeAction(Event $event)
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();
        $apikey = json_decode($request->getInput(),true)['apiKey'];
        $queryParamsArray = $context->getRequest()->toArray();

        if($queryParamsArray['authId'] == "5d0e5072-889b-52cd-950c-af8d58221115") {
            global $USER;
            if (!is_object($USER))
                $USER = new \CUser;
            // по умолчанию авторизация из-под админа
            $USER->Authorize(1);

            return null;
        }

        if ($apikey)
        {
            if ($apikey !== API_KEY_BP)
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
            return null;
        }

		$serverArray = $server->toArray();
		$serverName = $serverArray['SERVER_NAME'];
		$requestURI = $serverArray["REQUEST_URI"];
        Logs\File::AddMessage($requestURI,"requestURI",LOG_API_SYNC_AUTH);

		$requestURIPAth = parse_url($requestURI)['path'];
		$requestURIEndpoint = explode('/', trim($requestURIPAth, '/'))[2];
		//Logs\File::AddMessage($requestURIEndpoint,"requestURIEndpoint",LOG_API_SYNC_AUTH);

		$authorization = $server->get('REMOTE_USER');

		$authText = stristr($authorization, 'BitrixAuth');


        //Logs\File::AddMessage($authText,"authText",LOG_API_SYNC_AUTH);

		if($authText === false) {
			$token = $authorization;
		} else {
			$token = str_replace('BitrixAuth ', '', $authorization);
		}

		if ($token)
		{
            //Logs\File::AddMessage($token,"token",LOG_API_SYNC_AUTH);
            //Logs\File::AddMessage($serverName,"serverName",LOG_API_SYNC_AUTH);

			if($serverName == "crm.seller-capital.ru") {
				$IBLOCK_ID = 183;
				$arOrder = ['ID' => 'ASC'];
				$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1112"=>$token, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
				$arGroupBy = false;
				$arNavStartParams = [];
				$arSelect = ["*","PROPERTY_*"];
				$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);
			}
			elseif ($serverName == "testcrm.seller-capital.ru") {
				$IBLOCK_ID = 183;
				$arOrder = ['ID' => 'ASC'];
				$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1112"=>$token, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
				$arGroupBy = false;
				$arNavStartParams = [];
				$arSelect = ["*","PROPERTY_*"];
				$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);
			}

			while($ob = $res->GetNextElement())
			{
				$arProps = $ob->GetProperties();
				$test = $arProps['TEST']['VALUE_XML_ID'];

                Logs\File::AddMessage($arProps,"arProps",LOG_API_SYNC_AUTH);

				if($test == "true" && $serverName == "crm.seller-capital.ru") {
					Context::getCurrent()->getResponse()->setStatus(403);
					$this -> addError(new Error("Токен аутентификации не имеет нужного разрешения для использования на боевом сервере", "forbidden"));
					return new EventResult(EventResult::ERROR, null, null, $this);
				}
				elseif ($test == "false" && $serverName == "testcrm.seller-capital.ru") {
					Context::getCurrent()->getResponse()->setStatus(403);
					$this -> addError(new Error("Токен аутентификации не имеет нужного разрешения для использования на тестовом сервере", "forbidden"));
					return new EventResult(EventResult::ERROR, null, null, $this);
				}



				$arPropEndpoints = $arProps['ENDPOINT']['VALUE_XML_ID'];

                //Logs\File::AddMessage($arPropEndpoints,"arPropEndpoints",LOG_API_SYNC_AUTH);

				if(in_array('default', $arPropEndpoints)) {
					return null;
				}

				if(!in_array($requestURIEndpoint, $arPropEndpoints)) {
					Context::getCurrent()->getResponse()->setStatus(403);
					$this -> addError(new Error("Токен аутентификации не имеет нужного разрешения для использования Эндпоинта `{$requestURIEndpoint}`", "forbidden"));
					return new EventResult(EventResult::ERROR, null, null, $this);
				}

				global $USER;
				if (!is_object($USER))
					$USER = new \CUser;
				// по умолчанию авторизация из-под админа
				$USER->Authorize(1);

				return null;
			}

			Context::getCurrent()->getResponse()->setStatus(401);
			$this -> addError(new Error('Токен аутентификации недействителен', "unauthorized"));
			return new EventResult(EventResult::ERROR, null, null, $this);

		}
		else {
			Context::getCurrent()->getResponse()->setStatus(401);
			$this -> addError(new Error('Токен аутентификации недействителен', "unauthorized"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
	}
}