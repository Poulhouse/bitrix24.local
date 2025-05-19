<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use KPLab\API\V2\LogsAction;
use KPLab\Logs;

define("LOG_API_SYNC_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/KPKLeadsController.log");

\Bitrix\Main\Loader::includeModule('kplab.api.v2');
class KPK extends \Bitrix\Main\Engine\Controller
{

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

    public function setLeadsAction(array $params = []) {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();
        $serverArray = $server->toArray();
        $serverName = $serverArray['SERVER_NAME'];

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $requestArray = json_decode($request->getInput(),true);
        if($requestArray == NULL) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        if(empty($requestArray['leads'])) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Этот запрос не поддерживается. Пустой `leads`', "invalid_request"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
        else {
            $authorization = $server->get('REMOTE_USER');
            $token = str_replace('BitrixAuth ', '', $authorization);

            Loader::includeModule('iblock');
            if($serverName == "crm.seller-capital.ru") {
                $IBLOCK_ID = 183;
                $arOrder = ['ID' => 'ASC'];
                $arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1112" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
                $arGroupBy = false;
                $arNavStartParams = [];
                $arSelect = ["*", "PROPERTY_*"];
                $res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);
            }
            elseif ($serverName == "testcrm.seller-capital.ru") {
                $IBLOCK_ID = 183;
                $arOrder = ['ID' => 'ASC'];
                $arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1112" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
                $arGroupBy = false;
                $arNavStartParams = [];
                $arSelect = ["*", "PROPERTY_*"];
                $res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);
            }

