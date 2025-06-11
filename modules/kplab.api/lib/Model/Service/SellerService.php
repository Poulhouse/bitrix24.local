<?php namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\API\V2\Interfaces\AbstractSeller;
use KPLab\API\V2\Model\DTO\Sellers\SellerLegalEntityDTO;
use KPLab\API\V2\Model\DTO\Sellers\SellerPersonDTO;
use KPLab\API\V2\Model\DTO\Common\BankAccountDTO;
use KPLab\API\V2\Model\Service\Sellers\SellerBeneficiarOwnersService;
use KPLab\API\V2\Model\Service\Sellers\SellerDirectorService;
use KPLab\Helpers\Address;
use KPLab\Logs;

use KPLab\API\V2\Model\DTO\SellersRequisite\SellerLegalRequisiteData;
use KPLab\API\V2\Model\DTO\SellersRequisite\SellerPersonRequisiteData;
use KPLab\API\V2\Model\Service\Sellers\SellerRequisiteService;

use Bitrix\Crm\Service\Factory;
use Bitrix\Crm\Item;

class SellerService
{
    public string $pathObjectUrl;
    private array $objectData = [];
    public SellerBeneficiarOwnersService $sellerBeneficiarOwnersService;
    public SellerRequisiteService $sellerRequisiteService;
    public SellerDirectorService $sellerDirectorService;

