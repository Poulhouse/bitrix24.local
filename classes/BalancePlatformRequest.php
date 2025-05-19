<?php

namespace KPLab;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;

class BalancePlatformRequest extends \Bitrix\Main\Engine\Controller {

    private $balancePlatformRequest;

    public function getDefaultPreFilters()
    {
        return [
            new Authentication(),
        ];
    }
    public function getDefaultPostFilters()
    {
        return array();
    }

    protected function prepareParams()
    {
        //$this->loans = new \KPLab\JWT\Loans();
        return parent::prepareParams();
    }

    public function onBeforeAction(\Event $event) {

        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();

        $apikey = json_decode($request->getInput(),true)['apiKey'];

        if ($apikey)
        {
            if ($apikey !== APIKEY)
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

    public function recieveAction() {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $response = $context -> getResponse();
        $server = $context -> getServer();

        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса

        $point = "SE_BX";
        $url = $server['SCRIPT_URI'];
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders();
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }
        $recieve = json_decode($request->getInput(),true);
        Logs\File::AddMessage($recieve,"recieve",LOG_BP);
        //$headers = $request['headers']['headers'];
        $status = $recieve['status'];
        $data = $recieve['data'];
        $isFirstRequest = $recieve['isFirstRequest'];
        $taskId = $recieve['taskId'];
        $message = $recieve['message'];

        $objectData['ITEM_TITLE'] = $data['clientName'];
        $objectData['TASK_ID'] = $taskId;


        $balancePlatformRequestDate = $recieve['balancePlatformRequestDate'];


        if(is_null($message)) {

            Logs\File::AddMessage($message,"message",LOG_BP);
            if($isFirstRequest)
            {
                Logs\File::AddMessage($isFirstRequest,"isFirstRequest",LOG_BP);
                //$res149 = self ::setParams149($taskId, $data);
                $resCompany = self ::setParamsCompany($taskId, $recieve);
                Logs\File::AddMessage($resCompany,"resCompany",LOG_BP);
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
                            } else
                            {
                                $message = $operationResult -> getErrorMessages();
                                $statusRequest = 'Failed'; // Статус запроса
                                $jsonRes['success'] = "";
                                $jsonRes['error'] = $message;
                                // Логируем информацию
                                \KPLab\API\LogsAction::Request(
                                    $objectData,
                                    $methodName,                        // Метод запроса (имя метода)
                                    $url,                               // URL запроса
                                    $controllerName,                    // имя текущего контроллера
                                    $request->getRequestMethod(),       // Метод запроса (POST или GET)
                                    $statusRequest,                     // Статус запроса
                                    json_encode($jsonRes),              // Ответ на запрос
                                    $timeData,                          // Время
                                    $request->getInput(),               // Тело запроса
                                    json_encode($request->getHeaders()),// Заголовки запроса
                                    $taskId,                            // Task ID (если есть)
                                    0,                              // ID Типа запроса (если есть)
                                    false                              // Тип запроса (если есть)
                                );
                                Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
                                return false;
                            }
                        }
                    endif;

                    $jsonRes['success'] = $resCompany;
                    $jsonRes['error'] = "";
                    // Логируем информацию
                    \KPLab\API\LogsAction::Request(
                        $objectData,
                        $methodName,                        // Метод запроса (имя метода)
                        $url,                               // URL запроса
                        $controllerName,                    // имя текущего контроллера
                        $request->getRequestMethod(),       // Метод запроса (POST или GET)
                        $statusRequest,                     // Статус запроса
                        json_encode($jsonRes),              // Ответ на запрос
                        $timeData,                          // Время
                        $request->getInput(),               // Тело запроса
                        json_encode($request->getHeaders()),// Заголовки запроса
                        $taskId,                            // Task ID (если есть)
                        0,                              // ID Типа запроса (если есть)
                        false                              // Тип запроса (если есть)
                    );
                    Logs\IBlock ::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
                    $res = $resCompany;
                }
            }
            else {
                $res134 = self::setParams134($taskId, $recieve);
                if($res134 !== null) {
                    $resCompany = self ::setParamsCompany($taskId, $recieve);
                    Logs\File::AddMessage($resCompany,"resCompany",LOG_BP);
                    if ($resCompany !== null)
                    {
                        $jsonRes['success'] = $res134;
                        $jsonRes['error'] = "";
                        // Логируем информацию
                        \KPLab\API\LogsAction::Request(
                            $objectData,
                            $methodName,                        // Метод запроса (имя метода)
                            $url,                               // URL запроса
                            $controllerName,                    // имя текущего контроллера
                            $request->getRequestMethod(),       // Метод запроса (POST или GET)
                            $statusRequest,                     // Статус запроса
                            json_encode($jsonRes),              // Ответ на запрос
                            $timeData,                          // Время
                            $request->getInput(),               // Тело запроса
                            json_encode($request->getHeaders()),// Заголовки запроса
                            $taskId,                            // Task ID (если есть)
                            0,                              // ID Типа запроса (если есть)
                            false                              // Тип запроса (если есть)
                        );
                        Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
                        $res = $res134;
                    }
                }
            }
            return $res;
        }
        else {
            $jsonRes['success'] = "";
            $jsonRes['error'] = $message;

            $statusRequest = 'Failed'; // Статус запроса

            // Логируем информацию
            \KPLab\API\LogsAction::Request(
                $objectData,
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                $request->getRequestMethod(),       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                json_encode($jsonRes),              // Ответ на запрос
                $timeData,                          // Время
                $request->getInput(),               // Тело запроса
                json_encode($request->getHeaders()),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
            return false;
        }

    }

    public function getInfoAction(array $params = []) {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();

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
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        elseif(is_array($allData134) && $allData134['errorFields']) {
            $error['message'] = "There are empty fields";
            $error['erData'] =  $allData134['errorFields'];
            $this->addError(new Error($error['message'], 403, $error['erData']));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $error;
            Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        else {
            $jsonRes['success'] = $allData134;
            $jsonRes['error'] = "";
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
                    $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $recieve["checklist"]["_StatusType"]);
                    if (is_array($checklistStatus)) {
                        return [
                            'status' => 'error',
                            'messages' => $checklistStatus,
                        ];
                    }

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
                        'UF_CRM_56_1711371246' => $recieve["data"]['pdN_Group'] // Групповой ПДН
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
                '%UF_CRM_UF_SE_TASK_ID' => $taskId
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
        $statusType_checklistXML_ID = "checklist_".$statusType_checklistData;

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
        $statusType_checklistXML_ID = "check_list_".$statusType_checklistData;

        $rsEnum = \CUserFieldEnum::GetList(array(), array(
            "XML_ID" => $statusType_checklistXML_ID,
        ));
        if ($arEnum = $rsEnum->Fetch()) {
            $statusType_checklistID = $arEnum['ID'];
        }

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
            'UF_CRM_COMPANY_CHKLST_WB_INC_TO_SALE_FOR_SIX_MONTH' => $data["wbIncomeToSaleForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_OZON_INC_SALE_FOR_SIX_MOUNTH' => $data["ozonIncomeToSaleForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_MARKETPLACE_DURATION_IN_MNTH' => $data["marketplaceDurationInMonths"],
            'UF_CRM_COMPANY_CHKLST_SALES_TO_COUNT_SIX_MTH_RATIO' => $data["salesToCountForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_SALES_TO_RETURN_COUNT_SIX_MH' =>	$data["salesToReturnCountForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_STOK_SUM_LIMIT_SIX_MTH_RATIO' => $data["stockSumToLimitForSixMonthsRatio"],
            'UF_CRM_COMPANY_CHKLST_SMOOTHED_LIM_FOR_SIX_MONTHS' => $data["smoothedLimitForSixMonths"],
            'UF_CRM_COMPANY_CHKLST_PDN' => $data["pdn"]*100,
            'UF_CRM_COMPANY_CHKLST_ANY_FNS_BLOCKED_ACCOUNTS' => $data["anyFnsBlockedAccounts"],
            'UF_CRM_COMPANY_CHKLST_PERSON_VALID_PASSPORT' => $data["personValidPassport"],
            'UF_CRM_COMPANY_CHKLST_LOCATED_IN_LISTS_NAMES' => $data["locatedInListsNames"],
            'UF_CRM_COMPANY_CHKLST_IN_UNRELIABLE_SUPPLIER_LIST' => $data["inUnreliableSupplierList"],
            'UF_CRM_COMPANY_CHKLST_ANY_DISQUALIFIED_DIRECTORS' => $data["anyDisqualifiedDirectors"],
            'UF_CRM_COMPANY_CHKLST_PERSON_AGE_LIST' => $data["personAgeList"][0],//[45]
            'UF_CRM_COMPANY_CHKLST_HAS_PERSON_RUSSIAN_NATIONAL' => $data["hasPersonRussianNationality"],
            'UF_CRM_COMPANY_CHKLST_MAX_ACTIVE_LOAN_OVERDUE_DAYS' => $data["maxActiveLoanOverdueDays"],
            'UF_CRM_COMPANY_CHKLST_ANY_LONG_OVERDUE_LOAN' => $data["anyLongOverdueLoan"],
            'UF_CRM_COMPANY_CHKLST_PERSON_LOAN_DATE' => $data["personLoanDate"],
            'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_MICRO_LOAN_CNT' => $data["personActiveMicroLoanCount"],
            'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_FSSP_SUM' => $data["personActiveFsspSum"],
            'UF_CRM_COMPANY_CHKLST_CLAIMS_SUM_INC_FOR_SIX_MONTH' => $data["claimsSumToIncomeForSixMonthsRatio"],
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
        $checklistStatusType = self::saveChecklistStatusTypeData($factory, $item, $data["checklist"]['statusType']);
        if (is_array($checklistStatusType)) {
            return [
                'status' => 'error',
                'messages' => $checklistStatusType,
            ];
        }

        // Сохраняем данные Checklist
        $checklist = self::saveChecklistData($factory, $item, $data["checklist"]['data']);
        if (is_array($checklist)) {
            return [
                'status' => 'error',
                'messages' => $checklist,
            ];
        }

        // Сохраняем данные RefusedInStatusByData
        $refusedInStatusBy = self::saveRefusedInStatusByData($factory, $item, $data["checklist"]['refusedInStatusByData']);
        if (is_array($refusedInStatusBy)) {
            return [
                'status' => 'error',
                'messages' => $refusedInStatusBy,
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