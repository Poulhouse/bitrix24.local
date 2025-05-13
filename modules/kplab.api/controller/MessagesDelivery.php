<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_MESSAGES_DELIVERY_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/MessagesDeliveryController.log");

class MessagesDelivery extends \Bitrix\Main\Engine\Controller
{
    protected function getDefaultPreFilters()
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new \KPLab\API\V2\Controller\ActionFilter\NonAuthentication(),
        ];
    }
    public function setStatusAction(array $params = []) {
//region Подготовка к обработке запроса
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();
        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();
        $url = $server -> get('SCRIPT_URI') . "?" .$server -> get('QUERY_STRING');

        $token = str_replace('BitrixAuth ', '', $server->get('REMOTE_USER'));
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SMS: Результат добавления статуса от SMS провайдера: ";
        $objectData = $this->CURLObjectData;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        /*$controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса
        $requestTypeId = 0;
        $outRequest = false;
        $jsonRes = ['status' => $statusRequest, 'response' => null];
        $partnerName = "SE";
        $taskId = 0;*/

        $arRequest = json_decode($requestJson,true);

        \Bitrix\Main\Loader ::IncludeModule('crm');
        //endregion

        Logs\File ::AddMessage($arRequest, "arRequest", LOG_API_MESSAGES_DELIVERY_CONTROLLER);

        //$objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($arRequest, $objectData);
    }
}