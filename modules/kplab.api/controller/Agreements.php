<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_AGREEMENTS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/AgreementsController.log");

class Agreements extends \Bitrix\Main\Engine\Controller
{
    public function configureActions()
    {
        return [
            'add' => [
                'prefilters' => [
                    new \KPLab\API\V2\Controller\ActionFilter\Authentication(),
                ],
                '-prefilters' => [
                    \Bitrix\Main\Engine\ActionFilter\Authentication::class,
                ],
                'postfilters' => [],
            ]
        ];
    }


    public function addAction($params = [])
    {
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/logs/Agreements_debug.log", "addAction", FILE_APPEND);
        //region Подготовка к обработке запроса
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();
        $this->bpRequest = $context->getRequest();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();


        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/logs/requests_debug.log", print_r($requestHeaders, true), FILE_APPEND);
        
        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса
        $requestTypeId = 0;
        $outRequest = false;
        $jsonRes = ['status' => $statusRequest, 'response' => null];
        $partnerName = "SE";
        $taskId = 0;

        $authorization = $server -> get('REMOTE_USER');
        $token = str_replace('BitrixAuth ', '', $authorization);

        $url = $server -> get('SCRIPT_URI') . $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления соглашение пользователя: ";
        $objectData = $this->CURLObjectData;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $arRequest = json_decode($requestJson,true);
        Logs\File ::AddMessage("Добавляем соглашение пользователя", $arRequest, LOG_API_SYNC_AGREEMENTS_CONTROLLER);

        \Bitrix\Main\Loader ::IncludeModule('crm');
        //endregion

        return new \Bitrix\Main\Engine\Response\Json(['status' => 'success']);
    }
}