<?php

namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\Logs;

use KPLab\API\V2\Model\DTO\SellerPersonDTO;
use KPLab\API\V2\Model\DTO\SellerLegalEntityDTO;

define("LOG_SELLER_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_seller.log");

class oldSellerService
{
    protected Sellers\SellerDirectorService $dirService;
    protected Sellers\SellerBeneficiarOwnersService $ownerService;
    protected Sellers\SellerBankAccountService $bankAccountService;
    public string $pathObjectUrl;

    public function __construct() {
        $this->dirService = new Sellers\SellerDirectorService();
        $this->ownerService = new Sellers\SellerBeneficiarOwnersService();
        $this->bankAccountService = new Sellers\SellerBankAccountService();
    }

    /**
     * @throws ArgumentException
     * @throws SqlQueryException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function processPerson(SellerPersonDTO $dto): void
    {
        $inn    = $dto->sellerInn;
        $crmId  = (int)$dto->crmId;
        $data   = $dto->data;

        $cardId = $this->findCard($inn, $crmId);
        // создаём/обновляем основной объект (FL или IP)
        $this->createOrUpdateCard($this, $cardId, $data, false, $cardId, 'seller');
        // создаём/обновляем реквизиты
        $this->createOrUpdateRQ($this, $cardId, $data);

        // Банковские реквизиты
        foreach ($data->bankAccounts as $b) {
            $this->bankAccountService->handle($cardId, $b);
        }
    }

    /**
     * @throws ArgumentException
     * @throws SqlQueryException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function processLegalEntity(SellerLegalEntityDTO $dto): void
    {
        $inn    = $dto->sellerInn;
        $crmId  = (int)$dto->crmId;
        $data   = $dto->data; // DataUL

        $cardId = $this->findCard($inn, $crmId);
        // 1) основной UL
        $this->createOrUpdateCard($this, $cardId, $data, false, $cardId, 'seller');
        $this->createOrUpdateRQ($this, $cardId, $data);

        // 2) директор
        $this->dirService->handle($cardId, $data->director);

        // 3) beneficiars
        foreach ($data->beneficiars ?? [] as $b) {
            $this->ownerService->handle($cardId, $b);
        }

        // 4) Банковские реквизиты
        foreach ($data->bankAccounts as $b) {
            $this->bankAccountService->handle($cardId, $b);
        }
    }

    /**
     * Отправка данных в СМЭВ (внутр.)
     */
    public function postPassportData($companyId = null, $contactId = null): array|string
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
        $idSERequest = '';

        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();

        $headersValues = array(
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json; charset=utf-8",
            "accept" => "application/json"
        );

        $requestJson = $context->getRequest()->getInput();
        $requestMethod = "POST";
        $queryParamsArray = $context->getRequest()->toArray();
        $this->CURLObjectData['METHOD'] = $requestMethod;

        $errors = [];
        if (str_contains($serverName, 'test')) {
            $apiUrl = "https://api.dev.seller-capital.ru";
        } else {
            $apiUrl = "https://api.seller-capital.ru";
        }
        $url = $apiUrl . "/SendRequest";

