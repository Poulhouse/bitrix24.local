<?php

namespace KPLab\API\V2\Helpers;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use KPLab\API\V2\LogsAction;
use KPLab\API\V2\Model\ORM\RoutesTable;
use KPLab\Logs;
define("LOG_HANDLER_RESPONSE", $_SERVER['DOCUMENT_ROOT']."/local/logs/HandlerResponse.log");

class HandlerResponse extends \Bitrix\Main\Engine\Controller
{
    const MODULE_ID = 'kplab.api';

    public $controller;
    public $context;

    public string $controllerName;
    public mixed $methodName;
    public mixed $statusRequest;
    public int $requestTypeId;
    public bool $outRequest;
    public array $jsonRes;
    public string $partnerName;
    public string $taskId;
    public bool $logger;
    public string $requestMethod;
    public string $url;
    public string $logLevel;
    public mixed $startTime;
    public array $headers;
    public string $body;
    public function handleInit(
        Controller $controller,
        string $funcName,
        string $partnerName,
        string $requestMethod,
        string $url,
        $startTime,
        array $headers,
        string $body,
        $context,
        ?string $logLevel = null,
        bool $outRequest = false,
        int $taskId = 0,
        string $status = 'Success'
    ): static
    {
        if(is_null($logLevel)) $logLevel = 'none';
        // Получаем имя текущего контроллера и метода
        $this->logLevel = $logLevel;
        $this->context = $context;
        $this->controller = $controller;
        $this->controllerName = get_class($controller);
        $this->methodName = $funcName;
        $this->statusRequest = $status; // Статус запроса
        $this->requestTypeId = 0;
        $this->outRequest = $outRequest;
        $this->jsonRes = ['status' => $this->statusRequest, 'response' => null];
        $this->partnerName = $partnerName;
        $this->taskId = $taskId;
        $this->requestMethod = $requestMethod;
        $this->url = $url;
        $this->startTime = $startTime;
        $this->headers = $headers;
        $this->body = $body;
        return $this;
    }


    public function initHandler($controller, $ctx, string $body, string $actionMethod = __FUNCTION__): static
    {
        $req        = $ctx->getRequest();
        $jsonReq    = $req->getInput();
        $uri        = $req->getRequestUri();
        $uriWithoutQuery = parse_url($uri, PHP_URL_PATH);
        $controller->serverName = $ctx->getServer()->get('SERVER_NAME');

        // 1) Находим ID маршрута и logLevel
        $route = RoutesTable::getList([
            'filter' => ['ROUTE_PATH' => $uriWithoutQuery, 'ACTIVE' => 'Y'],
            'select' => ['ID','LOG_LEVEL']
        ])->fetch();

        $routeId  = $route['ID'] ?? null;

        $logLevel = $routeId
            ? Option::get(self::MODULE_ID, "route_{$routeId}_log_level", $route['LOG_LEVEL'] ?? 'errors')
            : 'errors';

        // 2) Составляем timeData
        $timeData = Logs\TimeData::start();

        // 3) Получаем все query-параметры
        $controller->queryParamsArray = $req->toArray();

        // 4) HTTP-метод
        $method = $req->getRequestMethod();

        if ($method === 'GET') {
            $controller->requestData = $controller->queryParamsArray;
        } else {
            $decoded = json_decode($jsonReq, true);
            $controller->requestData = is_array($decoded) ? $decoded : [];
        }


        // 5) Имя партнёра (REMOTE_USER)
        $partner = LogsAction::getPartnerName($ctx->getServer()->get('REMOTE_USER'));
        $controller->partnerName = $partner;

        // 6) Сохраняем начальные данные для логирования в $this->objectData
        $controller->objectData = [
            'METHOD'     => $method,
            'ITEM_TITLE' => "", // обновим в action-е, когда будет INN
            // 'INIT_OBJECT_URL' добавим позже, когда узнаем ID карточки
        ];

        // Передаём сразу в сервис, чтобы он мог логировать эти данные
        $controller->sellerService->setObjectData($controller->objectData);

        // 7) Заголовки → map
        $headers = $req->getHeaders()->toArray();
        $map     = [];
        foreach ($headers as $h) {
            // $h = ['name'=>'HEADER_NAME','values'=>['value1','value2',...]]
            $map[$h['name']] = $h['values'][0] ?? '';
        }

        // 8) Возвращаем готовый HandlerResponse
        return $this->handleInit(
            $controller,
            $actionMethod,       // "initHandler"
            $partner,           // имя партнёра
            $method,            // HTTP-метод
            $uri,               // URI (без query-string)
            $timeData,          // время старта
            $map,               // map заголовков
            $body,              // «сырое» тело запроса (JSON)
            Application::getInstance()->getContext(),
            $logLevel           // уровень логирования
        );
    }