    public function __construct()
    {
        $this->sellerDirectorService = new SellerDirectorService();
        $this->sellerBeneficiarOwnersService = new SellerBeneficiarOwnersService();
        $this->sellerRequisiteService = new SellerRequisiteService();
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function sync(AbstractSeller $dto, ?int $crmId = null): int
    {
        return match (true) {
            $dto instanceof SellerLegalEntityDTO => $this->syncUL($dto, $crmId),
            $dto instanceof SellerPersonDTO      => $this->syncPerson($dto, 'seller', null, $crmId),
            default                              => throw new \InvalidArgumentException('Unsupported DTO type'),
        };
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function syncUL(SellerLegalEntityDTO $dto, ?int $crmId = null): int
    {
        $dataArray = $dto->toArray();
        // получаем ID карточки компании (если ещё не создана — создаём)
        $sellerCardId = $this->findCard($dto->inn);
        if (!$sellerCardId) {
            $sellerCardId = $this->createCard(null, $dataArray, 'seller', $crmId);
        }
        // теперь передаём верные параметры
        $this->updateCard($sellerCardId, $dataArray, $sellerCardId, 'seller', $crmId);

        $requisiteDto = new SellerLegalRequisiteData($sellerCardId, $dto);
        $this->sellerRequisiteService->save($requisiteDto);

        $this->sellerDirectorService->handler($dto->directorData, $sellerCardId);
        $this->sellerBeneficiarOwnersService->handler($dto->beneficiars, $sellerCardId);

        return $sellerCardId;

        /*$rqId = $this->findCompanyRQ($sellerCardId, $dto->inn)
            ?? $this->createRequisite($sellerCardId, (array)$dto);
        $this->updateRequisite($rqId, (array)$dto, $sellerCardId);

        Address::processAddressRequisites($rqId, \CCrmOwnerType::Company, $sellerCardId, (array)$dto);*/
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function syncPerson(SellerPersonDTO $dto, string $type, ?int $parentId = null, ?int $crmId = null): int
    {
        $dataArray = $dto->toArray();
        $cardId = $this->findCard($dto->inn);

        if (!$cardId) {
            $cardId = $this->createCard($parentId, $dataArray, $type, $crmId);
        }
        $this->updateCard($parentId, $dataArray, $cardId, $type, $crmId);

        $requisiteDto = new SellerPersonRequisiteData($cardId, $dto);
        $this->sellerRequisiteService->save($requisiteDto);

        return $cardId;

        /*$rqId = $this->findCompanyRQ($cardId, $dto->inn)
            ?? $this->createRequisite($cardId, (array)$dto);
        $this->updateRequisite($rqId, (array)$dto, $cardId);

        Address::processAddressRequisites($rqId, \CCrmOwnerType::Company, $cardId, (array)$dto);*/
    }

    // ================= Вынесенные методы =================

    /**
     * @throws ArgumentException
     */
    public function createCard(?int $sellerCardId, array $dataArray, string $type, ?int $crmId): int
    {
        $factoryCompany = $this->getCompanyFactory();
        $factoryLK =  $this->getLKFactory();

        $newItem = $factoryCompany->createItem();

        $this->setTypeId($newItem, $dataArray['type']);
        $this->setOrganizationFilial($newItem);
        $this->setFilial($newItem);
        $this->setInn($newItem, $dataArray['inn']);
        $this->setServiceEDO($newItem, $dataArray['serviceEDO']);

        // Для типов director или beneficiars можно задать заголовок карточки
        if ($type == "director" || $type == "beneficiar") {
            $fullName = $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'];
            $newItem->setTitle($fullName);
        }

        // Сохранение новой карточки
        $operation = $factoryCompany->getAddOperation($newItem);
        $operation->disableAllChecks();
        $operation->launch();

        return $newItem->getId();
    }

    /**
     * @throws ObjectPropertyException
     * @throws ArgumentException
     * @throws SystemException
     */
    public function updateCard(?int $sellerCardId, array $dataArray, int $currentCardId, string $type, ?int $crmId): void
    {
        $entityTypeIdLK = 128;
        $factoryCompany = $this->getCompanyFactory();
        $factoryLK =  $this->getLKFactory();

        $item = $factoryCompany->getItem($currentCardId);
        if (is_null($item)) {
            throw new \RuntimeException("Карточка не найдена по ID {$currentCardId} в " . __FILE__);
        }
        //$itemLK = ($crmId ? $factoryLK->getItem($crmId): null);

        $this->setTypeId($item, $dataArray['type']);
        $this->setOrganizationFilial($item);
        $this->setFilial($item);
        $this->setInn($item, $dataArray['inn']);

        if (!empty($dataArray['phone'])) {
            $arPhone = [
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $currentCardId,
                'TYPE_ID' => 'PHONE',
                'VALUE_TYPE' => 'WORK',
                'VALUE' => $dataArray['phone']
            ];
            $multi = new \CCrmFieldMulti();
            $multi->Add($arPhone);
        }
        if (!empty($dataArray['email'])) {
            $arEmail = [
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $currentCardId,
                'TYPE_ID' => 'EMAIL',
                'VALUE_TYPE' => 'WORK',
                'VALUE' => $dataArray['email']
            ];
            $multi = new \CCrmFieldMulti();
            $multi->Add($arEmail);
        }

        $this->setServiceEDO($item, $dataArray['serviceEDO']);
        $this->setMarketplaceLinks($item, $dataArray['marketplaceLinks']);
        $this->setSyncId($item, $dataArray['synchId']);
        $this->setUpdateLK($item);
        $this->setPassportIsManual($item, $dataArray['isManual']);

        if(!empty($dataArray['charterFile'])) {
            $this->setCharterFile($item, $dataArray['charterFile']);
        }
        if(!empty($dataArray['orderDirector'])) {
            $this->setOrderDirector($item, $dataArray['orderDirector']);
        }
        if(!empty($dataArray['passport']['files'])) {
            $this->setPassportFiles($item, $dataArray['passport']['files']);
        }

        // Запуск операции обновления
        $operation = $factoryCompany->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operation->launch();
    }

    public function findCard(string $inn, int|false|null $crmId = false): ?int
    {
        $cardId = false;
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);


        if(!$crmId) {
            $params = [
                'filter' => [
                    'UF_CRM_6433D7C925893' => $inn,
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
            $entityTypeId = 128;
            $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
            $itemLK = $factory -> getItem($crmId);
            $itemLKData = $factory -> getItem($crmId)->getData();
            $cardId = $itemLKData['COMPANY_ID'];

            return $cardId;
        }
    }

    public function findCompanyRQ(int $cardId, ?string $inn = null): ?int
    {
        if(!is_null($inn)) {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            \KPLab\Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            \KPLab\Logs\File ::AddMessage($requisite, "First requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        if(isset($requisite)) {
            return $requisite[0]['ID'];
        } else {
            return null;
        }
    }

    public function createRequisite(int $cardId, array $dataArray): int
    {
        // Определяем PRESET_ID в зависимости от типа
        if ($dataArray['type'] === "IP" || $dataArray['type'] === "FL") {
            $PRESET_ID = 2;
        }
        if ($dataArray['type'] === "UL") {
            $PRESET_ID = 1;
        }

        // Формируем параметры для создания в зависимости от типа
        if ($dataArray['type'] === "IP" || $dataArray['type'] === "FL") {
            $params = [
                "fields" => [
                    "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
                    "ENTITY_ID" => $cardId,
                    "PRESET_ID" => $PRESET_ID,
                    'TITLE' => $dataArray['type'] . " " . $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'NAME' => $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'RQ_NAME' => $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'RQ_FIRST_NAME' => $dataArray['firstName'],
                    'RQ_LAST_NAME' => $dataArray['lastName'],
                    'RQ_SECOND_NAME' => $dataArray['secondName'],
                    'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                    'RQ_IDENT_DOC_SER' => $dataArray['passport']['series'],
                    'RQ_IDENT_DOC_NUM' => $dataArray['passport']['number'],
                    'RQ_IDENT_DOC_DATE' => date('d.m.Y', strtotime($dataArray['passport']['issuedAt'])),
                    'RQ_IDENT_DOC_ISSUED_BY' => $dataArray['passport']['issuer'],
                    'RQ_IDENT_DOC_DEP_CODE' => $dataArray['passport']['issuerCode'],
                    'UF_CRM_1647929611' => $dataArray['birthPlace'],
                    'UF_CRM_1684493639' => $dataArray['birthday'],
                    'RQ_INN' => $dataArray['inn'],
                    'RQ_OGRNIP' => $dataArray['ogrnip'],
                    'RQ_OKPO' => $dataArray['okpo'],
                    'RQ_OKVED' => $dataArray['okved'],
                    'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($dataArray['companyRegDate'])),
                    'UF_CRM_1688964741' => $dataArray['fnsDepartment'],
                ]
            ];
        }
        if ($dataArray['type'] === "UL") {
            $params = [
                "fields" => [
                    "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
                    "ENTITY_ID" => $cardId,
                    "PRESET_ID" => $PRESET_ID,
                    'TITLE' => $dataArray['companyName'],
                    'NAME' => $dataArray['companyName'],
                    'RQ_INN' => $dataArray['inn'],
                    'RQ_KPP' => $dataArray['kpp'],
                    'RQ_OGRN' => $dataArray['ogrn'],
                    'RQ_OKPO' => $dataArray['okpo'],
                    'RQ_OKVED' => $dataArray['okved'],
                    'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($dataArray['companyRegDate'])),
                    'UF_CRM_1688964741' => $dataArray['fnsDepartment'],
                    'RQ_COMPANY_NAME' => $dataArray['companyName'],
                    'RQ_COMPANY_FULL_NAME' => $dataArray['companyFullName'],
                ]
            ];
        }

        // Создаём реквизит через REST API и получаем его идентификатор
        $rqResponse = \CRest::call('crm.requisite.add', $params);
        return $rqResponse['data'];
    }

    public function updateRequisite(int $rqId, array $dataArray, int $cardId): void
    {
        // Формируем параметры для обновления в зависимости от типа (IP, FL или UL)
        if ($dataArray['type'] === "IP" || $dataArray['type'] === "FL") {
            $params = [
                "id" => $rqId,
                "fields" => [
                    'TITLE' => $dataArray['type'] . " " . $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'NAME' => $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'RQ_NAME' => $dataArray['lastName'] . " " . $dataArray['firstName'] . " " . $dataArray['secondName'],
                    'RQ_FIRST_NAME' => $dataArray['firstName'],
                    'RQ_LAST_NAME' => $dataArray['lastName'],
                    'RQ_SECOND_NAME' => $dataArray['secondName'],
                    'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                    'RQ_IDENT_DOC_SER' => $dataArray['passport']['series'],
                    'RQ_IDENT_DOC_NUM' => $dataArray['passport']['number'],
                    'RQ_IDENT_DOC_DATE' => date('d.m.Y', strtotime($dataArray['passport']['issuedAt'])),
                    'RQ_IDENT_DOC_ISSUED_BY' => $dataArray['passport']['issuer'],
                    'RQ_IDENT_DOC_DEP_CODE' => $dataArray['passport']['issuerCode'],
                    'UF_CRM_1647929611' => $dataArray['birthPlace'],
                    'UF_CRM_1684493639' => $dataArray['birthday'],
                    'RQ_INN' => $dataArray['inn'],
                    'RQ_OGRNIP' => $dataArray['ogrnip'],
                    'RQ_OKPO' => $dataArray['okpo'],
                    'RQ_OKVED' => $dataArray['okved'],
                    'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($dataArray['companyRegDate'])),
                    'UF_CRM_1688964741' => $dataArray['fnsDepartment'],
                ]
            ];
        }
        if ($dataArray['type'] === "UL") {
            $params = [
                "id" => $rqId,
                "fields" => [
                    'TITLE' => $dataArray['companyName'],
                    'NAME' => $dataArray['companyName'],
                    'RQ_INN' => $dataArray['inn'],
                    'RQ_KPP' => $dataArray['kpp'],
                    'RQ_OGRN' => $dataArray['ogrn'],
                    'RQ_OKPO' => $dataArray['okpo'],
                    'RQ_OKVED' => $dataArray['okved'],
                    'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($dataArray['companyRegDate'])),
                    'UF_CRM_1688964741' => $dataArray['fnsDepartment'],
                    'RQ_COMPANY_NAME' => $dataArray['companyName'],
                    'RQ_COMPANY_FULL_NAME' => $dataArray['companyFullName'],
                ]
            ];
        }

        // Выполняем обновление через REST API
        \CRest::call('crm.requisite.update', $params);
    }

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

                return ['ID' => $dealId, 'COMPANY_ID' => $companyId];
            }
        }
    }

    private function filesFromArray($dataFiles, $base64, $anyItems): array
    {
        $arFiles = [];
        if($anyItems) {
            foreach ($dataFiles as $k => $file) {
                //Logs\File::AddMessage($file, 'file_'.$k, LOG_API_SYNC_SELLER_CONTROLLER);
                $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/tmp/".$fileName;

                if($base64) file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                if(!$base64) {
                    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
                    // Инициализация cURL-сессии
                    $ch = curl_init($file["file"]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    // Добавляем заголовок с Bearer Token
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $token"
                    ]);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_ENCODING, "");
                    curl_setopt($ch, CURLOPT_HEADER, false);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

                    curl_close($ch);

                    // Если произошла ошибка, выводим ответ для диагностики
                    if ($httpCode !== 200) {
                        \KPLab\Logs\File::AddMessage($response, 'Ошибка при скачивании файла:', LOG_API_SYNC_SELLER_CONTROLLER);
                    } else {
                        file_put_contents($filePathName, $response);
                        echo "Файл успешно скачан";
                    }

                    curl_close($ch);
                }

                $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                $fileId = \CFile::SaveFile($file,'docs');//Запись диск Битрикс

                if ($fileId) {
                    $arFiles[] = \CFile::MakeFileArray($fileId);
                    unlink($filePathName);
                } else {
                    \KPLab\Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
            }
        }
        else {
            $file = $dataFiles;
            $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
            $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;

            if($base64) file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
            if(!$base64) {
                $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
                // Инициализация cURL-сессии
                $ch = curl_init($file["file"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // Добавляем заголовок с Bearer Token
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $token"
                ]);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_ENCODING, "");
                curl_setopt($ch, CURLOPT_HEADER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

                curl_close($ch);

                // Если произошла ошибка, выводим ответ для диагностики
                if ($httpCode !== 200) {
                    \KPLab\Logs\File::AddMessage($response, 'Ошибка при скачивании файла:', LOG_API_SYNC_SELLER_CONTROLLER);
                } else {
                    file_put_contents($filePathName, $response);
                    echo "Файл успешно скачан";
                }

                curl_close($ch);
            }

            $file = \CFile::MakeFileArray($filePathName);//сформировали массив
            $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс

            if ($fileId) {
                $arFiles[] = \CFile::MakeFileArray($fileId);
                unlink($filePathName);
            } else {
                \KPLab\Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
        }

        return $arFiles;
    }

    public function setObjectData(array $data): void
    {
        $this->objectData = $data;
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

    public function getFirstRequisiteId(int $companyId): ?int
    {
        $result = \CRest::call('crm.requisite.list', [
            'filter' => [
                'ENTITY_ID' => $companyId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Company
            ],
            'select' => ['ID']
        ]);

        return $result['result'][0]['ID'] ?? null;
    }

    public function addBankAccount(int $rqId, BankAccountDTO $dto): void
    {
        \CRest::call('crm.requisite.bankdetail.add', [
            'fields' => [
                'ENTITY_TYPE_ID'    => \CCrmOwnerType::Requisite,
                'ENTITY_ID'         => $rqId,
                'NAME'              => $dto->title,
                'RQ_BANK_NAME'      => $dto->nameBank,
                'RQ_BIK'            => $dto->bankIdCode,
                'RQ_BIC'            => $dto->bankIdCode,
                'RQ_ACC_NUM'        => $dto->checkAccount,
                'RQ_COR_ACC_NUM'    => $dto->adjAccount,
                'RQ_ACC_CURRENCY'   => 'RUB',
                'COMMENTS'          => 'МКК'
            ]
        ]);
    }

    public function processLoan(string $sellerInn, int $crmId, array $loanData): array
    {
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(128);
        $sellerCardId  = $this->findCard($sellerInn, $crmId);

        $amount       = floatval($loanData['amount'] ?? 0);
        $term         = intval($loanData['term'] ?? 0);
        $purpose      = $loanData['purposeLoan'] ?? '';
        $isFirst      = (bool)($loanData['isFirstTranche'] ?? false);
        $withDelay    = (bool)($loanData['typeContract'] ?? false);

        // Получаем ENUM ID для срока займа
        $termEnum = \CUserFieldEnum::GetList([], ['XML_ID' => "{$term}_MONTHS"]);
        $loanTermId = $termEnum && ($row = $termEnum->Fetch()) ? (int)$row['ID'] : null;

        if ($isFirst) {
            // 🔁 Поиск существующего элемента (Ожидание решения клиента)
            $items = $factory->getItems([
                'filter' => [
                    '=COMPANY_ID' => $sellerCardId,
                    'STAGE_ID' => 'DT128_226:UC_6GB0Q7',
                    'CATEGORY_ID' => 226
                ],
                'select' => ['ID']
            ]);

            if (!$items) {
                return ['status' => 'fail', 'message' => 'Создание первого транша невозможно, он уже существует'];
            }

            foreach ($items as $item) {
                $item->set('UF_CRM_CRMID', $crmId);
                $item->set('UF_CRM_INN', $sellerInn);
                $item->set('UF_CRM_LOAN_AMOUNT', $amount);
                $item->set('UF_CRM_LOAN_TERM', $loanTermId);
                $item->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purpose);
                $item->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $this->getDelayEnumId($withDelay));
                $item->save();

                $item->setStageId('DT128_226:CLIENT');
                $item->setCategoryId(226);

                $operation = $factory->getUpdateOperation($item);
                $operation->disableAllChecks();

                return $operation->launch()->isSuccess()
                    ? ['status' => 'first_created']
                    : ['status' => 'fail', 'message' => 'Создание транша не удалось'];
            }
        }

        // 🔁 Повторный транш — создаём новый элемент на основе предыдущего
        $prevItem = $factory->getItem($crmId);
        $title = $prevItem->get('TITLE');
        $parentId = $prevItem->get('PARENT_ID_134');
        $createdBy = $prevItem->get('CREATED_BY');

        $item = $factory->createItem();
        $item->set('TITLE', "Повторный транш {$title}");
        $item->set('COMPANY_ID', $sellerCardId);
        $item->set('UF_CRM_CRMID', $crmId);
        $item->set('PARENT_ID_134', $parentId);
        $item->set('UF_CRM_INN', $sellerInn);
        $item->set('UF_CRM_LOAN_AMOUNT', $amount);
        $item->set('UF_CRM_LOAN_TERM', $loanTermId);
        $item->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purpose);
        $item->set('UF_CRM_REPEAT_ZAYAVKA', 1);
        $item->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $this->getDelayEnumId($withDelay));

