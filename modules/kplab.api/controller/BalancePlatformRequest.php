<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use KPLab\BalancePlatform;
use KPLab\Logs;

define("LOG_BP", $_SERVER['DOCUMENT_ROOT']."/local/classes/balanceplatform/BalancePlatformRequest.log");
define("TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");
define('API_KEY','4d0e4072-889b-42cd-950c-af8d58221114');

\Bitrix\Main\Loader::includeModule('kplab.api.v2');
\Bitrix\Main\Loader::includeModule('rest');
\Bitrix\Main\Loader ::IncludeModule('crm');

class BalancePlatformRequest extends \Bitrix\Main\Engine\Controller {

    private $bpRequest;

    public function getDefaultPreFilters()
    {
        return [
            new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
        ];
    }
    public function getDefaultPostFilters()
    {
        return array();
    }

    protected function prepareParams()
    {
        return parent::prepareParams();
    }

    public function recieveAction() {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();
        $this->bpRequest = $context->getRequest();

        $requestHeaders = $context->getRequest()->getHeaders();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];

        $response = $context->getResponse();

        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса
        $jsonRes = ['status' => $statusRequest, 'response' => null];

        $point = "SE_BX";
        $url = $server['SCRIPT_URI'];
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $requestMethod;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }
        $arRequest = json_decode($requestJson,true);

        $status = $arRequest['status'];
        $data = $arRequest['data'];
        $isFirstRequest = $arRequest['isFirstRequest'];
        $taskId = $arRequest['taskId'];
        $message = $arRequest['message'];

        $objectData['ITEM_TITLE'] = $data['clientName'];
        $objectData['TASK_ID'] = $taskId;

        $balancePlatformRequestDate = $arRequest['balancePlatformRequestDate'];


        if(is_null($message)) {
            if($isFirstRequest)
            {
                $resCompany = self::setParamsCompany($taskId, $arRequest);
                if ($resCompany !== null)
                {
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(149);
                    $items = $factory->getItems([
                        'filter' => [
                            '%UF_CRM_49_1706019437092' => $taskId
                        ]
                    ]);
                    if($items):
                        foreach ($items as $k => $item)
                        {
                            $item->getData();
                            $objectData['ITEM_TITLE'] = $item->getData()['TITLE'];

                            $item->set('UF_CRM_SCORING_PASSED', true);
                            $item->set('UF_CRM_HAS_BANK_STATEMENTS', $arRequest['hasBankStatements']);
                            $item->setStageId("DT149_231:SUCCESS");

                            $operation = $factory -> getUpdateOperation($item);

                            // Step 2: config operation (optional)
                            $operation->disableAllChecks();

                            // Step 3: launch operation
                            $operationResult = $operation -> launch();

                            if ($operationResult -> isSuccess())
                            {
                                $message = "Данные успешно сохранились";
                                \CRest ::call('crm.timeline.comment.add', [
                                    'fields' => [
                                        "ENTITY_ID" => $item -> getId(),
                                        "ENTITY_TYPE" => 'DYNAMIC_149',
                                        "COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
                                    ]
                                ]);
                            }
                            else
                            {
                                $message = $operationResult -> getErrorMessages();
                                $statusRequest = 'Failed'; // Статус запроса

                                $resultDecoded = json_decode($message, true);
                                $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $message;

                                $jsonRes['status'] = $statusRequest;
                                $jsonRes['response'] = $resultToSave;

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
                                    $taskId,                            // Task ID (если есть)
                                    0,                              // ID Типа запроса (если есть)
                                    false                              // Тип запроса (если есть)
                                );
                                Logs\IBlock::setData($url, $requestJson, $jsonRes, $objectData, $timeData, $point, $requestHeaders);
                                return $jsonRes['response'];
                            }
                        }
                    endif;

                    $resultDecoded = json_decode($resCompany, true);
                    $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $resCompany;

                    $jsonRes['status'] = $statusRequest;
                    $jsonRes['response'] = $resultToSave;

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
                        $taskId,                            // Task ID (если есть)
                        0,                              // ID Типа запроса (если есть)
                        false                              // Тип запроса (если есть)
                    );
                    Logs\IBlock ::setData($url, $requestJson, $jsonRes, $objectData, $timeData, $point, $requestHeaders);
                    $res = $jsonRes['response'];
                }
            }
            else {
                $res134 = $this->setParams134($taskId, $arRequest);
                if($res134 !== null) {


                    $resultDecoded = json_decode($res134, true);
                    $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $res134;

                    $jsonRes['status'] = $statusRequest;
                    $jsonRes['response'] = $resultToSave;

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
                        $taskId,                            // Task ID (если есть)
                        0,                              // ID Типа запроса (если есть)
                        false                              // Тип запроса (если есть)
                    );
                    Logs\IBlock::setData($url, $requestJson, $jsonRes, $objectData, $timeData, $point, $headersValues);
                    $res = $jsonRes['response'];

                    $resCompany = self ::setParamsCompany($taskId, $arRequest);

                    if ($resCompany !== null)
                    {
                        Logs\File::AddMessage($resCompany,"resCompany",LOG_BP);
                    }
                }
            }
            return $res;
        }
        else {
            $statusRequest = 'Failed'; // Статус запроса
            $resultDecoded = json_decode($message, true);
            $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $message;

            $jsonRes['status'] = $statusRequest;
            $jsonRes['response'] = $resultToSave;

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
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $requestJson, $jsonRes, $objectData, $timeData, $point, $requestHeaders);
            return $jsonRes['response'];
        }

    }
    public function getInfoAction(array $params = []) {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();
        $requestMethod = $server['REQUEST_METHOD'];


        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса

        $jsonRes['status'] = $statusRequest;
        $jsonRes['response'] = null;

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $point = "SE_BX";
        $url = $server['SCRIPT_URI'];
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders();
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $recieve = json_decode($request->getInput(),true);

        $recieveINN = $recieve['inn'];

        $objectData['ITEM_TITLE'] = "Запрос по ИНН: {$recieveINN}";

        $allData134 = self::getInfo134byINN($recieveINN);
        if(!$allData134) {
            $errorMessage = "Not found card information by INN: {$recieveINN}";
            $this->addError(new Error($errorMessage, 403));
            $statusRequest = 'Failed'; // Статус запроса

            $resultDecoded = json_decode($errorMessage, true);
            $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $errorMessage;

            $jsonRes['status'] = $statusRequest;
            $jsonRes['response'] = $resultToSave;
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
                $request->getInput(),               // Тело запроса
                json_encode($request->getHeaders()),// Заголовки запроса
                null,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        elseif(is_array($allData134) && $allData134['errorFields']) {
            $error['message'] = "There are empty fields";
            $error['erData'] =  $allData134['errorFields'];
            $this->addError(new Error($error['message'], 403, $error['erData']));
            $statusRequest = 'Failed'; // Статус запроса

            $resultDecoded = json_decode($error, true);
            $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $error;

            $jsonRes['status'] = $statusRequest;
            $jsonRes['response'] = $resultToSave;
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
                $request->getInput(),               // Тело запроса
                json_encode($request->getHeaders()),// Заголовки запроса
                null,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        else {

            $resultDecoded = json_decode($allData134, true);
            $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $allData134;

            $jsonRes['status'] = $statusRequest;
            $jsonRes['response'] = $resultToSave;
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
                $request->getInput(),               // Тело запроса
                json_encode($request->getHeaders()),// Заголовки запроса
                null,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
            $point = "BX_SE";
            $res = BalancePlatform::createRequest($allData134, $objectData, $timeData, $point);
            return $res;
        }

    }

    public static function setParams134(string $taskId, $recieve) {
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(134);

        $items = $factory->getItems([
            'filter' => [
                '%UF_CRM_56_1705945353800' => $taskId
            ]
        ]);
        if($items):
            foreach ($items as $k => $item)
            {
                if($recieve == null) {
                    $message = "Данные не поступили!";
                    \CRest ::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $item -> getId(),
                            "ENTITY_TYPE" => "DYNAMIC_134",
                            "COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
                        ]
                    ]);
                }
                else
                {

                    // Сохраняем данные Checklist StatusType
                    $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $recieve["data"]["checklistStatus"]);
                    if (is_array($checklistStatus)) {
                        return [
                            'status' => 'error',
                            'messages' => $checklistStatus,
                        ];
                    }

                    $ratingStatus = (string) $recieve["data"]['ratingStatus'];
                    if($ratingStatus == "Негативный") $ratingStatusID = 21205;
                    if($ratingStatus == "Удовлетворительный") $ratingStatusID = 21206;
                    if($ratingStatus == "Средний") $ratingStatusID = 21207;
                    if($ratingStatus == "Хороший") $ratingStatusID = 21208;

                    $refusedInStatuses = $recieve["data"]["refusedInStatuses"];

                    $refusedInStatusesAutomaticRefuse = $refusedInStatuses['automaticRefuse'];
                    $refusedInStatusesAutomaticApprove = $refusedInStatuses['automaticApprove'];
                    $refusedInStatusesAuthorizedPerson = $refusedInStatuses['authorizedPerson'];
                    $refusedInStatusesCreditCommittee = $refusedInStatuses['creditCommittee'];


                    $fields = [
                        'UF_CRM_56_1684843345' => date('d.m.Y H:i:s'),
                        'UF_CRM_56_1705999659270' => $recieve["data"]['avg6MonthRevenue'], //Среднемесячная выручка
                        'UF_CRM_56_1705999684755' => $recieve["data"]['lastMonthRevenue'], //Выручка за последний месяц
                        'UF_CRM_56_1705999722689' => $recieve["data"]['stocksSum'], //Остаток товаров string ---UF_CRM_56_1705999840678---
                        'UF_CRM_56_1706000045243' => $recieve["data"]['pdn'], //ПДН
                        'UF_CRM_56_1706000068667' => $recieve["data"]['overdueBKISum'], //Наличие просроченных платежей
                        'UF_CRM_56_1706000119258' => $recieve["data"]['proceedingsPhysical'], //ФССП
                        'UF_CRM_56_1684846350' => $recieve["data"]['smoothedLimit'], //Новый лимит с учетом сглаживания
                        'UF_CRM_56_1710466920' => $recieve["data"]['hasInvalidKeys'], //Новый лимит с учетом сглаживания
                        'UF_CRM_56_1706002947573' => $recieve["data"]['status'], // Статус Скоринг системы Seller-Engine
                        'UF_CRM_56_1711371246' => $recieve["data"]['pdN_Group'], // Групповой ПДН
                        'UF_CRM_LIMIT_TO_PDN' => $recieve["data"]['limitToPDN'], // лимит до предельного значения ПДН
                        'UF_CRM_PDN_AFTER_TAKINGLIMIT' => $recieve["data"]['pdnAfterTakingLimit'], // ПДН после выборки
                        'UF_CRM_AVERAGE_CHECK' => $recieve["data"]['averageCheck'], // ПДН после выборки
                        'UF_CRM_56_FORECAST_SALES' => (float) $recieve["data"]['forecastSales'], // Прогноз выручки на текущий месяц.
                        'UF_CRM_AVERAGE_SALES_COUNT' => $recieve["data"]['averageSalesCount'], // Среднемесячное кол-во продаж
                        'UF_CRM_TARIF_OF_SELLERS' => $ratingStatusID, // Рейтинг клиента
                        'UF_CRM_56_GROW_RATE' => $recieve["data"]["growRate"],
                        'UF_STOCK_SUM_TO_SMOOTHED_LIMIT_RATIO' => $recieve["data"]["stockSumToSmoothedLimitRatio"],
                        'UF_CRM_56_AUTOMATIC_REFUSE' => $refusedInStatusesAutomaticRefuse,
                        'UF_CRM_56_AUTOMATIC_APPROVE' => $refusedInStatusesAutomaticApprove,
                        'UF_CRM_56_AUTHORIZED_PERSON' => $refusedInStatusesAuthorizedPerson,
                        'UF_CRM_56_CREDIT_COMMITTEE' => $refusedInStatusesCreditCommittee
                    ];

                    self::updateItemFields($factory, $item, $fields, 'Данные for 134');

                    $item -> setFromCompatibleData($fields);

                    // Step 1: get operation
                    $operation = $factory -> getUpdateOperation($item);

                    // Step 2: config operation (optional)
                    $operation->disableAllChecks();

                    // Step 3: launch operation
                    $operationResult = $operation -> launch();

                    if ($operationResult -> isSuccess())
                    {
                        $message = "Данные успешно сохранились";
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $item -> getId(),
                                "ENTITY_TYPE" => "DYNAMIC_134",
                                "COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
                            ]
                        ]);
                    } else
                    {
                        $message = $operationResult -> getErrorMessages();
                    }
                }
            }
            return $message;
        endif;
        return null;
    }
    public static function getInfo134byINN($inn) {
        $entityTypeId = 134;
        $req = new \Bitrix\Crm\EntityRequisite();
        $rsCompany = $req->getList(array(
            'filter' => array(
                'RQ_INN' => $inn
            ),
            'select' => ['ENTITY_ID']
        ));

        $rq = $rsCompany->fetch();
        $rqID = $rq['ENTITY_ID'];

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(134);
        //$items=[];
        $items = $factory->getItems([
            'filter' => [
                //'UF_CRM_56_1684841075' => $inn,
                'COMPANY_ID' => $rqID
            ]
        ]);

        if($items):
            foreach ($items as $k => $item)
            {
                $elementID = $item->getId();
                $allData = BalancePlatform::getInfo($entityTypeId, $elementID);
            }
            return $allData;
        endif;

        return null;
    }
    public static function setParams149(string $taskId, $data) {

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(149);

        $items = $factory->getItems([
            'filter' => [
                '%UF_CRM_49_1706019437092' => $taskId
            ]
        ]);
        if($items):
            foreach ($items as $k => $item)
            {
                if($data == null) {
                    $message = "Данные не поступили!";
                    \CRest ::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $item -> getId(),
                            "ENTITY_TYPE" => "DYNAMIC_149",
                            "COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
                        ]
                    ]);
                } else {

                    // Сохраняем данные Checklist StatusType

                    $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $data["_StatusType"]);
                    if (is_array($checklistStatus)) {
                        return [
                            'status' => 'error',
                            'messages' => $checklistStatus,
                        ];
                    }


                    $fields = [
                        'UF_CRM_49_1706081754331' => date('d.m.Y H:i:s'),
                        'UF_CRM_49_1706081303342' => $data['avg6MonthRevenue'], //Среднемесячная выручка
                        'UF_CRM_49_1706081312614' => $data['lastMonthRevenue'], //Выручка за последний месяц
                        'UF_CRM_49_1706081324071' => $data['stocksSum'], //Остаток товаров string ---UF_CRM_56_1705999840678---
                        'UF_CRM_49_1706081415791' => $data['pdn'], //ПДН
                        'UF_CRM_49_1706081340065' => $data['overdueBKISum'], //Наличие просроченных платежей
                        'UF_CRM_49_1706081290967' => $data['proceedingsPhysical'], //ФССП
                        'UF_CRM_49_1706081357760' => $data['smoothedLimit'], //Новый лимит с учетом сглаживания
                        'UF_CRM_AVERAGE_SALES_COUNT' => $data['hasBankStatements'], //Новый лимит с учетом сглаживания
                    ];
                    self::updateItemFields($factory, $item, $fields, 'Данные for 149');

                    $item -> setFromCompatibleData($fields);

                    // Step 1: get operation
                    $operation = $factory -> getUpdateOperation($item);

                    // Step 2: config operation (optional)
                    $operation->disableAllChecks();

                    // Step 3: launch operation
                    $operationResult = $operation -> launch();

                    if ($operationResult -> isSuccess())
                    {
                        $message = "Данные успешно сохранились";
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $item -> getId(),
                                "ENTITY_TYPE" => "DYNAMIC_149",
                                "COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
                            ]
                        ]);
                    } else
                    {
                        $message = $operationResult -> getErrorMessages();
                    }
                }
            }
            return $message;
        endif;

        return null;
    }
    public static function setParamsCompany(string $taskId, $data) {
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $itemsCompany = $factory->getItems([
            'filter' => [
                '%UF_CRM_UF_SE_TASK_ID' => str_replace("\"","", $taskId)
            ]
        ]);
        if($itemsCompany):
            //Logs\File::AddMessage("Найдена компания","item",LOG_BP);
            foreach ($itemsCompany as $k => $itemCompany)
            {
                if($data == null) {
                    $message = "Данные не поступили!";
                    \CRest ::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $itemCompany -> getId(),
                            "ENTITY_TYPE" => "COMPANY",
                            "COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
                        ]
                    ]);
                }
                else {

                    // Сохраняем данные и проверяем результат
                    $saveResult = self::saveAllData($factory, $itemCompany, $data);
                    if ($saveResult['status'] === 'error') {
                        // Если произошла ошибка, добавляем комментарий с сообщениями об ошибках
                        $message = "Ошибка при сохранении данных: " . implode(', ', $saveResult['messages']);
                        \CRest::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $itemCompany->getId(),
                                "ENTITY_TYPE" => "COMPANY",
                                "COMMENT" => "[b]{$message}[/b]"
                            ]
                        ]);
                    } else {
                        // Если все прошло успешно
                        $fields = [
                            'UF_CRM_AVERAGE_SALES_COUNT' => $data['hasBankStatements']
                        ];
                        self::updateItemFields($factory, $itemCompany, $fields, 'Данные HasBankStatements');
                        $message = "Данные успешно сохранились";
                        \CRest::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $itemCompany->getId(),
                                "ENTITY_TYPE" => "COMPANY",
                                "COMMENT" => "[b]{$message} от Seller-Engine![/b]"
                            ]
                        ]);
                    }
                }
            }
            return $message;

        else:
            Logs\File::AddMessage("Нихуя НЕ Найдена компания","item",LOG_BP);
        endif;

        return null;
    }



    private static function prepareOvkFields($data): array
    {
        return [
            'UF_CRM_COMPANY_OVK_CHECKDOMAIN' => $data["checkDomain"], // Проверка домена
            'UF_CRM_COMPANY_OVK_CHECK_COMPANY_INFO' => $data["checkCompanyInfo"], // Проверка информации о компании
            'UF_CRM_COMPANY_OVK_CHECK_LICENSES' => $data["checkLicenses"], // Проверка лицензий
            'UF_CRM_COMPANY_OVK_IS_IN_TERRORIST_LIST' => $data["isInTerroristList"], // Наличие в списке террористов
            'UF_CRM_COMPANY_OVK_IS_IN_MVK_LIST' => $data["isInMvkList"], // Наличие в списке МВК
            'UF_CRM_COMPANY_OVK_IS_IN_OMU_LIST' => $data["isInOmuList"], // Наличие в списке ОМУ
            'UF_CRM_COMPANY_OVK_IS_IN_STRATEGIC_LIST' => $data["isInStrategicList"], // Наличие в стратегическом списке
            'UF_CRM_COMPANY_OVK_IS_IN_OPK_ULS' => $data["isInOpkUls"], // Наличие в списке ОПК УЛС
            'UF_CRM_COMPANY_OVK_IS_IN_SANCTION_LIST' => $data["isInSanctionList"], // Наличие в санкционном списке
            'UF_CRM_COMPANY_OVK_IS_IN_PEP_LIST' => $data["isInPepList"], // Наличие в списке ПЭП
            'UF_CRM_COMPANY_OVK_IS_IN_764_LIST' => $data["isIn764List"], // Наличие в списке 764
            'UF_CRM_COMPANY_OVK_IS_IN_FINANCIAL_PYRAMYDE' => $data["isInFinancialPyramyde"], // Наличие в финансовой пирамиде
            'UF_CRM_COMPANY_OVK_IS_IN_ILLEGAL_CREDITOR' => $data["isInIllegalCreditor"], // Наличие в списке нелегальных кредиторов
            'UF_CRM_COMPANY_OVK_RESULT' => $data["result"], // Результат
        ];
    }
    private static function prepareKonturFocusFields($data): array
    {
        foreach ($data["certificates"] as $certificate_konturFocusData) {
            $type_certificate_konturFocusData = $certificate_konturFocusData["type"];
            $endDate_certificate_konturFocusData = $certificate_konturFocusData["endDate"];
            $productName_certificate_konturFocusData = $certificate_konturFocusData["productName"];
            $UF_CRM_COMPANY_KNTR_CERTIFICATE[] = "{$type_certificate_konturFocusData} | {$endDate_certificate_konturFocusData} | {$productName_certificate_konturFocusData}";
        }

        $subjects_lesseeContracts_konturFocusData = $data["lesseeContracts"][0]["subjects"]; //...
        $contractDate_lesseeContracts_konturFocusData = $data["lesseeContracts"][0]["contractDate"]; //...

        foreach ($data["licenses"] as $license_konturFocusData) {
            $activity_license_konturFocusData = $license_konturFocusData["activity"];
            $dateEnd_license_konturFocusData = $license_konturFocusData["dateEnd"];
            $UF_CRM_COMPANY_KNTR_LICENSES[] = "{$activity_license_konturFocusData} | {$dateEnd_license_konturFocusData}";
        }

        $stage_lastBankruptcyDataKonturFocusData = $data["lastBankruptcyData"]["stage"];
        $stageDate_lastBankruptcyDataKonturFocusData = $data["lastBankruptcyData"]["stageDate"];

        return [
            //region KONTUR FOCUS
            'UF_CRM_COMPANY_KNTR_ADMIN_OFFENCE_CASE_COUNT' => $data["administrativeOffenceCaseCount"],
            'UF_CRM_COMPANY_KNTR_ARBITR_CLAIMS_LOST_CASES_SUM' => $data["arbitrationClaimsForLostCasesSum"],
            'UF_CRM_COMPANY_KNTR_ARBIT_CLAIMS_REVIEW_CASES_SUM' => $data["arbitrationClaimsForReviewCasesSum"],
            'UF_CRM_COMPANY_KNTR_ANY_FNS_BLOCKED_ACCOUNTS' => $data["anyFnsBlockedAccounts"],
            'UF_CRM_COMPANY_KNTR_ANY_SENT_DOCUMENTS_TO_FNS' => $data["anySentDocumentsToFns"],
            'UF_CRM_COMPANY_KNTR_ANY_DISQUALIFIED_DIRECTORS' => $data["anyDisqualifiedDirectors"],
            'UF_CRM_COMPANY_KNTR_BANKS' => $data["banks"],
            'UF_CRM_COMPANY_KNTR_BLOCKED_ACCOUNTS' => $data["blockedAccounts"],
            'UF_CRM_COMPANY_KNTR_CERTIFICATE' => $UF_CRM_COMPANY_KNTR_CERTIFICATE,
            'UF_CRM_COMPANY_KNTR_LESSEE_CONTRACTS' => "{$subjects_lesseeContracts_konturFocusData} | {$contractDate_lesseeContracts_konturFocusData}",
            'UF_CRM_COMPANY_KNTR_CONNECTED_COMPANIES' => $data["connectedCompanies"],
            'UF_CRM_COMPANY_KNTR_CONNECTED_SITES' => $data["connectedSites"],
            'UF_CRM_COMPANY_KNTR_ENFORCEMENT_PROCEEDINGS_SUM' => $data["enforcementProceedingsSum"],
            'UF_CRM_COMPANY_KNTR_IN_ANY_SANCTION_LISTS' => $data["inAnySanctionLists"],
            'UF_CRM_COMPANY_KNTR_IN_ANY_FNS_LIST' => $data["inAnyFnsList"],
            'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_ENTERPRISE_LIST' => $data["inStrategicEnterpriseList1009"],
            'UF_CRM_COMPANY_KNTR_IN_JOIN_STOCK_CMPNY_LIST91P' => $data["inJointStockCompanyList91P"],
            'UF_CRM_COMPANY_KNTR_IN_UNRELIABLE_SIPPLIER_LIST' =>$data["inUnreliableSupplierList"],
            'UF_CRM_COMPANY_KNTR_MSP_LIST_DATE' => $data["mspListDate"],
            'UF_CRM_COMPANY_KNTR_LICENSES' => $UF_CRM_COMPANY_KNTR_LICENSES,
            'UF_CRM_COMPANY_KNTR_TRADEMARKS' => $data["trademarks"],
            'UF_CRM_COMPANY_KNTR_LAST_BANKRUPTCY_DATA' => "{$stage_lastBankruptcyDataKonturFocusData} | {$stageDate_lastBankruptcyDataKonturFocusData}",
            //endregion
        ];
    }
    private static function prepareKonturPrismaFields($data): array
    {
        return [
            //region KONTUR PRISMA
            'UF_CRM_COMPANY_KNTR_IN_GOVERNMENT_DIRECTIVE_LIST' => $data["inGovernmentDirectiveList"],
            'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_ORG_LIST' => $data["inStrategicOrganizationsList"],
            'UF_CRM_COMPANY_KNTR_WEBSITE_BLOCK_INFOS' => $data["websiteBlockInfos"],
            'UF_CRM_COMPANY_KNTR_IN_PRLIFRTN_RISK_DETECTIONLIST' => $data["inProliferationRiskDetectionList"],
            'UF_CRM_COMPANY_KNTR_IN_REFUSAL_LIST764P' => $data["inRefusalList764P"],
            'UF_CRM_COMPANY_KNTR_IN_BANK_REFUSAL_LIST764P' => $data["inBankRefusalList764P"],
            'UF_CRM_COMPANY_KNTR_IN_SANCTIONS_LIST' => $data["inSanctionsList"],
            'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_COMPANIES_LIST' => $data["inStrategicCompaniesList"],
            'UF_CRM_COMPANY_KNTR_IN_TERRORIST_LIST' =>  $data["inTerroristsList"],
            'UF_CRM_COMPANY_KNTR_IN_EXTREMISTS_LIST' => $data["inExtremistsList"],
            'UF_CRM_COMPANY_KNTR_IN_INTERDEP_COMMISSION_LIST' => $data["inInterdepartmentalCommissionList"],
            'UF_CRM_COMPANY_KNTR_IN_WEAPON_MASS_DESTRUCTION_DIS' => $data["inWeaponsOfMassDestructionDistributorsList"],
            'UF_CRM_COMPANY_KNTR_IN_ILLEG_LENDER_INDICATOR_LIST' => $data["inIllegalLenderIndicatorsList"],
            'UF_CRM_COMPANY_KNTR_IN_PYRAMID_SCHEME_INDICATAOR_L' => $data["inPyramidSchemeIndicatorsList"],
            'UF_CRM_COMPANY_KNTR_ILLEG_SECUR_MARKET_PARTICIPANT' => $data["inIllegalSecuritiesMarketParticipantIndicatorsList"],
            //endregion
        ];
    }
    private static function prepareChecklistStatusTypeFields($data): array
    {
        $statusType_checklistData = (string) $data;
        $statusType_checklistXML_ID = "company_checklist_".$statusType_checklistData;

        $rsEnum = \CUserFieldEnum::GetList(array(), array(
            "XML_ID" => $statusType_checklistXML_ID,
        ));
        if ($arEnum = $rsEnum->Fetch()) {
            $statusType_checklistID = $arEnum['ID'];
        }

        return [
            'UF_CRM_COMPANY_CHKLST' => $statusType_checklistID,
        ];
    }
    private static function prepareChecklistStatusNumFields($data): array
    {
        $statusType_checklistData = (string) $data;

        if($statusType_checklistData == 'AutomaticRefuse') $statusType_checklistID = 20874;
        if($statusType_checklistData == 'AuthorizedPerson') $statusType_checklistID = 20876;
        if($statusType_checklistData == 'CreditCommittee') $statusType_checklistID = 20877;
        if($statusType_checklistData == 'AutomaticApprove') $statusType_checklistID = 20875;
        if($statusType_checklistData == 'NotEnoughDocuments') $statusType_checklistID = 20878;
        if($statusType_checklistData == 'Bizmoll') $statusType_checklistID = 21865;

        return [
            'UF_CRM_CHKLST' => $statusType_checklistID,
        ];
    }
    private static function prepareChecklistStatusFields($data): array
    {
        //$statusType_checklistData = (string) $data;
        $statusType_checklistValue = (string) $data;

        $rsEnum = \CUserFieldEnum::GetList(array(), array(
            "VALUE" => $statusType_checklistValue,
        ));
        if ($arEnum = $rsEnum->Fetch()) {
            $statusType_checklistID = $arEnum['ID'];
        }

        return [
            'UF_CRM_CHKLST' => $statusType_checklistID,
        ];
    }
    private static function prepareChecklistFields($data): array
    {
        return [
            //region checkList
            'UF_CRM_COMPANY_INCOME_TO_SALE_RATIO' => $data["incomeToSaleRatio"],
            'UF_CRM_COMPANY_CHKLST_MARKETPLACE_DURATION_IN_MNTH' => $data["marketplaceDurationInMonths"],
            //'UF_CRM_COMPANY_CHKLST_SALES_TO_COUNT_SIX_MTH_RATIO' => $data["salesToCountForSixMonthsRatio"],
            //'UF_CRM_COMPANY_CHKLST_SALES_TO_RETURN_COUNT_SIX_MH' =>	$data["salesToReturnCountForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_STOK_SUM_LIMIT_SIX_MTH_RATIO' => $data["stockSumToSmoothedLimitRatio"],
            'UF_CRM_COMPANY_CHKLST_SMOOTHED_LIM_FOR_SIX_MONTHS' => $data["smoothedLimit"],
            'UF_CRM_COMPANY_CHKLST_PDN' => $data["pdn"]*100,
            'UF_CRM_COMPANY_CHKLST_ANY_FNS_BLOCKED_ACCOUNTS' => $data["hasAnyFnsBlockedAccounts"],
            'UF_CRM_COMPANY_CHKLST_PERSON_VALID_PASSPORT' => $data["hasPersonValidPassport"],
            'UF_CRM_COMPANY_CHKLST_LOCATED_IN_LISTS_NAMES' => $data["locatedInLists"],
            'UF_CRM_COMPANY_CHKLST_IN_UNRELIABLE_SUPPLIER_LIST' => $data["inUnreliableSupplierList"],
            'UF_CRM_COMPANY_CHKLST_ANY_DISQUALIFIED_DIRECTORS' => $data["hasAnyDisqualifiedDirectors"],
            'UF_CRM_COMPANY_CHKLST_PERSON_AGE_LIST' => $data["personAgeList"][0],//[45]
            'UF_CRM_COMPANY_CHKLST_HAS_PERSON_RUSSIAN_NATIONAL' => $data["hasPersonRussianNationality"],
            'UF_CRM_COMPANY_CHKLST_MAX_ACTIVE_LOAN_OVERDUE_DAYS' => $data["maxLoanOverdueDays"],
            'UF_CRM_COMPANY_CHKLST_ANY_LONG_OVERDUE_LOAN' => $data["hasAnyLongOverdueLoan"],
            'UF_CRM_COMPANY_CHKLST_PERSON_LOAN_DATE' => $data["lastPersonLoanTransferDate"],
            'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_MICRO_LOAN_CNT' => $data["personActiveMicroLoanCount"],
            'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_FSSP_SUM' => $data["personActiveFsspSum"],
            'UF_CRM_COMPANY_CHKLST_CLAIMS_SUM_INC_FOR_SIX_MONTH' => $data["claimsSumToAvgIncomesRatio"],
            'UF_CRM_COMPANY_CHKLST_LAST_BANKRUPTCY_DATE' => $data["lastBankruptcyDate"],
            'UF_CRM_COMPANY_CHKLST_AUTO_LIMIT' => (int) $data["limitForAutoApprove"],
            //endregion
        ];
    }
    private static function prepareRefusedInStatusFields($data): array
    {
        return [
            'UF_CRM_COMPANY_CHKLST_REFUSED_IN_STATUS_BY_DATA' => $data["automaticRefuse"],
            'UF_CRM_COMPANY_CHKLST_AUTOMATIC_APPROVE' => $data["automaticApprove"],
            'UF_CRM_COMPANY_CHKLST_AUTHORIZED_PERSON' => $data["authorizedPerson"],
            'UF_CRM_COMPANY_CHKLST_CREDIT_COMMITTEE' => $data["creditCommittee"]
        ];
    }
    private static function prepareRatingStatusFields($data): array
    {
        $status_ratingStatusValue = (string) $data;

        $status_ratingStatusXML_ID = "CO_Rate_1";

        if($status_ratingStatusValue == "0") $status_ratingStatusXML_ID = "CO_Rate_4";
        if($status_ratingStatusValue == "1") $status_ratingStatusXML_ID = "CO_Rate_3";
        if($status_ratingStatusValue == "2") $status_ratingStatusXML_ID = "CO_Rate_2";
        if($status_ratingStatusValue == "3") $status_ratingStatusXML_ID = "CO_Rate_1";

        $rsEnum = \CUserFieldEnum::GetList(array(), array(
            "XML_ID" => $status_ratingStatusXML_ID,
        ));
        if ($arEnum = $rsEnum->Fetch()) {
            $statusType_ratingStatusID = $arEnum['ID'];
        }

        return [
            'UF_CRM_TARIF_OF_SELLERS' => (int) $statusType_ratingStatusID,
        ];
    }
    private static function saveOvkData($factory, $item, $data) {
        $fields = self::prepareOvkFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные ovk');
    }
    private static function saveKonturFocusData($factory, $item, $data) {
        $fields = self::prepareKonturFocusFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные Kontur Focus');
    }
    private static function saveKonturPrismaData($factory, $item, $data) {
        $fields = self::prepareKonturPrismaFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные Kontur Prisma');
    }
    private static function saveChecklistStatusTypeData($factory, $item, $data) {
        $fields = self::prepareChecklistStatusTypeFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные Checklist StatusType');
    }
    private static function saveChecklistStatusData($factory, $item, $data) {
        $fields = self::prepareChecklistStatusFields($data);

        Logs\File::AddMessage($fields,"fields saveChecklistStatusData",LOG_BP);
        return self::updateItemFields($factory, $item, $fields, 'Данные checklistStatus');
    }
    private static function saveChecklistStatusNumData($factory, $item, $data) {
        $fields = self::prepareChecklistStatusNumFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные checklistStatus');
    }
    private static function saveRatingStatus($factory, $item, $data) {
        $fields = self::prepareRatingStatusFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные ratingStatus');
    }
    private static function saveChecklistData($factory, $item, $data) {
        $fields = self::prepareChecklistFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные Checklist');
    }
    private static function saveRefusedInStatusByData($factory, $item, $data) {
        $fields = self::prepareRefusedInStatusFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные Checklist RefusedInStatusByData');
    }
    private static function saveAllData($factory, $item, $data)
    {
        // Сохраняем данные OVK
        $ovkResult = self::saveOvkData($factory, $item, $data['ovk']);
        if (is_array($ovkResult)) {
            // Возвращаем ошибку, если результат является массивом с сообщениями об ошибке
            return [
                'status' => 'error',
                'messages' => $ovkResult,
            ];
        }

        // Сохраняем данные Kontur Focus
        $konturFocusResult = self::saveKonturFocusData($factory, $item, $data['kontur']['focus']);
        if (is_array($konturFocusResult)) {
            return [
                'status' => 'error',
                'messages' => $konturFocusResult,
            ];
        }

        // Сохраняем данные Kontur Prisma
        $konturPrismaResult = self::saveKonturPrismaData($factory, $item, $data['kontur']['prisma']);
        if (is_array($konturPrismaResult)) {
            return [
                'status' => 'error',
                'messages' => $konturPrismaResult,
            ];
        }

        // Сохраняем данные Checklist StatusType
        $checklistStatusType = self::saveChecklistStatusTypeData($factory, $item, $data["checklistV2"]['status']);
        if (is_array($checklistStatusType)) {
            return [
                'status' => 'error',
                'messages' => $checklistStatusType,
            ];
        }

        $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $data["checklistV2"]['statusString']);
        if (is_array($checklistStatus)) {
            return [
                'status' => 'error',
                'messages' => $checklistStatus,
            ];
        }

        // Сохраняем данные Checklist
        $checklist = self::saveChecklistData($factory, $item, $data["checklistV2"]);
        if (is_array($checklist)) {
            return [
                'status' => 'error',
                'messages' => $checklist,
            ];
        }

        // Сохраняем данные RefusedInStatusByData
        $refusedInStatusBy = self::saveRefusedInStatusByData($factory, $item, $data["checklistV2"]['refusedInStatuses']);
        if (is_array($refusedInStatusBy)) {
            return [
                'status' => 'error',
                'messages' => $refusedInStatusBy,
            ];
        }

        // Сохраняем данные RatingStatus
        $ratingStatus = self::saveRatingStatus($factory, $item, $data["ratingStatus"]);
        if (is_array($ratingStatus)) {
            return [
                'status' => 'error',
                'messages' => $ratingStatus,
            ];
        }

        // Если все прошло успешно, возвращаем успешный результат
        return [
            'status' => 'success',
            'messages' => 'Все данные успешно сохранены.',
        ];
    }
    private static function updateItemFields($factory, $item, $fields, $messagePrefix)
    {
        Logs\File::AddMessage($fields, "$messagePrefix", LOG_BP);

        //$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $item->setFromCompatibleData($fields);
        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();

        $operationResult = $operation->launch();

        if ($operationResult->isSuccess()) {
            return "{$messagePrefix} успешно сохранились";
        } else {
            return $operationResult->getErrorMessages(); // Возвращаем массив ошибок
        }
    }
}