    public function handleError($statusCode, $message, $code, $objectData, array $customData = [])
    {

        $this->context->getResponse()->setStatus($statusCode);
        $this->controller->addError(new Error($message, $code, $customData));
        $this->statusRequest = 'Failed'; // Статус запроса

        $resultDecoded = json_decode($message, true);
        $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '"'.$message.'"';

        $this->jsonRes['status'] = $this->statusRequest;
        $this->jsonRes['response'] = $resultToSave;

        if($this->logLevel == 'debug' || $this->logLevel == 'error'){
            // Логируем информацию
            \KPLab\API\V2\LogsAction::Request(
                $objectData,
                $this->methodName,                        // Метод запроса (имя метода)
                $this->url,                               // URL запроса
                $this->controllerName,                    // имя текущего контроллера
                $this->requestMethod ,       // Метод запроса (POST или GET)
                $this->statusRequest,                     // Статус запроса
                $this->jsonRes,              // Ответ на запрос
                $this->startTime,                          // Время
                $this->body,               // Тело запроса
                json_encode($this->headers),// Заголовки запроса
                $this->taskId,
                $this->requestTypeId,                 // Тип запроса (если есть)
                $this->outRequest,
                $this->partnerName
            );
        }

        return new EventResult(EventResult::ERROR, null, null, $this->controller);
    }

    public function getResponse($objectData): array|string
    {
        $logData = [
            'objectData' => $objectData,
            'methodName' => $this->methodName,
            'controllerName' => $this->controllerName,
            'method' => $this->requestMethod,
            'timeData' => $this->startTime
        ];

        return \KPLab\ApiRequest::sendRequest(
            $this->url,
            'GET',
            json_encode($this->headers),
            $this->body,
            $logData
        );
    }

    public function handleSuccess($message, $objectData)
    {
        Context::getCurrent()->getResponse()->setStatus(200);
        $resultToSaveLogs = "";
        $resultToSaveDecoded = [];
        if(is_string($message)) {
            $resultToSaveDecoded = json_decode($message, true);
            $resultToSaveLogs = $resultToSaveDecoded !== null ? json_encode($resultToSaveDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '"'.$message.'"';
        }
        elseif(is_array($message)) {
            $resultToSaveDecoded = $message;
            $resultToSaveLogs = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        $jsonResLogs['status'] = $this->statusRequest;
        $jsonResLogs['response'] = $resultToSaveLogs;

        $this->jsonRes['status'] = $this->statusRequest;
        $this->jsonRes['response'] = $resultToSaveDecoded;

        if($this->logLevel == 'debug') {
            // Логируем информацию
            \KPLab\API\V2\LogsAction::Request(
                $objectData,
                $this->methodName,                        // Метод запроса (имя метода)
                $this->url,                               // URL запроса
                $this->controllerName,                    // имя текущего контроллера
                $this->requestMethod,       // Метод запроса (POST или GET)
                $this->statusRequest,                     // Статус запроса
                $jsonResLogs,              // Ответ на запрос
                $this->startTime,                          // Время
                $this->body,               // Тело запроса
                json_encode($this->headers),// Заголовки запроса
                $this->taskId,
                $this->requestTypeId,                 // Тип запроса (если есть)
                $this->outRequest,
                $this->partnerName
            );
        }


        return new \Bitrix\Main\Engine\Response\Json(
            [
                'status' => 'success',
                'data' => $resultToSaveDecoded
            ]
        );
    }

}