        $data = [];
        if(!is_null($companyId)) {
            $this->getCompanyInfoById($companyId);
            $this->setCURLObjectData($companyId);
            $rqId = $this->rqId;
            $this->CURLObjectData['ITEM_TITLE'] = "Отправка данных в СМЭВ: ". $this->itemDatatitle;
            $payload = [
                "name" => (string) $this->sellerFirstName,
                "surname" => (string) $this->sellerLastName,
                "patronymic" => (string) $this->sellerSecondName,
                "pass_series" => (string) $this->sellerPassportSeries,
                "pass_number" => (string) $this->sellerPassportNumber,
                "birthdate" => (string) $this->sellerPassportBirthday,
                //"inn" => (string) $this->sellerInn,
                "gender" => null
            ];
            $data["request"] = [
                "payload" => $payload,
                "callback_url" => "https://{$serverName}/api/v2/sellers/smavInfo/?authId=5d0e5072-889b-52cd-950c-af8d58221115&crmEntityId=company_{$companyId}&rqId={$rqId}"
            ];
        }
        elseif(!is_null($contactId)) {
            $this->getContactInfoById($contactId);
            $this->setCURLObjectData($contactId);
            $rqId = $this->rqId;
            $this->CURLObjectData['ITEM_TITLE'] = "Отправка данных в СМЭВ: ". $this->itemDatatitle;
            $payload = [
                "name" => (string) $this->sellerFirstName,
                "surname" => (string) $this->sellerLastName,
                "patronymic" => (string) $this->sellerSecondName,
                "pass_series" => (string) $this->sellerPassportSeries,
                "pass_number" => (string) $this->sellerPassportNumber,
                "birthdate" => (string) $this->sellerPassportBirthday,
                "inn" => (string) $this->sellerInn,
                "gender" => null
            ];
            $data["request"] = [
                "payload" => $payload,
                "callback_url" => "https://{$serverName}/api/v1/sellers/smavInfo/?authId=5d0e5072-889b-52cd-950c-af8d58221115&crmEntityId=contact_{$contactId}&rqId={$rqId}"
            ];
        }
        $requestJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $objectData = $this->CURLObjectData;

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
            $context,
            true
        );
        //endregion

        $jsonResponse = $HandlerResponse->getResponse($objectData);

        if(!is_null($companyId)) {
            $item = $factoryCompany->getItem($companyId);
            $arResponse = json_decode($jsonResponse['response'],true);
            $idSERequest = $arResponse['id'];
            $item->set('UF_CRM_SMEV_ID_REQUEST',$idSERequest);

            $operation = $factoryCompany->getUpdateOperation($item);
            $operation->disableAllChecks();

            // Сохраняем элемент CRM после установки всех полей
            $saveResult = $operation->launch();

            if (!$saveResult->isSuccess()) {
                return array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
            }
        }

        if(!is_null($contactId)) {
            $item = $factoryContact->getItem($contactId);
            $arResponse = json_decode($jsonResponse['response'],true);
            $idSERequest = $arResponse['id'];
            $item->set('UF_CRM_SMEV_ID_REQUEST',$idSERequest);

            $operation = $factoryContact->getUpdateOperation($item);
            $operation->disableAllChecks();

            // Сохраняем элемент CRM после установки всех полей
            $saveResult = $operation->launch();

            if (!$saveResult->isSuccess()) {
                return array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
            }
        }

        return $jsonResponse;

    }

    /**
     * Получение данных от СМЭВ (внутр.)
     */
    public function getSMEVStatus($companyId = null, $contactId = null): array|string
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
        $idSERequest = '';

        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();

        $headersValues = array(
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json; charset=utf-8",
            "accept" => "application/json"
        );

        $requestJson = $context->getRequest()->getInput();
        $requestMethod = "GET";
        $queryParamsArray = $context->getRequest()->toArray();
        $this->CURLObjectData['METHOD'] = $requestMethod;

        if (str_contains($serverName, 'test')) {
            $apiUrl = "https://api.dev.seller-capital.ru";
        } else {
            $apiUrl = "https://api.seller-capital.ru";
        }

        if (!is_null($companyId)) {
            $this->getCompanyInfoById($companyId);
            $this->setCURLObjectData($companyId);

            $item = $factoryCompany->getItem($companyId);
            $itemData = $item->getData();
            $idSERequest = $itemData['UF_CRM_SMEV_ID_REQUEST'];
        }
        if (!is_null($contactId)) {
            $this->getContactInfoById($contactId);
            $this->setCURLObjectData($contactId);

            $item = $factoryContact->getItem($contactId);
            $itemData = $item->getData();
            $idSERequest = $itemData['UF_CRM_SMEV_ID_REQUEST'];
        }

        $url = $apiUrl . "/GetResponse?id={$idSERequest}";
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Получение данных от СМЭВ: " . $this->itemDatatitle;
        $objectData = $this->CURLObjectData;

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
            $context,
            true
        );
        //endregion

        $jsonResponse = $HandlerResponse->getResponse($objectData);

        $arResponse = json_decode($jsonResponse['response'], true);
        $services = $arResponse['response']["services"];

        //region Сохраняем данные и проверяем результат
        $saveResult = [];
        if(!is_null($contactId)) $saveResult = $this->saveAllData($factoryContact, $item, $services);
        if(!is_null($companyId)) $saveResult = $this->saveAllData($factoryCompany, $item, $services);

        if ($saveResult['status'] === 'error') {
            // Если произошла ошибка, добавляем комментарий с сообщениями об ошибках
            $message = "Ошибка при сохранении данных: " . implode(', ', $saveResult['messages']);
        }
        else {
            $message = "Данные успешно сохранились";
        }
        //endregion

        return $jsonResponse;
    }

    /**
     *  Поиск карточки (внутр.)
     * @param $controller
     * @param $dataInn
     * @param bool|int $crmId
     * @return EventResult|false|int|mixed
     */
    public function findCard($controller, $dataInn, bool|int $crmId = false): mixed
    {
        $cardId = false;
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);

        if(!$crmId) {
            $params = [
                'filter' => [
                    'UF_CRM_6433D7C925893' => $dataInn,
                ],
                'select' => ['ID']
            ];
            $itemsCompany = $factoryCompany -> getItems($params);
            foreach ($itemsCompany as $itemCompany)
            {
                $cardId = $itemCompany->getId();
            }

            $this->pathObjectUrl = "/crm/type/company/details/{$cardId}/";

            return $cardId;
        }
        else {
            $entityTypeId = 128;
            $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
            $itemLK = $factory -> getItem($crmId);
            if($itemLK) {
                $itemLKData = $factory -> getItem($crmId)->getData();
                $cardId = $itemLKData['COMPANY_ID'];

                $this->pathObjectUrl = "/crm/type/company/details/{$cardId}/";

                return $cardId;
            } else {
                $errorMessage = 'Ошибка `crmId` не известен';

                Context::getCurrent()->getResponse()->setStatus(404);
                $controller -> addError(new Error($errorMessage, "invalid_request"));
                return new EventResult(EventResult::ERROR, null, null, $this);
            }
        }
    }

    /**
     * Поиск карточки по ГУИД сделки (внутр.)
     * @param $dataInn
     * @param string $dealGUID
     * @return array|EventResult|false|int|void
     */
    public function findCardByDealGUID($dataInn, string $dealGUID = "") {
        $cardId = false;
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);

        if($dealGUID == "") {
            $params = [
                'filter' => [
                    'UF_CRM_6433D7C925893' => $dataInn,
                ],
                'select' => ['ID']
            ];
            $itemsCompany = $factoryCompany -> getItems($params);
            //Logs\File ::AddMessage($itemsCompany, "itemsCompany", LOG_API_SYNC_SELLER_CONTROLLER);
            foreach ($itemsCompany as $itemCompany)
            {
                $cardId = $itemCompany->getId();
            }

            return $cardId;
        }
        else {
            $entityTypeId = \CCrmOwnerType::Deal;
            $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
            $params = [
                'filter' => [
                    'UF_CRM_GUID' => $dealGUID,
                ],
                'select' => ['ID','COMPANY_ID']
            ];
            $deals = $factory -> getItems($params);
            foreach ($deals as $deal) {
                $dealData = $deal->getData();
                $dealId = $dealData['ID'];
                $companyId = $dealData['COMPANY_ID'];

                $this->pathObjectUrl = "/crm/type/2/details/{$dealId}/";

                return ['ID' => $dealId, 'COMPANY_ID' => $companyId];
            }
        }
    }

    /**
     * Создание или обновление карточки компании (внутр.)
     * @param $sellerCardId
     * @param $dataArray
     * @param $createCard
     * @param $currentCardId
     * @param string $type
     * @param null $crmId
     * @return EventResult|int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function createOrUpdateCard($sellerCardId, $dataArray, $createCard, $currentCardId, string $type = "", $crmId = null): int|EventResult
    {
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $entityTypeIdLK = 128;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
        $factoryLK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLK);

        //region Обновление Карточки
        if(!$createCard && !is_null($currentCardId)) {
            $itemSeller = $factoryCompany -> getItem($sellerCardId);
            if($crmId) $itemLK = $factoryLK->getItem($crmId);
            Logs\File ::AddMessage($currentCardId, "currentCardId", LOG_API_SYNC_SELLER_CONTROLLER);
            $item = $factoryCompany -> getItem($currentCardId);

            //region "Тип клиента (Организационно-правовая форма)"
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()){
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => $dataArray['type']]);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }

            $item->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
            //endregion

            $item->set("UF_CRM_COMPANY_SS_ORG", [5]); //Организация
            $item->set("UF_CRM_6433DBB98DD53", 17611); //Филиал

            if (!empty($dataArray['phone'])) {
                $arPhone = array(
                    'ENTITY_ID' => 'COMPANY',   // Тип сущности - COMPANY
                    'ELEMENT_ID' => $currentCardId,   // ID Контакта
                    'TYPE_ID' => 'PHONE',
                    'VALUE_TYPE' => 'WORK',
                    'VALUE' => $dataArray['phone']      // Телефон
                );

                $multi = new \CCrmFieldMulti();
                $multi->Add($arPhone);
            }
            if (!empty($dataArray['email'])) {
                $arEmail = array(
                    'ENTITY_ID' => 'COMPANY',   // Тип сущности - COMPANY
                    'ELEMENT_ID' => $currentCardId,   // ID Контакта
                    'TYPE_ID' => 'EMAIL',
                    'VALUE_TYPE' => 'WORK',
                    'VALUE' => $dataArray['email']      // Email
                );
                $multi = new \CCrmFieldMulti();
                $multi->Add($arEmail);
            }

            //region "Сервис ЭДО"
            $serviceEDO = $dataArray['serviceEDO'];
            $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "Edo_".$serviceEDO,
            ));
            if ($arEnumEDO = $rsEnumEDO -> Fetch())
            {
                $serviceEDOId = $arEnumEDO['ID'];
            }
            Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
            $item->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
            //endregion

            //region "Ссылки на маркетплейсы"
            $marketplaceLinks = $dataArray['marketplaceLinks'];
            $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
            //endregion

            //region "id синхронизации с SE"
            $syncId = $dataArray['synchId'];
            $item -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
            //endregion

            //region "Изменено ЛК"
            $item->set("UF_CRM_UPDATE_INFO_LK", true);
            //endregion

            //region "Ручное заполнение паспорта"
            $isManual = $dataArray['isManual'];
            $item->set("UF_CRM_PASSPORT_IS_MANUAL", $isManual);
            //endregion

            //region "Устав компании SC"
            $dataCompanyCharterFile = $dataArray['charterFile'];
            if($dataCompanyCharterFile !== NULL) {
                $arFile = array();
                $fileName = floor(microtime(true) * 1000)."_".$dataCompanyCharterFile["fileName"];
                $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                file_put_contents($filePathName, base64_decode ($dataCompanyCharterFile["file"]));//Запись на системный диск
                $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                if ($fileId) {
                    $fileArray = \CFile::MakeFileArray($fileId);
                    array_push($arFile, $fileArray);
                } else {
                    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
                $fields = [
                    'UF_CRM_COMPANY_CHARTER' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            } else {
                Logs\File::AddMessage('Пустой массив dataCompanyCharterFile', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
            //endregion

            //region "Приказ на директора SC"
            $dataOrderDirectorFile = $dataArray['orderDirector'];
            if($dataOrderDirectorFile !== NULL) {
                $arFile = array();
                $fileName = floor(microtime(true) * 1000)."_".$dataOrderDirectorFile["fileName"];
                $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                file_put_contents($filePathName, base64_decode ($dataOrderDirectorFile["file"]));//Запись на системный диск
                $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                if ($fileId) {
                    $fileArray = \CFile::MakeFileArray($fileId);
                    array_push($arFile, $fileArray);
                } else {
                    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
                $fields = [
                    'UF_CRM_ORDER_FOR_DIRECTOR' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            }
            else {
                Logs\File::AddMessage('Пустой массив dataOrderForDirectorArray', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
            //endregion

            //region "Паспорт, СНИЛС заемщика"
            $dataPassportArray = $dataArray['passport'];
            if($dataPassportArray !== NULL) {
                $dataPassportFiles = $dataPassportArray['files'];
                if(!empty($dataPassportFiles)) {
                    $arFile = array();
                    foreach ($dataPassportFiles as $file) {
                        $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arFile, $fileArray);
                        } else {
                            Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                        }
                    }
                    $fields = [
                        'UF_CRM_6433D94467769' => $arFile,
                    ];
                    $item->setFromCompatibleData($fields);
                } else {
                    Logs\File::AddMessage('Пустой массив dataPassportFiles', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
            }
            //endregion

            $operation = $factoryCompany->getUpdateOperation($item);
            $operation->disableAllChecks();
            $operation->launch();

            $itemId = $item->getId();

            //region Обновление Карточки бенефициаров
            if($type == "beneficiar")
            {
                //region "Бенефициар X" в Карточке Селлера
                $_beneficiars = [
                    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
                    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
                    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
                    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
                    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
                ];

                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_beneficiars)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_beneficiars as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemSeller->set($key, $currentCardId);
                            $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
                //endregion
            }
            //endregion

            //region Обновление Карточки руководителя
            if($type == "director") {
                //region "Руководитель (представитель)" в Карточке Селлера
                $itemSeller->set("UF_CRM_1615200179", "CO_".$currentCardId);
                //endregion

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion


            //region Обновление Карточки Поручителя
            if($type == "guarantor") {
                //region "Поручитель X" в Карточке ЛК
                if($crmId)
                {
                    $_guarantors = [
                        'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
                        'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
                        'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
                        'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
                        'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
                    ];
                    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                    // Проверка на наличие ID в массиве
                    if (!in_array($currentCardId, $_guarantors))
                    {
                        // ID не найден, ищем первое свободное поле
                        foreach ($_guarantors as $key => $value)
                        {
                            if (empty($value))
                            {
                                // Нашли свободное поле, записываем туда ID
                                $itemLK -> set($key, $currentCardId);
                                break; // Выходим из цикла, так как запись произведена
                            }
                        }
                    }
                }
                //endregion
            }
            //endregion
        }
        //endregion

        //region Создание Карточки
        elseif(is_null($currentCardId)) {
            $newItem = $factoryCompany->createItem();
            $itemSeller = $factoryCompany->getItem($sellerCardId);
            if($crmId) $itemLK = $factoryLK->getItem($crmId);

            //region "Тип клиента (Организационно-правовая форма)"
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()){
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => $dataArray['type']]);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }
            $newItem->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
            //endregion

            $newItem->set("UF_CRM_COMPANY_SS_ORG", [5]); //Организация
            $newItem->set("UF_CRM_6433DBB98DD53", 17611); //Филиал

            //region "Сервис ЭДО"
            $serviceEDO = $dataArray['serviceEDO'];
            $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "Edo_".$serviceEDO,
            ));
            if ($arEnumEDO = $rsEnumEDO -> Fetch())
            {
                $serviceEDOId = $arEnumEDO['ID'];
            }
            Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
            $newItem->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
            //endregion

            //region "Ссылки на маркетплейсы"
            $marketplaceLinks = $dataArray['marketplaceLinks'];
            $newItem -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
            //endregion

            //region "id синхронизации с SE"
            $syncId = $dataArray['synchId'];
            $newItem -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
            //endregion

            //region "Изменено ЛК"
            $newItem->set("UF_CRM_UPDATE_INFO_LK", true);
            //endregion

            //region "Ручное заполнение паспорта"
            $isManual = $dataArray['isManual'];
            $newItem->set("UF_CRM_PASSPORT_IS_MANUAL", $isManual);
            //endregion

            //region "Паспорт, СНИЛС заемщика"
            $dataPassportArray = $dataArray['passport'];
            if($dataPassportArray !== NULL) {
                $dataPassportFiles = $dataPassportArray['files'];
                if(!empty($dataPassportFiles)) {
                    $arFile = array();
                    foreach ($dataPassportFiles as $file) {
                        $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arFile, $fileArray);
                        } else {
                            Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                        }
                    }
                    $fields = [
                        'UF_CRM_6433D94467769' => $arFile,
                    ];
                    $newItem->setFromCompatibleData($fields);
                }
            }
            //endregion

            //region "ИНН (SCP)"
            $newItem->set("UF_CRM_6433D7C925893", $dataArray['inn']);
            //endregion

            //region Создание Карточки руководителя
            if ($type == "director") {

                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion

            //region Создание Карточки бенефициаров
            if ($type == "beneficiar") {

                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);



                //region "Бенефициар X" в Карточке Селлера
                $_beneficiars = [
                    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
                    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
                    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
                    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
                    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
                ];

                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_beneficiars)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_beneficiars as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemSeller->set($key, $currentCardId);
                            $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
                //endregion

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion

            //region Создание Карточки Поручителя
            if($type == "guarantor") {
                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);

                //region "Поручитель X" в Карточке ЛК
                $_guarantors = [
                    'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
                    'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
                    'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
                    'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
                    'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
                ];
                //endregion
                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_guarantors)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_guarantors as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemLK->set($key, $currentCardId);
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
            }
            //endregion

            $operation = $factoryCompany->getAddOperation($newItem);
            $operation->disableAllChecks();
            $operation->launch();
            $itemId = $newItem->getId();
        }
        //endregion

        $operationOnlySeller = $factoryCompany->getUpdateOperation($itemSeller);
        $operationOnlySeller->disableAllChecks();
        $operationOnlySeller->launch();


        $this->pathObjectUrl = "/crm/type/4/details/{$itemId}/";

        Logs\File ::AddMessage($itemId, "Получение ID карточки компании",LOG_API_SYNC_SELLER_CONTROLLER);

        return $itemId;
    }

    /**
     * Изменение стадии карточки сделки (внутр.)
     * @param $dealId
     * @param $stageId
     * @return void
     */
    public function changeStageDealCard($dealId,$stageId): void
    {
        $entityTypeId = \CCrmOwnerType::Deal;
        $factoryDeal = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $item = $factoryDeal->getItem($dealId);

        $item?->setStageId($stageId);

        $operation = $factoryDeal->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operationResult = $operation->launch();

        if (!$operationResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $operationResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item->getId(),
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b]{$message}[/b]"
                ]
            ]);
        }
    }

    /**
     * Обновление карточки компании (внутр.)
     * @param $companyId
     * @param $dataArray
     * @return int|null
     * @throws ArgumentException
     */
    public function updateCompanyCard($companyId, $dataArray): int|null
    {
        $entityTypeId = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

        $isAcceptPersonalInfo = $dataArray['isAcceptPersonalInfo'];
        $isAcceptPEPInfo = $dataArray['isAcceptPEPInfo'];
        $_type = $dataArray['type'];
        $marketplaceLinks = $dataArray['marketplaceLinks'];

        $item = $factoryCompany -> getItem($companyId);

        //region "Тип клиента (Организационно-правовая форма)"
        if($_type == "IP") {
            $rsEnumType = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "IP",
            ));
        }
        elseif($_type == "UL") {
            $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
                "XML_ID" => "ORG",
            ));
        }
        elseif($_type == "FL") {
            $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
                "XML_ID" => "FL",
            ));
        }

        if ($arEnumType = $rsEnumType -> Fetch())
        {
            $TypeId = $arEnumType['ID'];
        }
        $item->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
        //endregion

        //region "Ссылки на маркетплейсы"
        $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
        //endregion

        //region Согласия
        $item->set("UF_CRM_ACCEPT_PERSONAL_INFO", $isAcceptPersonalInfo); //согласие человека на обработку перс данных
        $item->set("UF_CRM_1726058034", $isAcceptPEPInfo); //согласие человека на подписание ПЭП
        //endregion

        //region "Паспорт, СНИЛС заемщика"
        $dataPassportArray = $dataArray['passport'];
        if($dataPassportArray !== NULL) {
            $dataPassportFiles = $dataPassportArray['files'];
            if(!empty($dataPassportFiles)) {
                $arFile = array();
                foreach ($dataPassportFiles as $file) {
                    $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                    $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                    file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                    $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                    $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                    if ($fileId) {
                        $fileArray = \CFile::MakeFileArray($fileId);
                        array_push($arFile, $fileArray);
                    } else {
                        Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                    }
                }
                $fields = [
                    'UF_CRM_6433D94467769' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            } else {
                Logs\File::AddMessage('Пустой массив dataPassportFiles', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
        }
        //endregion

        $operation = $factoryCompany->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operationResult = $operation->launch();

        if (!$operationResult->isSuccess()) {
            $message = "Ошибка при обновлении компании: " . implode(", ", $operationResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item->getId(),
                    "ENTITY_TYPE" => "COMPANY",
                    "COMMENT" => "[b]{$message}[/b]"
                ]
            ]);
            return null;
        }

        return $item->getId();
    }

    /**
     * Получение информации о компании по ИД (внутр.)
     * @param $companyId
     * @return $this
     * @throws SqlQueryException
     */
    public function getCompanyInfoById($companyId): static {
        $this->companyId = $companyId;
        $this->entityTypeId = \CCrmOwnerType::Company;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->companyId);
        if($item) {
            $this->itemDatatitle = $item->getData()['TITLE'];
            $this->rqId = $this->findRequisite(\CCrmOwnerType::Company, $companyId);
            global $DB;
            $RQItemSQL = "SELECT * FROM b_crm_requisite INNER JOIN b_uts_crm_requisite ON b_crm_requisite.ID = b_uts_crm_requisite.VALUE_ID WHERE ENTITY_ID='{$companyId}' AND ID='" . $this->rqId . "' ORDER BY ID ASC;";
            $resRQItemsQuery = $DB->query($RQItemSQL);
            while($resRQItem = $resRQItemsQuery->Fetch()) {
                $this->sellerInn = (string) $resRQItem['RQ_INN'];
                $this->sellerLastName = (string) $resRQItem['RQ_LAST_NAME'];
                $this->sellerFirstName = (string) $resRQItem['RQ_FIRST_NAME'];
                $this->sellerSecondName = (string) $resRQItem['RQ_SECOND_NAME'];
                $this->sellerPassportBirthday = (string) date('Y-m-d', strtotime($resRQItem['UF_CRM_1684493639']));
                $this->sellerPassportNumber = (string) $resRQItem['RQ_IDENT_DOC_NUM'];
                $this->sellerPassportSeries = (string) $resRQItem['RQ_IDENT_DOC_SER'];
            }
        }
        return $this;
    }

    /**
     * Получение информации о контакте по ИД (внутр.)
     * @param $contactId
     * @return $this
     * @throws SqlQueryException
     */
    public function getContactInfoById($contactId): static {
        $this->contactId = $contactId;
        $this->entityTypeId = \CCrmOwnerType::Contact;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->contactId);
        if($item) {
            $this->rqId = $this->findRequisite(\CCrmOwnerType::Contact, $contactId);
            global $DB;
            $RQItemSQL = "SELECT * FROM b_crm_requisite INNER JOIN b_uts_crm_requisite ON b_crm_requisite.ID = b_uts_crm_requisite.VALUE_ID WHERE ENTITY_ID='{$contactId}' AND ID='" . $this->rqId . "' ORDER BY ID ASC;";
            $resRQItemsQuery = $DB->query($RQItemSQL);
            while($resRQItem = $resRQItemsQuery->Fetch()) {
                $this->sellerInn = (string) $resRQItem['RQ_INN'];
                $this->sellerLastName = (string) $resRQItem['RQ_LAST_NAME'];
                $this->sellerFirstName = (string) $resRQItem['RQ_FIRST_NAME'];
                $this->sellerSecondName = (string) $resRQItem['RQ_SECOND_NAME'];
                $this->sellerPassportBirthday = (string) date('Y-m-d', strtotime($resRQItem['UF_CRM_1684493639']));
                $this->sellerPassportNumber = (string) $resRQItem['RQ_IDENT_DOC_NUM'];
                $this->sellerPassportSeries = (string) $resRQItem['RQ_IDENT_DOC_SER'];

            }

            $this->itemDatatitle = $this->sellerLastName . " " . $this->sellerFirstName . " " . $this->sellerSecondName;
        }
        return $this;
    }

    /**
     * Установка ObjectData элемента (внутр.)
     * @param $itemId
     * @return mixed
     */
    public function setObjectData($itemId): mixed {
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $this->objectData['ITEM_ID'] = $itemId;
        $this->objectData['ITEM_TYPE_ID'] = $this->entityTypeId;
        $this->objectData['ITEM_TITLE'] = $this->itemDatatitle;

        if (strpos($serverName, 'test') !== false) {
            $this->objectData['INIT_OBJECT_URL'] = "https://testcrm.seller-capital.ru/crm/type/{$this->entityTypeId}/details/{$itemId}/";
        } else {
            $this->objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$this->entityTypeId}/details/{$itemId}/";
        }

        return $this->objectData;
    }

    /**
     * Поиск реквизитов (внутр.)
     * @param $entityTypeId
     * @param $cardId
     * @param $inn
     * @return mixed|null
     */
    private function findRequisite($entityTypeId, $cardId, $inn = null): mixed
    {
        $filter = ["ENTITY_TYPE_ID" => $entityTypeId, "ENTITY_ID" => $cardId];
        $filter["RQ_INN"] = $inn;

        $requisiteList = \CRest::call("crm.requisite.list", [
            "filter" => $filter,
            "select" => ['ID', "PRESET_ID", "ENTITY_ID", "ENTITY_TYPE_ID"]
        ]);
        Logs\File::AddMessage($requisiteList, "requisiteList Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        $requisite = $requisiteList['result'];

        return $requisite ? $requisite[0]['ID'] : null;
    }

    /**
     * Поиск реквизитов компании по ИНН (внутр.)
     * @param $cardId
     * @param false|null $inn
     * @return mixed|null
     */
    private function findCompanyRQ($cardId, $inn = null): mixed {
        if(!is_null($inn)) {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "First requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        if(isset($requisite)) {
            return $requisite[0]['ID'];
        } else {
            return null;
        }


    }

    /**
     * Поиск реквизитов контакта по ИНН (внутр.)
     * @param $cardId
     * @param $inn
     * @return mixed|null
     */
    private function findContactRQ($cardId, $inn = null): mixed {
        if(!is_null($inn)) {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Contact, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Contact, "ENTITY_ID" => $cardId],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "First requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        if(isset($requisite)) {
            return $requisite[0]['ID'];
        } else {
            return null;
        }
    }

    /**
     * Создание или обновление реквизитов (внутр.)
     * @param $controller
     * @param $cardId
     * @param $dataArray
     * @param bool $createRQ
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    public function createOrUpdateRQ($cardId, $dataArray, $createRQ = false): void
    {
        $type = $dataArray['type'];
        $inn = $dataArray['inn'];
        $kpp = $dataArray['kpp'];
        $ogrnip = $dataArray['ogrnip'];
        $ogrn = $dataArray['ogrn'];
        $okpo = $dataArray['okpo'];
        $okved = $dataArray['okved'];
        $companyRegDate = $dataArray['companyRegDate'];
        $companyName = $dataArray['companyName'];
        $companyFullName = $dataArray['companyFullName'];
        $fnsDepartment = $dataArray['fnsDepartment'];
        $firstName = $dataArray['firstName'];
        $lastName = $dataArray['lastName'];
        $secondName = $dataArray['secondName'];
        $birthday = $dataArray['birthday'];
        $birthPlace = $dataArray['birthPlace'];
        $serviceEDO = $dataArray['serviceEDO'];

        $passportArray = $dataArray['passport'];
        $passportIssuer = $passportArray['issuer'];
        $passportNumber = $passportArray['number'];
        $passportSeries = $passportArray['series'];
        $passportIssuedAt = $passportArray['issuedAt'];
        $passportIssuerCode = $passportArray['issuerCode'];

        $rqId = $this->findCompanyRQ($cardId, $inn);
        Logs\File ::AddMessage($rqId, "rqId {$cardId} Update", LOG_API_SYNC_SELLER_CONTROLLER);

        if(isset($rqId)) {

            if($type === "IP" || $type === "FL")
            {
                $params = [
                    "id" => $rqId,
                    "fields" => [
                        'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
                        'NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_FIRST_NAME' => $firstName,
                        'RQ_LAST_NAME' => $lastName,
                        'RQ_SECOND_NAME' => $secondName,
                        'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                        'RQ_IDENT_DOC_SER' => $passportSeries,
                        'RQ_IDENT_DOC_NUM' => $passportNumber,
                        'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
                        'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
                        'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
                        'UF_CRM_1647929611' => $birthPlace,
                        'UF_CRM_1684493639' => $birthday,
                        'RQ_INN' => $inn,
                        'RQ_OGRNIP' => $ogrnip,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                    ]
                ];
            }
            if($type === "UL")
            {
                $params = [
                    "id" => $rqId,
                    "fields" => [
                        'TITLE' => $companyName,
                        'NAME' => $companyName,
                        'RQ_INN' => $inn,
                        'RQ_KPP' => $kpp,
                        'RQ_OGRN' => $ogrn,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                        'RQ_COMPANY_NAME' => $companyName,
                        'RQ_COMPANY_FULL_NAME' => $companyFullName,
                    ]
                ];
            }

            \CRest ::call('crm.requisite.update', $params);



            $_requisite = \CRest::call(
                "crm.requisite.get",
                array("id" => $rqId)
            )['result'];

            Logs\File ::AddMessage($_requisite, "requisite Info After Update for {$cardId}",
                LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {

            if($type === "IP") $PRESET_ID = 2;
            if($type === "FL") $PRESET_ID = 2;
            if($type === "UL") $PRESET_ID = 1;

            if($type === "IP" || $type === "FL")
            {
                $params = [
                    "fields" => [
                        "ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
                        "ENTITY_ID" => $cardId,
                        "PRESET_ID" => $PRESET_ID,
                        'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
                        'NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_FIRST_NAME' => $firstName,
                        'RQ_LAST_NAME' => $lastName,
                        'RQ_SECOND_NAME' => $secondName,
                        'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                        'RQ_IDENT_DOC_SER' => $passportSeries,
                        'RQ_IDENT_DOC_NUM' => $passportNumber,
                        'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
                        'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
                        'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
                        'UF_CRM_1647929611' => $birthPlace,
                        'UF_CRM_1684493639' => $birthday,
                        'RQ_INN' => $inn,
                        'RQ_OGRNIP' => $ogrnip,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                    ]
                ];
                $rqId = \CRest ::call('crm.requisite.add', $params)['data'];
            }
            if($type === "UL")
            {
                $params = [
                    "fields" => [
                        "ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
                        "ENTITY_ID" => $cardId,
                        "PRESET_ID" => $PRESET_ID,
                        'TITLE' => $companyName,
                        'NAME' => $companyName,
                        'RQ_INN' => $inn,
                        'RQ_KPP' => $kpp,
                        'RQ_OGRN' => $ogrn,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                        'RQ_COMPANY_NAME' => $companyName,
                        'RQ_COMPANY_FULL_NAME' => $companyFullName,
                    ]
                ];
                $rqId = \CRest ::call('crm.requisite.add', $params)['data'];
            }

            Logs\File ::AddMessage($rqId, "rqId {$cardId}  Create", LOG_API_SYNC_SELLER_CONTROLLER);
        }

        if(isset($rqId))
        {
            $addressArray = $dataArray['address'];
            foreach ($addressArray as $address)
            {
                $addressFiasId = $address['fiasId'];

                $http = new HttpClient();
                $http->setHeader('Content-Type', 'application/json');
                $http->setHeader('Accept', 'application/json');
                $http->setHeader('Authorization', 'Token 440b60bed73f6e0d78a0eb09ca91971f8c079590');
                $requestBody = [
                    'query' => $addressFiasId
                ];
                $http->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address", json_encode($requestBody));

                $responseJson = $http->getResult();
                $responseArray = json_decode($responseJson, true);
                $addressData = $responseArray['suggestions'][0]['data'];

                if ($address['type'] == "registration") $addressTypeId = 4;
                if ($address['type'] == "actual") $addressTypeId = 1;
                if ($address['type'] == "legal") $addressTypeId = 6;
                $addressCity = $addressData['city'];
                $addressFlat = $addressData['flat'];
                $addressHouse = $addressData['house'];
                $addressRegion = $addressData['region'];
                $addressDistrict = $addressData['city_district'];
                $addressStreet = $addressData['street'];
                $addressBuilding = $addressData['block'];
                $addressStructure = $addressData['block'];
                $addressCountry = $addressData['country'];
                $addressPostalCode = $addressData['postalCode'];

                //код добавления данного типа адреса в реквизит карточки клиента
                $arAddress['ENTITY_ID'] = intval($rqId);//id requisite
                $arAddress['TYPE_ID'] = $addressTypeId;//Адрес регистрации
                $arAddress['ENTITY_TYPE_ID'] = \CCrmOwnerType::Requisite;//Реквизит 8
                $arAddress['ANCHOR_ID'] = $cardId;// ID Компании
                $arAddress['POSTAL_CODE'] = $addressPostalCode;// Индекс
                $arAddress['COUNTRY'] = $addressCountry;// Страна
                $arAddress['PROVINCE'] = $addressRegion;// регион
                $arAddress['REGION'] = $addressDistrict;// Район
                $arAddress['CITY'] = $addressCity;// Город
                $arAddress['ADDRESS_1'] = $addressStreet . ", " . $addressHouse . ", " . $addressStructure . ", " . $addressBuilding;
                //Улица, дом, корпус, строение.
                $arAddress['ADDRESS_2'] = $addressFlat;// Квартира / офис.
                $arAddress['COUNTRY_CODE'] = 643;// Код страны
                $resultAddress = \CRest ::call('crm.address.add', ['fields' => $arAddress]);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressStreet, 'STREET', $addressTypeId,$addressFiasId);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressHouse, 'BUILDING', $addressTypeId,$addressFiasId);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressFiasId, 'FIAS_ID', $addressTypeId, $addressFiasId);

                Logs\File ::AddMessage($arAddress, "arAddress " . $address['type'], LOG_API_SYNC_SELLER_CONTROLLER);
            }
        }
    }

    /**
     * Обновление адреса (внутр.)
     * @param $id
     * @param $entityTypeId
     * @param $dataField
     * @param $nameField
     * @param $typeId
     * @param null $fiasId
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    private function addressUpdate($id, $entityTypeId, $dataField, $nameField, $typeId, $fiasId = null): void
    {
        global $DB;
        $Address = new \Bitrix\Location\Controller\Address;


        $resAddrList = \CRest::call('crm.address.list', array(
            'filter' => array('ANCHOR_ID' => $id, 'ANCHOR_TYPE_ID' => $entityTypeId),
            'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','ANCHOR_TYPE_ID','LOC_ADDR_ID')
        ))['result'];

        if(empty($resAddrList)) {
            return;
        }

        foreach($resAddrList as $i => $addrItem){
            if($addrItem['TYPE_ID'] == $typeId)
            {
                $LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];

                $beforeStrSQL = "SELECT * FROM b_location_addr_fld WHERE ADDRESS_ID = ".$LOC_ADDR_ID;
                $beforeResults = $DB->Query($beforeStrSQL);

                if($dataField !== "" && $nameField == "STREET")
                {
                    $streetBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 340)
                                $streetBool = true;
                        }

                    }

                    $streetTMP = str_replace(" ", "", $dataField);
                    $streetTMP = str_replace(".", "", $streetTMP);
                    $streetTMP = str_replace(",", "", $streetTMP);
                    $streetUPPER = strtoupper($streetTMP);
                    $fiasIdUPPER = strtoupper($fiasId);


                    if(!$streetBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.",340,'".$dataField."','".$streetUPPER."')";
                        //AddMessage2Log($strSQL, 'SQL STREET');

                    } else
                    {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 340";
                        //AddMessage2Log($strSQL, 'SQL STREET UPDATE');
                    }
                    $DB->Query($strSQL);



                }

                if($dataField !== "" && $nameField == "BUILDING")
                {
                    $houseBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 400)
                                $houseBool = true;
                        }

                    }
                    //$house = $dataField.' д.';
                    $houseTMP = str_replace(" ", "", $dataField);
                    $houseTMP = str_replace(".", "", $houseTMP);
                    $houseTMP = str_replace(",", "", $houseTMP);
                    $houseUPPER = strtoupper($houseTMP);

                    if(!$houseBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (" . $LOC_ADDR_ID . ",400,'" . $dataField . "','" . $houseUPPER . "')";
                        //AddMessage2Log($strSQL, 'SQL BUILDING');
                    } else {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 400";
                        //AddMessage2Log($strSQL, 'SQL BUILDING UPDATE');
                    }

                    $DB->Query($strSQL);
                }

                if($dataField !== "" && $nameField == "FIAS_ID")
                {
                    $fiasIdBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 900)
                                $fiasIdBool = true;
                        }

                    }

                    $fiasIdUPPER = strtoupper($fiasId);
                    if(!$fiasIdBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.", 900, '".$fiasId."', '".$fiasIdUPPER."')";
                        //AddMessage2Log($strSQL, 'SQL STREET');

                    } else
                    {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$fiasId."', VALUE_NORMALIZED = '".$fiasIdUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 900";
                        //AddMessage2Log($strSQL, 'SQL STREET UPDATE');
                    }
                    $DB->Query($strSQL);
                }


                //AddMessage2Log($strSQL, 'SQLALL');
            }

            //$DB->Query("INSERT INTO b_location_addr_fld (ADDRESS_ID, TYPE, VALUE, VALUE_NORMALIZED) VALUES ({$LOC_ADDR_ID},400,'{$house}','{$houseUPPER}')");

            $addrId = $Address->findById($addrItem['LOC_ADDR_ID']);
            $resAddress[$i] = $addrId['fieldCollection'];

            if(!empty($resAddress[$i][340]) && !empty($resAddress[$i][400])) {
                \CRest::call('crm.address.update',	array(
                    'fields' => array(
                        'TYPE_ID' => $addrItem['TYPE_ID'],
                        'ENTITY_TYPE_ID' => $addrItem['ENTITY_TYPE_ID'],
                        'ENTITY_ID' => $addrItem['ENTITY_ID'],
                        'LOC_ADDR_ID' => $addrItem['LOC_ADDR_ID'],
                        'POSTAL_CODE' => $resAddress[$i][50],//Почтовый индекс
                        'COUNTRY' => $resAddress[$i][100],//Страна
                        'PROVINCE' => $resAddress[$i][200],//Регион
                        'REGION' => $resAddress[$i][210],//Район
                        'CITY' => $resAddress[$i][300],//Город+Населенный пункт
                        'STREET' => $resAddress[$i][340],//Улица
                        'BUILDING' => $resAddress[$i][400],//Номер дома
                        'ADDRESS_1' => $resAddress[$i][340].', '.$resAddress[$i][400],
                        'ADDRESS_2' => $resAddress[$i][600]
                    )
                ));
            }
        }

    }

    /**
     * Сохранение данных (внутр.)
     * @param $factory
     * @param $item
     * @param $services
     * @return array
     */
    public function saveAllData($factory, $item, $services): array
    {
        $crmUpdateResult = $this->crmUpdate($factory, $item, $services);

        Logs\File ::AddMessage($crmUpdateResult, "crmUpdateResult", LOG_API_SYNC_SELLER_CONTROLLER);
        if ($crmUpdateResult !== true) {  // Если вернулся массив ошибок
            return [
                'status' => 'error',
                'messages' => $crmUpdateResult,
            ];
        }
        return [
            'status' => 'success',
            'messages' => ['Все данные успешно сохранены.'],
        ];
    }

    /**
     * Обновление CRM данными от служб СМЭВ (внутр.)
     * @param $factory
     * @param $item
     * @param $services
     * @return true|array
     */
    private function crmUpdate($factory, $item, $services): true|array
    {
        $errors = [];

        // Проверяем и устанавливаем нужные поля на основании данных из $requestArray['response']['services']
        if (isset($services) && is_array($services)) {
            foreach ($services as $serviceData) {
                $serviceName = $serviceData['service'];
                $result = (isset($serviceData['result']['valid']) && $serviceData['result']['valid'] === true) ? '1' : '0';
                $description = $serviceData['result']['description'] ?? null;

                Logs\File ::AddMessage($result, "result", LOG_API_SYNC_SELLER_CONTROLLER);

                // Устанавливаем поля для компании или контакта в зависимости от `service`
                if ($serviceName === "fns") {
                    $item->set('UF_CRM_PFR_VALIDITY_OF_PASSPORT', (string) $result);
                    $item->set('UF_CRM_PFR_DECODING_PASSPORT_CHECK', $description);
                } elseif ($serviceName === "mvd") {
                    $item->set('UF_CRM_MVD_VALIDITY_OF_PASSPORT', (string) $result);
                    $item->set('UF_CRM_MVD_DECODING_PASSPORT_CHECK', $description);
                }
            }
        } else {
            $errors[] = "Отсутствует корректный массив 'services' в запросе.";
        }

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();

        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $errors = array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Функция для генерации GUID (внутр.)
     * @return string
     */
    private function generateGUID(): string
    {
        if (function_exists('com_create_guid')) {
            return strtolower(trim(com_create_guid(), '{}'));
        } else {
            return strtolower(sprintf(
                '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(16384, 20479), // 4XXX
                mt_rand(32768, 49151), // 8XXX
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535)
            ));
        }
    }

    /**
     * ? Генерация ссылки для анонимной формы DEV (внтур.)
     * @param $dealId
     * @return string|void|null
     * @throws ArgumentException
     */
    public function generateDevLinkForAnonimForm($dealId)
    {
        // Получаем фабрику для сделок через контейнер
        $entityTypeId = \CCrmOwnerType::Deal;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            die("Не удалось получить фабрику для сделок.");
        }

        // Получаем объект сделки по ID
        $item = $factory->getItem($dealId);
        if (!$item) {
            die("Сделка с ID $dealId не найдена.");
        }

        // Генерируем GUID и формируем ссылку
        $guid = $this->generateGUID();

        // Формируем ссылку с параметром GUID
        $testLink = "https://stage-umber.vercel.app/doc-loader?id=" . urlencode($guid);

        // Сохраняем GUID в пользовательское поле сделки
        $item->set('UF_CRM_GUID', $guid);

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();
        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $saveResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $dealId,
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b] {$message} [/b]"
                ]
            ]);
            return null;
        }
        $message = "Ссылка (тест) для анонимной формы: " . $testLink;
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                "ENTITY_ID" => $dealId,
                "ENTITY_TYPE" => "DEAL",
                "COMMENT" => "[b] {$message} [/b]"
            ]
        ]);
        return $testLink;
    }

    /**
     * ? Генерация ссылки для анонимной формы PROD (внтур.)
     * @param $dealId
     * @return string|void|null
     * @throws ArgumentException
     */
    public function generateLinkForAnonimForm($dealId)
    {
        // Получаем фабрику для сделок через контейнер
        $entityTypeId = \CCrmOwnerType::Deal;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            die("Не удалось получить фабрику для сделок.");
        }

        // Получаем объект сделки по ID
        $item = $factory->getItem($dealId);
        if (!$item) {
            die("Сделка с ID $dealId не найдена.");
        }

        // Генерируем GUID и формируем ссылку
        $guid = $this->generateGUID();

        // Формируем ссылку с параметром GUID
        $link = "https://seller-capital.ru/doc-loader?id=" . urlencode($guid);

        // Сохраняем GUID в пользовательское поле сделки
        $item->set('UF_CRM_GUID', $guid);

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();
        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $saveResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $dealId,
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b] {$message} [/b]"
                ]
            ]);
            return null;
        }
        $message = "Ссылка для анонимной формы: " . $link;
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                "ENTITY_ID" => $dealId,
                "ENTITY_TYPE" => "DEAL",
                "COMMENT" => "[b] {$message} [/b]"
            ]
        ]);
        return $link;
    }
}