        $context = (new \Bitrix\Crm\Service\Context())->setUserId($createdBy);

        $item->save();
        $item->setStageId('DT128_226:NEW');
        $item->setCategoryId(226);

        $operation = $factory->getAddOperation($item, $context);
        $operation->disableAllChecks();

        return $operation->launch()->isSuccess()
            ? ['status' => 'repeat_created']
            : ['status' => 'fail', 'message' => 'Создание повторного транша не удалось'];
    }

    private function getDelayEnumId(bool $withDelay): ?int
    {
        $xmlId = $withDelay ? 'WITH_DELAY' : 'NO_DELAY';
        $enum = \CUserFieldEnum::GetList([], ['XML_ID' => $xmlId]);
        if ($row = $enum->Fetch()) {
            return (int)$row['ID'];
        }
        return null;
    }

    /**
     * Сохраняет данные от СМЭВ в карточку сущности (Company/Contact).
     *
     * @param Factory $factory
     * @param Item $item
     * @param array $services
     * @return array ['status' => 'success'|'error', 'messages' => string[]]
     * @throws ArgumentException
     */
    public function saveAllData(Factory $factory, Item $item, array $services): array
    {
        $messages = [];
        $success = true;

        foreach ($services as $service) {
            $serviceName = $service['service'] ?? 'unknown';
            $result = $service['result']['description'] ?? 'Нет описания';
            $valid = $service['result']['valid'] ?? null;

            $messages[] = "[{$serviceName}] {$result}";

            // Пример: сохраняем результат проверки как комментарий
            // Можно также сохранять в кастомные поля карточки
            Logs\File::AddMessage($service, "Сервис {$serviceName}", LOG_API_SYNC_SELLER_CONTROLLER);

            // Пример логики: если в карточке есть поле UF_CRM_SMEV_<SERVICE>, то сохраняем туда результат
            $ufField = 'UF_CRM_SMEV_' . strtoupper($serviceName);
            if ($item->hasField($ufField)) {
                $item->set($ufField, $valid === true ? 'Y' : 'N');
            }
        }

        try {
            $operation = $factory->getUpdateOperation($item);
            $operation->disableAllChecks(); // При необходимости
            $result = $operation->launch();

            if (!$result->isSuccess()) {
                $success = false;
                $messages = array_merge($messages, $result->getErrorMessages());
            }
        }
        catch (\Throwable $e) {
            $success = false;
            $messages[] = $e->getMessage();
        }

        return [
            'status' => $success ? 'success' : 'error',
            'messages' => $messages,
        ];
    }

    /**
     * @throws ArgumentException
     */
    public function getCloseDateConsent(string $sellerInn, int $crmId = 0): array
    {
        $sellerCardId = $this->findCard($sellerInn, $crmId);
        if (!is_int($sellerCardId)) {
            throw new \RuntimeException("Не существует Селлера с таким ИНН или CRMID");
        }
        $entityTypeIdOSK = 134;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdOSK);
        $items = $factory->getItems(['filter' => ['=COMPANY_ID' => $sellerCardId]]);

        foreach ($items as $item) {
            $date = $item->get('UF_CRM_END_DATE_OF_CONSENT');
            if ($date) {
                return ['closeDateConsent' => date('Y-m-d\TH:i:s.msp', strtotime($date))];
            }
        }

        return ['closeDateConsent' => null];
    }

    /**
     * @throws ArgumentException
     */
    private function setServiceEDO(Item $item, mixed $serviceEDO): void
    {
        $rsEnumEDO = \CUserFieldEnum::GetList([], ["XML_ID" => "Edo_" . $serviceEDO]);
        if ($arEnumEDO = $rsEnumEDO->Fetch()) {
            $item->set("UF_CRM_COMPANY_SERVICE_EDO", $arEnumEDO['ID']);
        }
    }

    /**
     * @throws ArgumentException
     */
    private function setMarketplaceLinks(Item $item, mixed $marketplaceLinks): void
    {
        $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks);
    }

    /**
     * @throws ArgumentException
     */
    private function setSyncId(Item $item, mixed $syncId): void
    {
        $item -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId);
    }

    /**
     * @throws ArgumentException
     */
    private function setUpdateLK(Item $item): void
    {
        $item->set("UF_CRM_UPDATE_INFO_LK", true);
    }

    /**
     * @throws ArgumentException
     */
    private function setPassportIsManual(Item $item, mixed $isManual): void
    {
        $item->set("UF_CRM_PASSPORT_IS_MANUAL", $isManual);
    }
    private function setCharterFile(Item $item, mixed $charterFile): void
    {
        try {
            $arFile = $this->filesFromArray($charterFile, false, false);
            $fields = [
                'UF_CRM_COMPANY_CHARTER' => $arFile,
            ];
            $item->setFromCompatibleData($fields);
        } catch( \Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"ERROR", LOG_API_SYNC_SELLER_CONTROLLER);
        }
    }
    private function setOrderDirector(Item $item, mixed $orderDirector): void
    {
        try {
            $arFile = $this->filesFromArray($orderDirector, false, false);
            $fields = [
                'UF_CRM_ORDER_FOR_DIRECTOR' => $arFile,
            ];
            $item->setFromCompatibleData($fields);
        } catch( \Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"ERROR", LOG_API_SYNC_SELLER_CONTROLLER);
        }
    }
    private function setPassportFiles(Item $item, mixed $files): void
    {
        try {
            $arFile = $this->filesFromArray($files, false, true);
            $fields = [
                'UF_CRM_6433D94467769' => $arFile,
            ];
            $item->setFromCompatibleData($fields);
        } catch( \Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"ERROR", LOG_API_SYNC_SELLER_CONTROLLER);
        }
    }

    /**
     * @throws ArgumentException
     */
    private function setOrganizationFilial(Item $item): void
    {
        $item->set("UF_CRM_COMPANY_SS_ORG", [5]);
    }

    /**
     * @throws ArgumentException
     */
    private function setFilial(Item $item): void
    {
        $item->set("UF_CRM_6433DBB98DD53", 17611);
    }

    /**
     * @throws ArgumentException
     */
    private function setInn(Item $item, mixed $inn): void
    {
        $item->set("UF_CRM_6433D7C925893", $inn);
    }

    /**
     * @throws ArgumentException
     */
    private function setTypeId(Item $item, mixed $type): void
    {

        switch ($type) {
            case 'IP':
                $typeId = 11075;
                break;
            case 'FL':
                $typeId = 11077;
                break;
            case 'UL':
                $typeId = 11076;
                break;
            default:
                return;
        }
        $item->set("UF_CRM_1684145100226", $typeId);
    }

    private function getCompanyFactory(): \Bitrix\Crm\Service\Factory
    {
        return \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
    }
    private function getLKFactory(): \Bitrix\Crm\Service\Factory
    {
        return \Bitrix\Crm\Service\Container::getInstance()->getFactory(128);
    }

}