<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\ArgumentTypeException;
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

        $arTypeId = [];
        $arTypeName = [];
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

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = 'Тело запроса не удалось декодировать как JSON.';

            return $this->handleError($errorMessage, "invalid_request",
                $objectData, $methodName, $url, $controllerName, $requestMethod,
                $jsonRes, $timeData, $requestJson, $headersValues, $taskId,
                $requestTypeId, $outRequest, $partnerName);
        }
        if(empty($arRequest['ipAddress'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `ipAddress`';
            return $this->handleError($errorMessage, "invalid_request",
                $objectData, $methodName, $url, $controllerName, $requestMethod,
                $jsonRes, $timeData, $requestJson, $headersValues, $taskId,
                $requestTypeId, $outRequest, $partnerName);
        }
        //endregion

        Loader::includeModule('iblock');

        $IBLOCK_ID = 16;

        $ipAddress = $arRequest['ipAddress'];
        $inn = $arRequest['inn'];
        $type = $arRequest['type'];
        $siteUrl = $arRequest['siteUrl'];

        $arFilter = array(
            "IBLOCK_ID" => $IBLOCK_ID,
            "CODE" => "TYPE" // Код вашего свойства типа "Список"
        );
        $rsPropsType = \CIBlockPropertyEnum::GetList(array(), $arFilter);
        while ($arPropType = $rsPropsType->Fetch()) {
            $arTypeId[$arPropType["XML_ID"]] = $arPropType["ID"];
            $arTypeName[$arPropType["XML_ID"]] = $arPropType["VALUE"];
        }

        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления соглашение пользователя: {$inn}";


        // Подготовка массива свойств для добавления в инфоблок
        $arProperties = [
            'TYPE' => $arTypeId[$type],
            'INN' => $inn,
            'IP_ADDRESS' => $ipAddress,
            'FORM_DATA' => json_encode($arRequest['formData'],JSON_UNESCAPED_UNICODE), // Если FORM_DATA - HTML/текст, то можно использовать json_encode или просто строковое представление
            'DOC_LINK' => $arRequest['docLink'],
            'SITE_URL' => $siteUrl
        ];

        // Добавление нового элемента в инфоблок
        $arFields = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "NAME" => "Новое $arTypeName[$type] для $inn", // Название элемента
            "ACTIVE" => "Y",
            "PROPERTY_VALUES" => $arProperties
        ];
        $el = new \CIBlockElement;
        $elementId = $el->Add($arFields);

        if ($elementId === false) {
            $message = "Ошибка при добавлении элемента: " . $el->LAST_ERROR;
        } else {
            $message = "Информация о соглашении успешно добавлено с ID: " . $elementId;
        }

        $objectData = $this->CURLObjectData;
        return $this->handleSuccess($message, $objectData, $methodName,
            $url, $controllerName, $requestMethod, $statusRequest, $jsonRes,
            $timeData, $requestJson, $headersValues, $taskId, $requestTypeId,
            $outRequest, $partnerName);
    }


    private function handleError(
        $message,
        $code = "invalid_request",
        $objectData,
        $methodName,                        // Метод запроса (имя метода)
        $url,                               // URL запроса
        $controllerName,                    // имя текущего контроллера
        $requestMethod,       // Метод запроса (POST или GET)
        $jsonRes,              // Ответ на запрос
        $timeData,                          // Время
        $requestJson,
        $requestHeaders,
        $taskId,
        $requestTypeId,
        $outRequest,
        $partnerName
    ): EventResult
    {
        Context::getCurrent()->getResponse()->setStatus(400);
        $this->addError(new Error($message, $code));
        $statusRequest = 'Failed'; // Статус запроса

        $resultDecoded = json_decode($message, true);
        $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '"'.$message.'"';

        $jsonRes['status'] = $statusRequest;
        $jsonRes['response'] = $resultToSave;
        //Logs\File ::AddMessage($jsonRes, "jsonRes", LOG_API_SYNC_SELLER_CONTROLLER);

        // Логируем информацию
        \KPLab\API\V2\LogsAction::Request(
            $objectData,
            $methodName,                        // Метод запроса (имя метода)
            $url,                               // URL запроса
            $controllerName,                    // имя текущего контроллера
            $requestMethod,       // Метод запроса (POST или GET)
            $statusRequest,                     // Статус запроса
            $jsonRes,              // Ответ на запрос
            $timeData,                          // Время
            $requestJson,               // Тело запроса
            json_encode($requestHeaders),// Заголовки запроса
            $taskId,
            $requestTypeId,                 // Тип запроса (если есть)
            $outRequest,
            $partnerName
        );

        // Возвращаем ошибку
        return new EventResult(EventResult::ERROR, null, null, $this);
    }

    /**
     * @throws ArgumentNullException
     * @throws ArgumentTypeException
     */
    private function handleSuccess(
        $message,
        $objectData,
        $methodName,                        // Метод запроса (имя метода)
        $url,                               // URL запроса
        $controllerName,                    // имя текущего контроллера
        $requestMethod,       // Метод запроса (POST или GET)
        $statusRequest,
        $jsonRes,              // Ответ на запрос
        $timeData,                          // Время
        $requestJson,
        $requestHeaders,
        $taskId,
        $requestTypeId,
        $outRequest,
        $partnerName
    )
    {

        Logs\File ::AddMessage($message, "message", LOG_API_SYNC_AGREEMENTS_CONTROLLER);

        $context = Context::getCurrent();
        $response = $context->getResponse();
        $response->setStatus(200);
        $response->addHeader('Content-Type', 'application/json; charset=UTF-8');

        if(is_string($message)) {
            $resultToSaveDecoded = json_decode($message, true);
            $resultToSaveLogs = $resultToSaveDecoded !== null ? json_encode($resultToSaveDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $message;
        } elseif(is_array($message)) {
            $resultToSaveDecoded = $message;
            $resultToSaveLogs = json_encode($message, JSON_UNESCAPED_UNICODE);
        } else {
            $resultToSaveDecoded = json_decode($message, true);
            $resultToSaveLogs = $resultToSaveDecoded !== null ? json_encode($resultToSaveDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $message;
        }

        $jsonResLogs['status'] = $statusRequest;
        $jsonResLogs['response'] = $resultToSaveLogs;

        $jsonRes['status'] = $statusRequest;
        $jsonRes['response'] = $resultToSaveDecoded;

        //Logs\File ::AddMessage($jsonRes, "jsonRes", LOG_API_SYNC_AGREEMENTS_CONTROLLER);
        // Логируем информацию
        \KPLab\API\V2\LogsAction::Request(
            $objectData,
            $methodName,                        // Метод запроса (имя метода)
            $url,                               // URL запроса
            $controllerName,                    // имя текущего контроллера
            $requestMethod,       // Метод запроса (POST или GET)
            $statusRequest,                     // Статус запроса
            $jsonResLogs,              // Ответ на запрос
            $timeData,                          // Время
            $requestJson,               // Тело запроса
            json_encode($requestHeaders),// Заголовки запроса
            $taskId,
            $requestTypeId,                 // Тип запроса (если есть)
            $outRequest,
            $partnerName
        );

        $response->setContent($resultToSaveLogs);

        return $resultToSaveLogs;
    }
}