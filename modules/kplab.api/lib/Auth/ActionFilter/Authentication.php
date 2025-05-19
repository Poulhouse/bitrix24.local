<?php namespace KPLab\API\V2\Auth\ActionFilter;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\API\V2\Model\ORM\ApiKeysTable;
use KPlab\Logs;

define("LOG_API_SYNC_AUTH", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/auth.log");
//define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");
define('API_KEY_BP','4d0e4072-889b-42cd-950c-af8d58221114');

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('main');

final class Authentication extends Base
{

	public function onBeforeAction(Event $event)
	{
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();

        $queryParamsArray = $request->toArray();
        $requestBody = json_decode($request->getInput(), true);

        $authorization = $server->get('REMOTE_USER');
        $authText = stristr($authorization, 'BitrixAuth');
        $headerToken = $authText === false ? $authorization : str_replace('BitrixAuth ', '', $authorization);

        // Поиск API-ключа в базе
        $apiKeyData = ApiKeysTable::getList([
            'filter' => ['STATUS' => 'active'],
            'select' => ['ID', 'API_KEY', 'USER_ID', 'KEY_LOCATION', 'KEY_PARAM_NAME']
        ])->fetchAll();

        foreach ($apiKeyData as $key) {
            if ($key['KEY_LOCATION'] === 'header' && $headerToken === $key['API_KEY']) {
                return $this->authorizeUser($key['USER_ID']);
            }
            if ($key['KEY_LOCATION'] === 'body' && isset($requestBody[$key['KEY_PARAM_NAME']]) && $requestBody[$key['KEY_PARAM_NAME']] === $key['API_KEY']) {
                return $this->authorizeUser($key['USER_ID']);
            }
            if ($key['KEY_LOCATION'] === 'query' && isset($queryParamsArray[$key['KEY_PARAM_NAME']]) && $queryParamsArray[$key['KEY_PARAM_NAME']] === $key['API_KEY']) {
                return $this->authorizeUser($key['USER_ID']);
            }
        }

        return $this->unauthorizedResponse("Invalid API key");
	}

    private function authorizeUser($userId)
    {
        global $USER;
        if (!is_object($USER)) $USER = new \CUser;
        $USER->Authorize($userId);
        return null;
    }

    private function unauthorizedResponse($message)
    {
        Context::getCurrent()->getResponse()->setStatus(401);
        $this->addError(new Error($message, "unauthorized"));
        return new EventResult(EventResult::ERROR, null, null, $this);
    }
}