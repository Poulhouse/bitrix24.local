<?php

namespace KPLab\API\V2\Helpers;

use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;

class HandlerResponse
{
    const MODULE_ID = 'kplab.api';
    public $controller;
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
    public mixed $timeData;
    public array $headersValues;
    public string $requestJson;
    public function __construct(
        $controller, $funcName, $partnerName,
        $requestMethod, $url, $timeData,
        $headersValues, $requestJson, $outRequest = false,
        $logger = true, $taskId = 0, $status = 'Success'
    )
    {
        // Получаем имя текущего контроллера и метода
        $this->controller = $controller;
        $this->controllerName = get_class($controller);
        $this->methodName = $funcName;
        $this->statusRequest = $status; // Статус запроса
        $this->requestTypeId = 0;
        $this->outRequest = $outRequest;
        $this->jsonRes = ['status' => $this->statusRequest, 'response' => null];
        $this->partnerName = $partnerName;
        $this->taskId = $taskId;
        $this->logger = $logger;
        $this->requestMethod = $requestMethod;
        $this->url = $url;
        $this->timeData = $timeData;
        $this->headersValues = $headersValues;
        $this->requestJson = $requestJson;
    }

    public function handleError($statusCode, $message, $code, $objectData): EventResult
    {

        Context::getCurrent()->getResponse()->setStatus($statusCode);
        $this->addError(new Error($message, $code));
        $this->statusRequest = 'Failed'; // Статус запроса

        $resultDecoded = json_decode($message, true);
        $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '"'.$message.'"';

        $this->jsonRes['status'] = $this->statusRequest;
        $this->jsonRes['response'] = $resultToSave;

        if($this->logger) {
            // Логируем информацию
            \KPLab\API\V2\LogsAction::Request(
                $objectData,
                $this->methodName,                        // Метод запроса (имя метода)
                $this->url,                               // URL запроса
                $this->controllerName,                    // имя текущего контроллера
                $this->requestMethod ,       // Метод запроса (POST или GET)
                $this->statusRequest,                     // Статус запроса
                $this->jsonRes,              // Ответ на запрос
                $this->timeData,                          // Время
                $this->requestJson,               // Тело запроса
                json_encode($this->headersValues),// Заголовки запроса
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
            'timeData' => $this->timeData
        ];

        return \KPLab\ApiRequest::sendRequest(
            $this->url,
            'GET',
            $this->headersValues,
            $this->requestJson,
            $logData
        );
    }


    public function handleSuccess($message, $objectData)
    {
        Context::getCurrent()->getResponse()->setStatus(200);

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

        if($this->logger) {
            // Логируем информацию
            \KPLab\API\V2\LogsAction::Request(
                $objectData,
                $this->methodName,                        // Метод запроса (имя метода)
                $this->url,                               // URL запроса
                $this->controllerName,                    // имя текущего контроллера
                $this->requestMethod,       // Метод запроса (POST или GET)
                $this->statusRequest,                     // Статус запроса
                $jsonResLogs,              // Ответ на запрос
                $this->timeData,                          // Время
                $this->requestJson,               // Тело запроса
                json_encode($this->headersValues),// Заголовки запроса
                $this->taskId,
                $this->requestTypeId,                 // Тип запроса (если есть)
                $this->outRequest,
                $this->partnerName
            );
        }

        return $resultToSaveDecoded;
    }

}