            while($ob = $res->GetNextElement())
            {
                $arProps = $ob -> GetProperties();
                $leadStage = $arProps['STATUS_LEAD']['VALUE_XML_ID'];
                $leadSource = $arProps['SOURCE'];
                Logs\File::AddMessage($leadStage,"leadStage",LOG_API_SYNC_CONTROLLER);
                Logs\File::AddMessage($leadSource,"leadSource",LOG_API_SYNC_CONTROLLER);

                if($leadStage == null) {
                    Context::getCurrent()->getResponse()->setStatus(500);
                    $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
                    return new EventResult(EventResult::ERROR, null, null, $this);
                }

                $this->addLeadToStage($this, $leadStage, $leadSource, $requestArray);
            }
            return true;
        }
    }

    public function setContactDataAction(array $params = []) {
        \Bitrix\Main\Loader::includeModule('kplab.api.v2');
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();
        $serverArray = $server->toArray();
        $serverName = $serverArray['SERVER_NAME'];

        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса

        $url = $server['SCRIPT_URI'];

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $requestArray = json_decode($request->getInput(),true);
        if($requestArray == NULL) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        if(empty($requestArray['contacts'])) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Этот запрос не поддерживается. Пустой `contacts`', "invalid_request"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
        else {
            $entityTypeId = \CCrmOwnerType::Contact;
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
            if (!$factory)
            {
                Context::getCurrent()->getResponse()->setStatus(500);
                $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
                return new EventResult(EventResult::ERROR, null, null, $this);
            }
            foreach ($requestArray['contacts'] as $contact) {
                $items = $factory->getItems([
                    'filter' => [
                        'UF_CRM_6695354473501' => $contact['internalId']
                    ]
                ]);
                if($items){
                    foreach ($items as $k => $item)
                    {
                        $itemData = $item->getData();
                        $contactId = $item->getId();
                        $objectData['ITEM_TITLE'] = $itemData['TITLE'];

                        // Обновление или добавление PHONE
                        if (!empty($contact['phoneValue'])) { // Проверяем, что поле не пустое
                            $phoneFields = [
                                'ENTITY_ID' => 'CONTACT',  // Указываем тип сущности
                                'ELEMENT_ID' => $contactId, // ID контакта
                                'TYPE_ID' => 'PHONE',      // Тип поля: PHONE
                                'VALUE_TYPE' => 'WORK',    // Тип значения: рабочий телефон
                                'VALUE' => $contact['phoneValue'], // Значение телефона
                            ];

                            $fieldMulti = new \CCrmFieldMulti();
                            $fieldMulti->Add($phoneFields); // Добавляем или обновляем телефон
                        }

                        // Обновление или добавление EMAIL
                        if (!empty($contact['emailValue'])) { // Проверяем, что поле не пустое
                            $emailFields = [
                                'ENTITY_ID' => 'CONTACT',  // Указываем тип сущности
                                'ELEMENT_ID' => $contactId, // ID контакта
                                'TYPE_ID' => 'EMAIL',      // Тип поля: EMAIL
                                'VALUE_TYPE' => 'WORK',    // Тип значения: рабочий email
                                'VALUE' => $contact['emailValue'], // Значение email
                            ];

                            $fieldMulti = new \CCrmFieldMulti();
                            $fieldMulti->Add($emailFields); // Добавляем или обновляем email
                        }

                        // Step 1: get operation
                        $operation = $factory -> getUpdateOperation($item);

                        // Step 2: config operation (optional)
                        $operation->disableAllChecks();

                        // Step 3: launch operation
                        $operationResult = $operation -> launch();
                        if ($operationResult -> isSuccess()) {
                            $message = "Данные контакта " . $contact['internalId']. " успешно обновлены";
                            $jsonRes['success'] = $message;
                            $jsonRes['error'] = null;
                            // Логируем информацию
                            LogsAction::Request(
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
                                null,                            // Task ID (если есть)
                                0,                              // ID Типа запроса (если есть)
                                false                              // Тип запроса (если есть)
                            );
                            $entityTypeId = \CCrmOwnerType::Deal;
                            $factoryDeal = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
                            if (!$factoryDeal)
                            {
                                Context::getCurrent()->getResponse()->setStatus(500);
                                $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
                                return new EventResult(EventResult::ERROR, null, null, $this);
                            }
                            $itemsDeal = $factoryDeal->getItems([
                                'filter' => [
                                    "CATEGORY_ID" => "45",
                                    'UF_CRM_669535782EFC4' => $contact['internalId']
                                ]
                            ]);
                            if($itemsDeal){
                                foreach ($itemsDeal as $k => $itemDeal) {

                                    $itemDeal->setStageId("C45:PREPARATION");
                                    // Step 1: get operation
                                    $operationDeal = $factoryDeal -> getUpdateOperation($itemDeal);

                                    // Step 2: config operation (optional)
                                    $operationDeal->disableAllChecks();

                                    // Step 3: launch operation
                                    $operationDealResult = $operationDeal -> launch();
                                    if ($operationResult -> isSuccess()) {

                                    }
                                }
                            }
                            return $jsonRes['success'];
                        }
                    }
                }
            }

        }
    }

    public function postOfferAndStatusLead($outInternalId) {
        $timeData = Logs\TimeData::start();
        $this->point = "MyFi_BX";
        $emulation = false;
        $url = "https://api.mirmyfi.ru/ext/sxxi/applications/status/";
        $TOKEN_KEY = "KQ7IzAjWujnCd6q1wVyUmwINB8Nd02Ezi11Zb2QN";
        global $DB;
        $strLeadsSQL = "
            SELECT * FROM b_crm_lead 
            INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID 
            WHERE b_uts_crm_lead.UF_OUT_INTERNALID = '{$outInternalId}' 
            ORDER BY b_crm_lead.ID ASC";

        $objectData = [
            'ITEM_ID' => "",
            'ITEM_TITLE' => "",
            'ITEM_TYPE_ID' => \CCrmOwnerType::Lead,
            'INIT_OBJECT_URL' => "",
            'METHOD' => 'POST'
        ];
        $resItemsQuery = $DB->query($strLeadsSQL);
        while ($resItem = $resItemsQuery->Fetch()) {

            $objectData['ITEM_ID'] = $resItem['ID'];
            $objectData['ITEM_TITLE'] = "MyFi: ". $resItem['TITLE'];
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/lead/details/".$resItem['ID']."/";
            $arItem = [
                'uuid' => ($resItem['UF_OUT_INTERNALID'] ?? ""),
                'status' => ($resItem['UF_CRM_STATUS_KF'] ?? ""),
                'comment' => "",
                'amount' => $resItem['UF_CRM_1682140471'],
                'term' => 730,
                'rate' => ($resItem['UF_CRM_1680637876'] ?? null)
            ];
        }
        $this->jsonData = json_encode($arItem,JSON_UNESCAPED_UNICODE);
        $this->methodName = __FUNCTION__;
        $this->controllerName = get_class($this);

        $headersRequest = array(
            "Token" => "{$TOKEN_KEY}",
            "Content-Type" => "application/json"
        );

        $logData = [
            'objectData' => $objectData,
            'methodName' => $this->methodName,
            'controllerName' => $this->controllerName,
            'method' => 'POST',
            'timeData' => $timeData,
            'point' => $this->point
        ];

        $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $headersRequest, $this->jsonData, $logData);
        return $jsonResponse;
    }

    public static function addLeadToStage($controller, $stageId, $source, $requestArray) {
        $entityTypeId = \CCrmOwnerType::Lead;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factory)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $controller -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $controller);
        }
        foreach ($requestArray['leads'] as $lead) {
            // пустой элемент, у которого заполнены значения полей по умолчанию. В том числе направление, стадия, кем создан и т.д.
            $newItem = $factory->createItem();

            //region "Паспорт, СНИЛС заемщика"
            $dataPassportArray = $lead['passport'];
            if($dataPassportArray !== NULL) {
                $dataPassportFiles = $dataPassportArray['files'];
                if(!empty($dataPassportFiles)) {
                    $arPassportFiles = array();
                    foreach ($dataPassportFiles as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arPassportFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion

            //region "Дополнительные документы по заемщику"

            $arOtherFiles = array();
            //region СогласияЗаемщика
            $dataFile1Array = $lead['borrowerConsents']; //СогласияЗаемщика
            if($dataFile1Array !== NULL) {
                $dataFile1Files = $dataFile1Array['files'];
                if(!empty($dataFile1Files)) {
                    foreach ($dataFile1Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion СогласияЗаемщика

            //region СогласияЮЛ
            $dataFile2Array = $lead['legalEntityConsents']; //СогласияЮЛ
            if($dataFile2Array !== NULL) {
                $dataFile2Files = $dataFile2Array['files'];
                if(!empty($dataFile2Files)) {
                    foreach ($dataFile2Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion СогласияЮЛ

            //region Карточка51
            $dataFile3Array = $lead['card51']; //Карточка51
            if($dataFile3Array !== NULL) {
                $dataFile3Files = $dataFile3Array['files'];
                if(!empty($dataFile3Files)) {
                    foreach ($dataFile3Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion Карточка51

            //region ОСВ_по_Счетам_66_67
            $dataFile4Array = $lead['OSV_66_67']; //ОСВ_по_Счетам_66_67
            if($dataFile4Array !== NULL) {
                $dataFile4Files = $dataFile4Array['files'];
                if(!empty($dataFile4Files)) {
                    foreach ($dataFile4Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion ОСВ_по_Счетам_66_67

            //region Контракт
            $dataFile5Array = $lead['contract']; //Контракт
            if($dataFile5Array !== NULL) {
                $dataFile5Files = $dataFile5Array['files'];
                if(!empty($dataFile5Files)) {
                    foreach ($dataFile5Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion Контракт

            //region Выписки по всем р/сч
            $dataFile6Array = $lead['extractsAccounts']; //Выписки по всем р/сч
            if($dataFile6Array !== NULL) {
                $dataFile6Files = $dataFile6Array['files'];
                if(!empty($dataFile6Files)) {
                    foreach ($dataFile6Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion Выписки по всем р/сч

            //region Налоговая декларация
            $dataFile7Array = $lead['taxReturn']; //Налоговая декларация
            if($dataFile7Array !== NULL) {
                $dataFile7Files = $dataFile7Array['files'];
                if(!empty($dataFile7Files)) {
                    foreach ($dataFile7Files as $file) {
                        $fileName = $file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arOtherFiles, $fileArray);
                        }
                    }
                }
            }
            //endregion Налоговая декларация
            //endregion

            $fields = [
                'TITLE' => $lead['title'], //Название Лида
                'UF_CRM_1680549527' => $lead['inn'], //ИНН №1
                'UF_CRM_1595501723401' => $lead['requestedAmount'], //Запрашиваемая сумма
                'UF_CRM_1655718054011' => $arPassportFiles, //"Паспорт, СНИЛС заемщика"
                'UF_CRM_1655721162' => $arOtherFiles, //Дополнительные документы по заемщику
                'UF_CRM_LINK_CONTRACT' => $lead['linkContract'],
                "SOURCE_ID" => $source['VALUE_XML_ID'],
                "UF_OUT_INTERNALID" => $lead['internalId'],
                'UTM_SOURCE' => 'lead',
                'UTM_MEDIUM' => 'referral',
                'UTM_CAMPAIGN' => $source['VALUE'],
                'UTM_CONTENT' => $requestArray['partnerInn']
            ];
            $newItem->setFromCompatibleData($fields);
            $newItem->setStageId($stageId);
            $context = new \Bitrix\Crm\Service\Context();
            $context->setUserId(1);

            // операция производится от пользователя $userId с выполнением всех проверок
            $operation = $factory->getAddOperation($newItem, $context);
            $result = $operation->launch();

            return $newItem->getId();
        }
        return null;
    }
}