<?php namespace KPLab\API\V2\Controller;

use Bitrix\Crm\EntityRequisite;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use KPLab\Logs;

define("LOG_BP", $_SERVER['DOCUMENT_ROOT']."/local/classes/balanceplatform/balanceplatform_.log");
define("TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");
define('API_KEY','4d0e4072-889b-42cd-950c-af8d58221114');

\Bitrix\Main\Loader::includeModule('kplab.api.v2');
\Bitrix\Main\Loader::includeModule('rest');
\Bitrix\Main\Loader ::IncludeModule('crm');

class BalancePlatform {
    private static function getCompanyData($companyId): ?array {

        //$result = self::getItemData(\CCrmOwnerType::Company, $companyId);

        $result = \CRest::call('crm.company.get', ['id' => $companyId])['result'];


        //Logs\File::AddMessage($result, "crm.company.get result", LOG_BP);
        /*$result = \Bitrix\Crm\CompanyTable::getList([
            'filter' => ['ID' => $companyId],
            'select' => ['*', 'UF_*'] // добавьте нужные поля
        ])->fetch();*/

        return $result ?: null; // Возвращаем данные или null, если компания не найдена
    }
    private static function getMarketplaceNominalAccountChecks($companyData) {
        $marketplaceNominalAccountChecks = [];
        if($companyData['UF_CRM_MARKETPLACE_AT_ACCOUNT']) {
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_MARKETPLACE_AT_ACCOUNT'
                ]
            ]);
            while ($arUserField = $userFields->fetch()){
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID']]);
                while ($arUserFieldData = $res->fetch()) {
                    foreach ($companyData['UF_CRM_MARKETPLACE_AT_ACCOUNT'] as $marketplaceNA) {
                        if($arUserFieldData['ID'] == $marketplaceNA) {
                            $marketplaceNominalAccountChecks[] = $arUserFieldData['XML_ID'];
                        }
                    }
                }
            }
        }
        return $marketplaceNominalAccountChecks;
    }
    private static function getClientGroupIndicator($companyData)
    {
        return $companyData['UF_CRM_1702588306']
            ? self::clientGroupValue($companyData['UF_CRM_1702588306'])
            : '';
    }
    private static function getItemData(int $entityTypeId, int $elementID) {

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $item = $factory->getItem($elementID);


        if (!isset($item)) {
            Logs\File::AddMessage("Item not found for ID: $elementID", "getItemData", LOG_BP);
            return null;
        }

        return $item->getData();
    }
    private static function checkForErrors(array $data): ?array
    {
        /*
         * Проверяет наличие ошибок в данных.
         * Возвращает массив ошибок, если они присутствуют, иначе null.
         */

        // Проверка на наличие ключа ошибки и валидность его значения
        if (!empty($data['error'])) {
            Logs\File::AddMessage($data['error'], "Проверка на наличие ключа ошибки", LOG_BP);
            return $data;
        }

        // Если данных об ошибках нет, возвращаем null
        return null;
    }
    private static function mergeData($passportInfo, $regData): array
    {
        /*
         * Централизованное объединение данных, что улучшает читаемость и упрощает управление результатами.
         */
        $mergedData = [];
        foreach ($passportInfo as $i => $p) {
            $mergedData[] = array_merge($p, $regData[$i]);
        }
        return $mergedData;
    }
    private static function initializeResult($companyId, $contactsIdList): array
    {
        $isBitrixTest = false;

        $host = $_SERVER['HTTP_HOST'];
        $url = str_contains($host, 'test')
            ? ($isBitrixTest = true)
            : ($isBitrixTest = false);

        // Загружаем данные компании
        $companyData = self::getCompanyData($companyId);

        if (!$companyData) {
            Logs\File::AddMessage("Company data not found for ID: $companyId", "Error", LOG_BP);
            return [];
        }

        // Логируем идентификаторы контактов
        Logs\File::AddMessage($contactsIdList, "Contacts ID List", LOG_BP);

        // Инициализация результирующего массива
        $result = [];
        $result['sellerInn'] = $contactsIdList['inn'];
        $result["bkiReportConsentDateLE"] = $companyData['UF_CRM_1700138022'];
        $result["bkiReportDealDateLE"] = $companyData['UF_CRM_1700138022'];
        $result['organization'] = ($contactsIdList['not'] !== "organization");
        $result['isBitrixTest'] = $isBitrixTest;
        $result['BitrixIsReadyForOOO'] = ($contactsIdList['BitrixIsReadyForOOO'] === true);

        Logs\File::AddMessage($result, "Initialized Result", LOG_BP);

        return $result;
    }
    public static function getCompanyIdBySmart($entityTypeId, $elementID) {
        // Определяем типы сущностей, которые поддерживаются для получения companyId
        $supportedEntityTypes = [134, 149];

        // Проверяем, что entityTypeId поддерживается
        if (!in_array($entityTypeId, $supportedEntityTypes, true)) {
            Logs\File::AddMessage("Unsupported entity type ID: $entityTypeId", "Error", LOG_BP);
            return null;
        }

        // Получаем данные элемента
        $itemData = self::getItemData($entityTypeId, $elementID);

        // Проверяем наличие данных и companyId в элементе
        if ($itemData && isset($itemData['COMPANY_ID'])) {
            return (int) $itemData['COMPANY_ID'];
        } else {
            Logs\File::AddMessage("Company ID not found for entityTypeId: $entityTypeId, elementID: $elementID", "Warning", LOG_BP);
            return null;
        }
    }
    public static function getItemForPostById(int $entityTypeId, int $id): ?array {
        $result = [];
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

        if (!$factory) {
            Logs\File::AddMessage(
                ['entityTypeId' => $entityTypeId],
                "Factory not found for provided entityTypeId",
                LOG_BP
            );
            return null;
        }

        $item = $factory->getItem($id);
        if (!$item) {
            Logs\File::AddMessage(
                ['entityTypeId' => $entityTypeId, 'id' => $id],
                "Item not found for provided entityTypeId and id",
                LOG_BP
            );
            return null;
        }

        $data = $item->getData();

        return [
            'ITEM_ID' => $id,
            'ITEM_TYPE_ID' => $entityTypeId,
            'ITEM_TITLE' => $data['TITLE'] ?? 'Title Not Available',
            'METHOD' => 'POST',
            'TASK_ID' => $data['UF_SE_TASK_ID'] ?? null,
            'INIT_OBJECT_URL' => "https://".$_SERVER['HTTP_HOST']."/crm/type/".$entityTypeId."/details/".$id."/",
        ];
    }
    public static function clientGroupValue($ID)
    {
        $IBLOCK_ID = 169;
        $arSelect = Array("*");
        $arFilter = array(
            "IBLOCK_ID" => $IBLOCK_ID,
            "=ID" => $ID
        );
        $res = \CIBlockElement::GetList(Array(), $arFilter, false, Array(), $arSelect);
        while($ob = $res->GetNextElement())
        {
            $fields = $ob->GetFields();
            $properties = $ob->GetProperties();
            $clientGroupIndicator = $fields['NAME'];
        }
        return $clientGroupIndicator;
    }
    public static function getUsernameByID($userId): string
    {
        $rsUser = \CUser::GetByID($userId);
        if ($arUser = $rsUser->Fetch()) {
            return trim(($arUser['LAST_NAME'] ?? '') . ' ' . ($arUser['NAME'] ?? ''));
        } else {
            Logs\File::AddMessage(
                ['userId' => $userId],
                "User not found",
                LOG_BP
            );
            return "Unknown User"; // или вернуть пустую строку ""
        }
    }
    private static function getRequisiteData($entityId, int $entityTypeId): ?array
    {
        try {
            $req = new \Bitrix\Crm\EntityRequisite();
            $result = $req->getList([
                'filter' => ['ENTITY_ID' => $entityId, 'ENTITY_TYPE_ID' => $entityTypeId],
                'select' => ['*', 'UF_*']
            ])->fetch();

            if ($result) {
                return $result;
            } else {
                Logs\File::AddMessage("Реквизиты не найдены для ENTITY_ID {$entityId}", "getRequisiteData warning", LOG_BP);
                return null;
            }
        } catch (\Exception $e) {
            Logs\File::AddMessage($e->getMessage(), "getRequisiteData error", LOG_BP);
            return null;
        }
    }

    //region validateFields
    private static function isEmpty($value): bool
    {
        return empty($value) && $value !== 0; // исключаем 0, так как это допустимое значение
    }
    private static function validatePerson($person): bool
    {
        $requiredFields = [
            'name', 'surname', 'patronymic', 'birthday',
            'birthPlace', 'creditAmount', 'bkiReportConsentDate',
            'bkiReportDealDate', 'passportIssuer', 'passportNumber',
            'passportIssuedAt', 'bkiReportStateCode', 'bkiReportBirthPlaceCode',
            'registrationAddressFias.city', 'registrationAddressFias.house',
            'registrationAddressFias.region', 'registrationAddressFias.street',
            'registrationAddressFias.regionKladrId', 'registrationAddressFias.registrationType'
        ];

        foreach ($requiredFields as $field) {
            if (self::isEmpty(self::getFieldValue($person, $field))) {
                return false;
            }
        }

        return true;
    }
    private static function getFieldValue($array, $fieldPath) {
        $pathParts = explode('.', $fieldPath);
        foreach ($pathParts as $part) {
            if (!isset($array[$part])) {
                return null;
            }
            $array = $array[$part];
        }
        return $array;
    }
    public static function validateFields($data): bool|array
    {
        $data = json_decode($data, true);

        // Если значение `sellerInn` пустое, считаем, что данные невалидны
        if (self::isEmpty($data['sellerInn'])) {
            return ['status' => false, 'fields' => $data];
        }

        // Проверка всех записей в `payload`
        if (isset($data['payload'])) {
            foreach ($data['payload'] as $person) {
                if (!self::validatePerson($person)) {
                    return ['status' => false, 'fields' => $data];
                }
            }
        }

        // Если всё валидно, возвращаем `true`
        return true;
    }
    //endregion validateFields

    //region getContactListByCompanyId
    private static function validateCompanyData(array $companyData): ?array
    {
        try {
            // Получаем ИНН с использованием метода getInn
            $inn = self::getInn($companyData, 4) ?: null;

            // Проверка наличия ИНН
            if (empty($inn)) {
                return ['errorFields' => ["{$companyData['TITLE']}" => "Реквизит ИНН - пустое"]];
            }
        } catch (\Exception $e) {
            // Логируем ошибки базы данных
            Logs\File::AddMessage($e->getMessage(), "validateCompanyData error", LOG_BP);
            return ['errorFields' => ['DB_ERROR' => $e->getMessage()]];
        }

        return null; // Все проверки пройдены, ошибок нет
    }
    private static function isOrganization($companyData): bool
    {
        return isset($companyData['UF_CRM_1684145100226']) &&
            $companyData['UF_CRM_1684145100226'] == 11076;
    }
    private static function getInn(array $companyData, $entityTypeId = 4): ?string
    {
        // Получаем реквизиты компании с помощью метода getRequisiteData
        $requisiteData = self::getRequisiteData((int)$companyData['ID'], $entityTypeId); // 4 - тип сущности для компании

        // Проверяем наличие реквизита RQ_INN и возвращаем его, если он найден
        if ($requisiteData && !empty($requisiteData['RQ_INN'])) {
            return $requisiteData['RQ_INN'];
        }

        // Логируем предупреждение, если реквизит не найден
        Logs\File::AddMessage("Реквизит ИНН не найден для компании с ID {$companyData['ID']}", "getInn warning", LOG_BP);

        return null;
    }
    private static function getOrganizationContactList(array $companyData): array
    {
        $contacts = [];

        // Получаем руководителя
        $director = self::getDirector($companyData['UF_CRM_1615200179'] ?? null);
        if ($director) {
            $contacts['ids'][0] = $director;
        }

        // Получаем список бенефициаров
        $beneficiaries = self::getBeneficiaries($companyData['UF_CRM_1687947495'] ?? []);
        foreach ($beneficiaries as $index => $beneficiary) {
            $contacts['ids'][$index + 1] = $beneficiary;
        }

        // Добавляем общие данные компании
        $contacts['not'] = '';
        $contacts['creditAmount'] = $companyData['UF_CRM_1694331571'];
        $contacts['bkiConsentDate'] = $companyData['UF_CRM_1700138022'];
        $contacts['clientGroupIndicator'] = self::getClientGroupIndicator($companyData);
        $contacts['BitrixIsReadyForOOO'] = true;
        $contacts['inn'] = self::getInn($companyData, 4) ?: null;
        $contacts['fullName'] = $companyData['UF_CRM_1595595411835'];

        return $contacts;
    }
    private static function getDirector(?string $directorId): ?array
    {
        if (!$directorId) {
            return null;
        }

        $cleanId = self::cleanContactId($directorId);
        return $cleanId ? ['id' => $cleanId, 'type' => 'director'] : null;
    }
    private static function getBeneficiaries(array $beneficiaryIds): array
    {
        $beneficiaries = [];
        foreach ($beneficiaryIds as $id) {
            $cleanId = self::cleanContactId($id);
            if ($cleanId) {
                $beneficiaries[] = ['id' => $cleanId, 'type' => 'beneficiary'];
            }
        }
        return $beneficiaries;
    }
    private static function cleanContactId(string $id): ?string
    {
        if (str_contains($id, 'CO_')) {
            return null; // Пропускаем ID, содержащие "CO_"
        } elseif (str_contains($id, 'C_')) {
            return str_replace('C_', '', $id);
        } else {
            return $id;
        }
    }
    private static function getIndividualContactList(array $companyData): array
    {
        return [
            'not' => 'organization',
            'ids' => $companyData['ID'],
            'types' => 'director', // Указываем тип контакта как 'director'
            'creditAmount' => $companyData['UF_CRM_1694331571'] ?? null,
            'bkiConsentDate' => $companyData['UF_CRM_1700138022'] ?? null,
            'clientGroupIndicator' => self::getClientGroupIndicator($companyData),
            'inn' => self::getInn($companyData, 4) ?: null,
            'fullName' => $companyData['UF_CRM_1595595411835'] ?? null
        ];
    }
    public static function getContactListByCompanyId(int $companyId): array
    {
        $companyData = self::getCompanyData($companyId);

        // Проверка наличия ошибок в данных компании
        if (is_null($companyData) ){
            return ['errorFields' => "Данные не обработаны в getContactListByCompanyId"];
        }
        if($errorResponse = self::validateCompanyData($companyData)) {
            return $errorResponse; // Возвращаем сообщение об ошибке, если данные не валидны
        }

        $isOrganization = self::isOrganization($companyData);

        return $isOrganization
            ? self::getOrganizationContactList($companyData)
            : self::getIndividualContactList($companyData);
    }
    //endregion getContactListByCompanyId

    //region getPassportInfo
    private static function getPassportInfo(array $contactsIdList, bool $organization, bool $isFirstRequest = false, $nbkiReportConsentPeriod): array {
        // Получаем паспортные данные
        $passportData = self::getPassportInfoById($contactsIdList, $organization, $isFirstRequest, $nbkiReportConsentPeriod);

        // Обработка ошибок: проверяем, содержит ли результат ключ "errorFields"
        if (isset($passportData['errorFields'])) {
            Logs\File::AddMessage($passportData['errorFields'], "Error in passport data retrieval", LOG_BP);
            return ['error' => "Ошибка при получении паспортных данных: " . json_encode($passportData['errorFields'], JSON_UNESCAPED_UNICODE)];
        }

        // Возвращаем паспортные данные, если всё прошло успешно
        return $passportData;
    }
    public static function getPassportInfoById(array $contactsIdList, bool $organization = false, $isFirstRequest = false, $nbkiReportConsentPeriod): array
    {
        $data = [];

        if(!$organization) {
            $data = self::getIndividualPassportInfo($contactsIdList['ids'], $contactsIdList, $isFirstRequest, false, $contactsIdList['types'], $nbkiReportConsentPeriod);
        } else {
            foreach ($contactsIdList['ids'] as $contactId) {
                $contactData = self::getIndividualPassportInfo($contactId, $contactsIdList, $isFirstRequest, true, $contactId['type'], $nbkiReportConsentPeriod);

                if (isset($contactData["errorFields"])) {
                    return $contactData; // Прерываем выполнение и возвращаем ошибки сразу, если они найдены
                }

                $data[$contactId['id']] = $contactData[$contactId['id']];
            }
        }
        return $data;
    }
    private static function getCompanyRequisiteData($companyId): ?array
    {
        // Получаем реквизиты компании, используя универсальный метод getRequisiteData
        return self::getRequisiteData($companyId, 4); // 4 - тип сущности для компаний
    }
    private static function getContactRequisiteData($contactId): ?array
    {
        // Получаем реквизиты контакта, используя универсальный метод getRequisiteData
        return self::getRequisiteData($contactId, 3); // 3 - тип сущности для контактов
    }
    private static function validateRequisiteData(array $requisite, array $contactData, bool $isFirstRequest, bool $isMultiple): string
    {
        $fieldsToCheck = [
            'RQ_FIRST_NAME' => "Реквизит Имя - пустое",
            'RQ_LAST_NAME' => "Реквизит Фамилия - пустое",
            'UF_CRM_1684493639' => "Дата рождения - пустое",
            'UF_CRM_1647929611' => "Место рождения - пустое",
            'RQ_IDENT_DOC_ISSUED_BY' => "Реквизит Кем выдан документ - пустое",
            'RQ_IDENT_DOC_NUM' => "Реквизит Номер документа - пустое",
            'RQ_IDENT_DOC_DATE' => "Реквизит Дата/Время выдачи документа - пустое",
        ];

        // Если не первый запрос, добавляем проверку на сумму кредита
        if (!$isFirstRequest) {
            $fieldsToCheck['creditAmount'] = "Поле Совокупный лимит клиента - пустое";
        }

        // Если запрос множественный, добавляем проверку согласия ПДН
        $fieldsToCheck['bkiConsentDate'] = $isMultiple ? "Поле Согласие ПДН - пустое" : "Поле Дата получения согласия - пустое";

        // Проверяем поля и собираем ошибки
        $errors = [];
        foreach ($fieldsToCheck as $field => $errorMessage) {
            if (empty($requisite[$field] ?? $contactData[$field])) {
                $errors[] = $errorMessage;
            }
        }

        return implode(" | ", $errors);
    }
    private static function formatDate(string $date, string $format = 'Y-m-d'): string
    {
        try {
            $formattedDate = date($format, strtotime($date));
            return $formattedDate ?: 'Invalid Date'; // Возвращает 'Invalid Date', если форматирование не удалось
        } catch (\Exception $e) {
            Logs\File::AddMessage($e->getMessage(), "formatDate error", LOG_BP);
            return 'Invalid Date'; // Обработка ошибки
        }
    }
    private static function getFullName(array $requisite): string
    {
        // Проверка наличия и значений каждого поля, чтобы избежать ошибок в выводе
        $lastName = $requisite['RQ_LAST_NAME'] ?? '';
        $firstName = $requisite['RQ_FIRST_NAME'] ?? '';
        $secondName = $requisite['RQ_SECOND_NAME'] ?? '';

        // Собираем полное имя, исключая пустые значения
        return trim("{$lastName} {$firstName} {$secondName}");
    }
    private static function formatPassportData(
        array $requisite,
        array $contactsIdList,
        bool $isFirstRequest,
        bool $isMultiple = false,
              $contactType = false,
        $nbkiReportConsentPeriod
    ): array
    {
        $data = [];

        if(!$isMultiple) {
            $data["position"] = $contactsIdList['types'];
        } else {
            $data["position"] = $contactType;
        }

        // Форматирование даты выдачи паспорта
        $passportIssuedDate = $requisite['RQ_IDENT_DOC_DATE'] ?? null;
        $formattedIssuedDate = $passportIssuedDate ? self::formatDate($passportIssuedDate, "Y-m-d") . "T" . self::formatDate($passportIssuedDate, "H:i:s") : null;

        // Форматирование даты рождения
        $birthday = $requisite['UF_CRM_1684493639'] ?? null;
        $formattedBirthday = $birthday ? self::formatDate($birthday, "d.m.Y") : '';


        $data['isGuarantor'] = false;
        $data['innFl'] = $requisite['RQ_INN'] ?? '';
        $data['name'] = $requisite['RQ_FIRST_NAME'] ?? '';
        $data['surname'] = $requisite['RQ_LAST_NAME'] ?? '';
        $data['birthday'] = $formattedBirthday;
        $data['patronymic'] = $requisite['RQ_SECOND_NAME'] ?? '';
        $data['birthPlace'] = $requisite['UF_CRM_1647929611'] ?? '';
        $data['creditAmount'] = !$isFirstRequest ? intval(str_replace('|RUB', '', $contactsIdList['creditAmount'] ?? '')) : 0;
        $data['bkiReportConsentDate'] = $contactsIdList['bkiConsentDate'] ?? '';
        $data['bkiReportDealDate'] = $contactsIdList['bkiConsentDate'] ?? '';
        $data['passportIssuer'] = $requisite['RQ_IDENT_DOC_ISSUED_BY'] ?? '';
        $data['passportNumber'] = $requisite['RQ_IDENT_DOC_NUM'] ?? '';
        $data['passportSeries'] = $requisite['RQ_IDENT_DOC_SER'] ?? '';
        $data['passportIssuedAt'] = $formattedIssuedDate ?? '';
        $data['passportIssuerCode'] = $requisite['RQ_IDENT_DOC_DEP_CODE'] ?? '';
        $data['bkiReportStateCode'] = "643";
        $data['bkiReportBirthPlaceCode'] = "643";
        $data['nbkiReportConsentPeriod'] = $nbkiReportConsentPeriod;

        return $data;
    }
    private static function getIndividualPassportInfo($contactId, $contactsIdList, $isFirstRequest, $isMultiple = false, $contactType = false, $nbkiReportConsentPeriod): array
    {
        $data = [];

        if($isMultiple) {
            $contactId = $contactId['id'];
            //$contactType = $contactId['type'];
        }

        //if(!$isMultiple) {
        $requisite = self::getCompanyRequisiteData($contactId);
        if(!$requisite) $requisite = self::getContactRequisiteData($contactId);

        $textError = self::validateRequisiteData($requisite, $contactsIdList, $isFirstRequest, $isMultiple);

        if ($textError !== "") {
            $fullName = $isMultiple ? $contactsIdList['fullName'] : self::getFullName($requisite);
            $data["errorFields"][$fullName] = $textError;
        } else {
            $data[$contactId] = self::formatPassportData($requisite, $contactsIdList, $isFirstRequest, $isMultiple, $contactType, $nbkiReportConsentPeriod);
        }

        return $data;
    }

    //endregion getPassportInfo

    //region getBitrixData
    private static function validateBitrixData(array $itemData, array $companyData, $isOrganization = false): array
    {
        $requiredFieldsConfig = [
            'common' => [
                'UF_CRM_56_1684744846487' => "approvedLimit",
                'UF_CRM_56_1684744827969' => "portfolio",
                'UF_CRM_56_1684744875738' => "balance",
                'ASSIGNED_BY_ID' => "Ответственный СЗ",
            ],
            'organization' => [
                'UF_CRM_1595595411835' => "ФИО клиента"
            ],
            'individual' => [
                'UF_CRM_1595595411835' => "Полное наименование Компании",
                'UF_CRM_1693788279' => "Документ согласия"
            ],
        ];

        $errors = [];
        $fieldsToCheck = $requiredFieldsConfig['common'];

        // Добавление специфичных полей для организации или физического лица
        $fieldsToCheck += $isOrganization ? $requiredFieldsConfig['organization'] : $requiredFieldsConfig['individual'];

        // Проверка наличия значений в обязательных полях
        foreach ($fieldsToCheck as $field => $errorMessage) {
            if (empty($itemData[$field] ?? $companyData[$field])) {
                $errors[] = "Поле {$errorMessage} - пустое";
            }
        }

        return $errors;
    }
    private static function convertCurrencyToFloat($currencyValue): float
    {
        // Удаляем любые символы, не являющиеся цифрами, десятичными точками или отрицательными знаками
        return (float)preg_replace('/[^\d.-]/', '', $currencyValue);
    }

    // Определение констант для полей, используемых в контрактах
    const FIELD_CONTRACT_DATE = 'UF_CRM_15_1679925201';
    const FIELD_TRANCHE_TERM = 'UF_CRM_15_SS_SROK';
    const FIELD_TRANCHE_RATE = 'UF_CRM_15_SS_STAVKA';
    private static function getContractDetails($contracts): array
    {
        // Проверка, если список контрактов пуст
        if (empty($contracts)) {
            return [
                'contractDate' => null,
                'lastTrancheDate' => null,
                'trancheTerm' => null,
                'trancheRate' => null,
            ];
        }

        $contractDetails = [];
        $contractDates = [];

        foreach ($contracts as $contractId) {
            // Получение данных контракта
            $contractItem = self::getItemData(188, $contractId);

            // Проверка на наличие необходимых полей в данных контракта
            if (isset($contractItem[self::FIELD_CONTRACT_DATE])) {
                $contractDates[$contractItem['ID']] = $contractItem[self::FIELD_CONTRACT_DATE];
                $contractDetails[$contractItem['ID']] = $contractItem;
            }
        }

        // Проверка на случай, если ни один контракт не содержит дату
        if (empty($contractDates)) {
            return [
                'contractDate' => null,
                'lastTrancheDate' => null,
                'trancheTerm' => null,
                'trancheRate' => null,
            ];
        }

        // Сортировка контрактов по дате
        asort($contractDates);

        $firstContractID = array_key_first($contractDates);
        $lastContractID = array_key_last($contractDates);

        // Возвращение данных о первом и последнем контракте
        return [
            'contractDate' => isset($contractDetails[$firstContractID][self::FIELD_CONTRACT_DATE]) ?
                date('Y-m-d', strtotime($contractDetails[$firstContractID][self::FIELD_CONTRACT_DATE])) : null,
            'lastTrancheDate' => isset($contractDetails[$lastContractID][self::FIELD_CONTRACT_DATE]) ?
                date('Y-m-d', strtotime($contractDetails[$lastContractID][self::FIELD_CONTRACT_DATE])) : null,
            'trancheTerm' => $contractDetails[$lastContractID][self::FIELD_TRANCHE_TERM] ?? null,
            'trancheRate' => $contractDetails[$lastContractID][self::FIELD_TRANCHE_RATE] ?? null,
        ];
    }
    private static function getContractData(array $contracts): array
    {
        $contractData = [
            'contractDate' => null,
            'lastTrancheDate' => null,
            'trancheTerm' => null,
            'trancheRate' => null,
        ];

        // Проверка наличия контрактов и вызов метода для получения деталей контракта
        if (!empty($contracts)) {
            $contractDetails = self::getContractDetails($contracts);

            // Обновление данных контракта, если в деталях присутствуют соответствующие значения
            $contractData['contractDate'] = $contractDetails['contractDate'];
            $contractData['lastTrancheDate'] = $contractDetails['lastTrancheDate'];
            $contractData['trancheTerm'] = $contractDetails['trancheTerm'];
            $contractData['trancheRate'] = $contractDetails['trancheRate'];
        }

        return $contractData;
    }
    const GUARANTOR_OSK_FIELDS = [
        'UF_CRM_56_1686298851',
        'UF_CRM_56_1686298912',
        'UF_CRM_56_1693179732',
        'UF_CRM_56_1693179748',
        'UF_CRM_56_1693179765',
        'UF_CRM_56_1693179777'
    ];
    private static function countGuarantors(array $itemData): int
    {
        return count(array_filter(self::GUARANTOR_OSK_FIELDS, fn($field) => !empty($itemData[$field])));
    }
    private static function populateBitrixData($itemData, $companyData)
    {
        $bitrixData = [];

        $limitRenewal = false;
        $isProcrastinator = false;

        str_contains($_SERVER['HTTP_HOST'], 'test') ? ($isProcrastinatorValue = 21229) : ($isProcrastinatorValue = 21753);
        if($itemData['UF_CRM_LIMIT_RENEWAL_DATE']) $limitRenewal = true;
        if($itemData['UF_CRM_IS_PROCRASTINATOR'] == $isProcrastinatorValue) $isProcrastinator = true;

        $bitrixData['clientName'] = (str_replace('"','',$companyData['UF_CRM_1595595411835'])??$companyData['UF_CRM_1595595411835']);
        $bitrixData['clientGroupIndicator'] = self::getClientGroupIndicator($companyData);
        $bitrixData['mpCount'] = (str_replace('"','',$companyData['UF_CRM_1706982719'])??$companyData['UF_CRM_1706982719']) ?? '';
        $bitrixData['approvedLimit'] = self::convertCurrencyToFloat($itemData['UF_CRM_56_1684744846487']);
        $bitrixData['portfolio'] = self::convertCurrencyToFloat($itemData['UF_CRM_56_1684744827969']);
        $bitrixData['balance'] = self::convertCurrencyToFloat($itemData['UF_CRM_56_1684744875738']);
        $bitrixData['supportDepartmentEmployee'] = self::getUsernameByID($itemData['ASSIGNED_BY_ID']);
        $bitrixData['limitRenewal'] = $limitRenewal;
        $bitrixData['isProcrastinator'] = $isProcrastinator;
        $bitrixData['marketplaceNominalAccountChecks'] = self::getMarketplaceNominalAccountChecks($companyData);

        $contractData = self::getContractData($itemData['UF_CRM_56_1684743456']);
        $bitrixData = array_merge($bitrixData, $contractData);

        $bitrixData['guarantorCount'] = self::countGuarantors($itemData);

        return $bitrixData;
    }
    private static function getIndividualBitrixData($itemData, $companyData)
    {
        $errors = self::validateBitrixData($itemData, $companyData);

        // Проверяем наличие ошибок, если есть — добавляем в результат
        if (!empty($errors)) {
            return [
                'errorFields' => [
                        $companyData['UF_CRM_1595595411835'] ?? 'Unknown Client' => implode(' | ', $errors)
                ]
            ];
        }
        // Заполняем данные для клиента, если ошибок нет
        return self::populateBitrixData($itemData, $companyData);
    }
    private static function getOrganizationBitrixData($itemData, $companyData, $company)
    {
        // Проверяем данные компании на ошибки
        $errors = self::validateBitrixData($itemData, $companyData, true);

        // Если есть ошибки, возвращаем их сразу
        if (!empty($errors)) {
            return [
                'errorFields' => [
                        $company['fullName'] ?? 'Unknown Organization' => implode(' | ', $errors)
                ]
            ];
        }

        // Если ошибок нет, заполняем данные для организации
        return self::populateBitrixData($itemData, $companyData);
    }
    public static function getBitrixData(int $entityTypeId, int $elementID, $clientData): array
    {
        $bitrixData = [];

        try {
            // Получение данных элемента CRM и компании
            $itemData = self::getItemData($entityTypeId, $elementID);
            $companyData = self::getCompanyData($clientData['companyId']);

            // Проверка на успешное получение данных
            if (!$itemData || !$companyData) {
                throw new \Exception("Не удалось получить данные элемента или компании.");
            }

            // Выбор метода в зависимости от типа клиента
            $bitrixData = $clientData['isOrganization']
                ? self::getOrganizationBitrixData($itemData, $companyData, $clientData['company'])
                : self::getIndividualBitrixData($itemData, $companyData);

        } catch (\Exception $e) {
            // Логирование ошибки и возврат сообщения об ошибке
            Logs\File::AddMessage($e->getMessage(), "getBitrixData error", LOG_BP);
            return ['error' => $e->getMessage()];
        }

        return $bitrixData;
    }
    //endregion getBitrixData

    //region getRegistrationData
    private static function getRegistrationData(array $contactsIdList, bool $organization, bool $kladr = false): array {
        // Получение данных о регистрации
        $registrationData = self::getRegistrationDataById($contactsIdList, $organization, $kladr);

        // Проверка на наличие ошибок
        if (isset($registrationData['errorFields'])) {
            Logs\File::AddMessage($registrationData['errorFields'], "Error in registration data retrieval", LOG_BP);
            return ['error' => "Ошибка при получении регистрационных данных: " . json_encode($registrationData['errorFields'], JSON_UNESCAPED_UNICODE)];
        }

        return $registrationData;
    }
    public static function getRegistrationDataById(array $contactsIdList, bool $organization = false, bool $kladr = false): array
    {
        // Определяем массив для хранения результатов
        $result = [];

        // Проверяем, является ли это запросом для организации или индивидуального предпринимателя
        $contactsIds = is_array($contactsIdList['ids']) ? $contactsIdList['ids'] : [$contactsIdList['ids']];

        Logs\File::AddMessage($contactsIds, "getRegistrationDataById contactsIds", LOG_BP);
        foreach ($contactsIds as $contactId) {
            if($organization) {
                // Получаем данные адреса для каждого контакта
                $addressData = self::getAddressData($contactId['id'], false);

                // Проверяем наличие ошибок в адресных данных
                if (isset($addressData['errorFields'])) {
                    $result['errorFields'][$contactsIdList['fullName']." (".$contactId['id'].")"] = $addressData['errorFields'];
                } else {
                    // Если нет ошибок, добавляем адрес в результат
                    $result[$contactId['id']]['registrationAddressFiasId'] = $addressData['registrationAddressFiasId'];
                }
            } else {
                // Получаем данные адреса для каждого контакта
                $addressData = self::getAddressData($contactId, false);

                // Проверяем наличие ошибок в адресных данных
                if (isset($addressData['errorFields'])) {
                    $result['errorFields'][$contactsIdList['fullName']." (".$contactId.")"] = $addressData['errorFields'];
                } else {
                    // Если нет ошибок, добавляем адрес в результат
                    $result[$contactId]['registrationAddressFiasId'] = $addressData['registrationAddressFiasId'];
                }
            }

        }
        return $result;
    }
    public static function _getRegistrationDataById(array $contactsIdList, bool $organization = false): array
    {
        // Определяем массив для хранения результатов
        $result = [];

        // Проверяем, является ли это запросом для организации или индивидуального предпринимателя
        $contactsIds = is_array($contactsIdList['ids']) ? $contactsIdList['ids'] : [$contactsIdList['ids']];

        Logs\File::AddMessage($contactsIds, "_getRegistrationDataById contactsIds", LOG_BP);
        foreach ($contactsIds as $contactId) {
            if($organization) {
                // Получаем данные адреса для каждого контакта
                $addressData = self::getAddressData($contactId, $organization);

                // Проверяем наличие ошибок в адресных данных
                if (isset($addressData['errorFields'])) {
                    $result['errorFields'][$contactsIdList['fullName']." (".$contactId.")"] = $addressData['errorFields'];
                } else {
                    // Если нет ошибок, добавляем адрес в результат
                    $result[$contactId]['registrationAddressFiasId'] = $addressData['registrationAddressFiasId'];
                }
            } else {
                // Получаем данные адреса для каждого контакта
                $addressData = self::getAddressData($contactId, $organization);

                // Проверяем наличие ошибок в адресных данных
                if (isset($addressData['errorFields'])) {
                    $result['errorFields'][$contactsIdList['fullName']." (".$contactId.")"] = $addressData['errorFields'];
                } else {
                    // Если нет ошибок, добавляем адрес в результат
                    $result[$contactId]['registrationAddressFiasId'] = $addressData['registrationAddressFiasId'];
                }
            }

        }
        return $result;
    }

    private static function extractRegistrationAddress($addressList)
    {
        Logs\File::AddMessage($addressList, "extractRegistrationAddress addressList", LOG_BP);

        foreach ($addressList as $address) {
            if ($address['TYPE_ID'] == 4) {  // Проверка на нужный тип адреса
                try {
                    $addressController = new \Bitrix\Location\Controller\Address();
                    Logs\File::AddMessage($address['LOC_ADDR_ID'], "extractRegistrationAddress LOC_ADDR_ID", LOG_BP);
                    return $addressController->findById($address['LOC_ADDR_ID'])['fieldCollection'] ?? null;
                } catch (\Exception $e) {
                    Logs\File::AddMessage($e->getMessage(), "extractRegistrationAddress error", LOG_BP);
                    return null;
                }
            }
        }
        // Логируем ошибку, если адрес типа 4 не найден
        Logs\File::AddMessage("Registration address with TYPE_ID 4 not found.", "extractRegistrationAddress", LOG_BP);
        return null;
    }
    private static function extractLegalAddress($addressList)
    {
        foreach ($addressList as $address) {
            if ($address['TYPE_ID'] == 6) {  // Проверка на нужный тип адреса
                try {
                    $addressController = new \Bitrix\Location\Controller\Address();
                    return $addressController->findById($address['LOC_ADDR_ID'])['fieldCollection'] ?? null;
                } catch (\Exception $e) {
                    Logs\File::AddMessage($e->getMessage(), "extractLegalAddress error", LOG_BP);
                    return null;
                }
            }
        }
        // Логируем ошибку, если адрес типа 4 не найден
        Logs\File::AddMessage("Legal address with TYPE_ID 6 not found.", "extractLegalAddress", LOG_BP);
        return null;
    }
    private static function getAddressList($contactId)
    {
        try {
            $resultAddressList = \CRest::call('crm.address.list', [
                'filter' => ['ANCHOR_ID' => $contactId,'ENTITY_TYPE_ID'=>8],
                'select' => ['TYPE_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID', 'ANCHOR_ID', 'LOC_ADDR_ID']
            ]);

            // Проверяем, если результат пуст или в формате с ошибкой
            if (isset($resultAddressList['error']) || empty($resultAddressList['result'])) {
                Logs\File::AddMessage("Failed to retrieve address list for contact ID {$contactId}", "getAddressList error", LOG_BP);
                return null;
            }

            return $resultAddressList['result'];
        } catch (\Exception $e) {
            // Логируем ошибку вызова \CRest::call в случае исключения
            Logs\File::AddMessage($e->getMessage(), "getAddressList exception", LOG_BP);
            return null;
        }
    }
    private static function getKladrFieldData(array $addressFields, string $fieldId, string $contentType, string $regionId = '', string $cityId = ''): ?array
    {
        if (empty($addressFields[$fieldId])) {
            return [];
        }

        $query = explode(" ", $addressFields[$fieldId])[0];
        $curlOptions = [
            CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}&regionId={$regionId}&cityId={$cityId}",
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
            CURLOPT_RETURNTRANSFER => true
        ];
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $result = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Проверяем HTTP-код ответа и результат
        if ($httpCode !== 200 || !isset($result['result'])) {
            Logs\File::AddMessage("Failed to retrieve Kladr data for {$fieldId} with status code {$httpCode}", "getKladrFieldData error", LOG_BP);
            return null;
        }

        // Если результат пуст, также логируем предупреждение
        if (empty($result['result'])) {
            Logs\File::AddMessage("Empty result for Kladr data for {$fieldId}.", "getKladrFieldData warning", LOG_BP);
            return null;
        }

        return $result['result'][1] ?? null;
    }
    private static function getHouseNumber($addressFields)
    {
        $houseNumber = trim(self::getField($addressFields, 400));

        if(str_contains($houseNumber, 'д')) {
            $houseNumber = trim(explode("д", $houseNumber)[0]);
        }

        if(str_contains($houseNumber, 'строение')) {
            $houseNumber = trim(explode("строение", $houseNumber)[0]);
        }
        elseif(str_contains($houseNumber, 'корпус')) {
            $houseNumber = trim(explode("корпус", $houseNumber)[0]);
        }
        // Логируем результат
        Logs\File::AddMessage($houseNumber, "houseNumber", LOG_BP);

        return $houseNumber;
    }
    private static function extractBuilding($addressFields): ?string
    {
        $houseNumber = trim(self::getField($addressFields, 400));
        if(str_contains($houseNumber, 'строение')) {
            $Building = (int) trim(explode("строение", $houseNumber)[1]);
        } else {
            $Building = null;
        }
        return $Building;
    }
    private static function extractStructure($addressFields): ?string
    {
        $houseNumber = trim(self::getField($addressFields, 400));
        if(str_contains($houseNumber, 'корпус')) {
            $Structure = (int) trim(explode("корпус", $houseNumber)[1]);
        } else {
            $Structure = null;
        }
        return $Structure;
    }
    private static function getField(array $addressFields, int $fieldId): ?string
    {
        // Проверяем наличие значения по ключу $fieldId и возвращаем его, если он существует
        if (isset($addressFields[$fieldId])) {
            $fieldValue = trim($addressFields[$fieldId]);
            Logs\File::AddMessage($fieldValue, "Extracted field value for ID $fieldId", LOG_BP);
            return $fieldValue;
        }

        // Логирование в случае отсутствия значения по данному ключу
        Logs\File::AddMessage("Value not found for field ID $fieldId", "getField warning", LOG_BP);
        return null;
    }
    private static function getKladrData(array $addressFields): array
    {
        //Logs\File::AddMessage($addressFields, "getKladrData addressFields", LOG_BP);

        // Получение данных ФИАС ID
        $registrationAddressFiasId = self::getField($addressFields, 900);
        if (empty($registrationAddressFiasId)) {
            return ['errorFields' => 'Нет данных о ФИАС ID'];
        }

        /*
        // Получение данных региона
        $regionData = self::getKladrFieldData($addressFields, 200, 'region');
        if (empty($regionData)) {
            return ['errorFields' => 'Нет данных о Регионе'];
        }

        // Получение данных города
        $cityData = self::getKladrFieldData($addressFields, 300, 'city', $regionData['id']);
        if (empty($cityData) && $regionData['name'] !== 'Москва') {
            return ['errorFields' => 'Нет данных о Городе'];
        }

        // Получение данных улицы
        $streetData = self::getKladrFieldData($addressFields, 340, 'street', $regionData['id'], $cityData['id']);
        if (empty($streetData)) {
            return ['errorFields' => 'Нет данных об Улице'];
        }*/

        // Заполнение информации об адресе
        return [
            'registrationAddressFiasId' => $registrationAddressFiasId
            /*'region' => intval(substr($regionData['id'], 0, 2)),  // Код региона
            'regionKladrId' => $regionData['id'],                 // ID региона в КЛАДР
            'city' => $cityData['name'] ?? '',                    // Название города
            'street' => $streetData['name'] ?? '',                // Название улицы
            'house' => self::getHouseNumber($addressFields),      // Номер дома
            'flat' => self::getField($addressFields, 600),        // Номер квартиры
            'building' => self::extractBuilding($addressFields),  // Строение, если есть
            'structure' => self::extractStructure($addressFields),// Корпус, если есть
            'registrationType' => "const",                        // Тип регистрации, константное значение*/
        ];
    }
    private static function getSimpleAddressData(array $addressFields): array
    {
        //Logs\File::AddMessage($addressFields, "getSimpleAddressData addressFields", LOG_BP);

        // Получение данных ФИАС ID
        $registrationAddressFiasId = self::getField($addressFields, 900);
        if (empty($registrationAddressFiasId)) {
            return ['errorFields' => 'Нет данных о ФИАС ID'];
        }

        /*// Получение данных региона
        $regionData = self::getKladrFieldData($addressFields, 200, 'region');
        if (empty($regionData)) {
            return ['errorFields' => 'Нет данных о Регионе'];
        }

        // Получение названия города, улицы и номера дома напрямую из переданных данных
        $cityData = self::getField($addressFields, 300);          // Город
        if (empty($cityData)) {
            return ['errorFields' => 'Нет данных о Городе'];
        }*/

        return [
            'registrationAddressFiasId' => $registrationAddressFiasId
            /*'region' => intval(substr($regionData['id'], 0, 2)),   // Код региона
            'regionKladrId' => $regionData['id'],                  // ID региона в КЛАДР
            'city' => $cityData,                                   // Название города
            'street' => self::getField($addressFields, 340),       // Улица
            'house' => self::getField($addressFields, 400),        // Номер дома
            'flat' => self::getField($addressFields, 600),         // Квартира
            'building' => self::extractBuilding($addressFields),   // Строение, если есть
            'structure' => self::extractStructure($addressFields), // Корпус, если есть
            'registrationType' => "const",                         // Тип регистрации (фиксированное значение)*/
        ];
    }
    private static function getAddressData(int $contactId, bool $organization): array
    {
        // Получаем список адресов для указанного контакта
        $addressList = self::getAddressList($contactId);

        //Logs\File::AddMessage($addressList, "getAddressData addressList", LOG_BP);
        if($organization) {

            // Проверяем и извлекаем адрес для регистрации (тип 6 - Юридический адрес)
            $legalAddress = self::extractLegalAddress($addressList);

            // Если адрес не найден, возвращаем сообщение об ошибке
            if (!$legalAddress) {
                return ['errorFields' => 'Нет данных об юридическом адресе'];
            }

            $registrationAddressFiasId = self::getField($legalAddress, 900);
            if (empty($registrationAddressFiasId)) {
                return ['errorFields' => 'Нет данных о ФИАС ID'];
            }
            return [
                'legalAddressFiasId' => $registrationAddressFiasId
            ];
        } else {
            // Проверяем и извлекаем адрес для регистрации (тип 4 - регистрация)
            $registrationAddress = self::extractRegistrationAddress($addressList);

            // Если адрес не найден, возвращаем сообщение об ошибке
            if (!$registrationAddress) {
                return ['errorFields' => 'Нет данных о регистрационном адресе'];
            }


            $registrationAddressFiasId = self::getField($registrationAddress, 900);
            if (empty($registrationAddressFiasId)) {
                return ['errorFields' => 'Нет данных о ФИАС ID'];
            }
            return [
                'registrationAddressFiasId' => $registrationAddressFiasId
            ];
        }
    }
    //endregion getRegistrationData

    //region getInfo
    private static function prepareOvkFields($data): array
    {
        return [
            'UF_CRM_COMPANY_OVK_CHECKDOMAIN' => $data["checkDomain"] ?? null, // Проверка домена
            'UF_CRM_COMPANY_OVK_CHECK_COMPANY_INFO' => $data["checkCompanyInfo"] ?? null, // Проверка информации о компании
            'UF_CRM_COMPANY_OVK_CHECK_LICENSES' => $data["checkLicenses"] ?? null, // Проверка лицензий
            'UF_CRM_COMPANY_OVK_IS_IN_TERRORIST_LIST' => $data["isInTerroristList"] ?? null, // Наличие в списке террористов
            'UF_CRM_COMPANY_OVK_IS_IN_MVK_LIST' => $data["isInMvkList"] ?? null, // Наличие в списке МВК
            'UF_CRM_COMPANY_OVK_IS_IN_OMU_LIST' => $data["isInOmuList"] ?? null, // Наличие в списке ОМУ
            'UF_CRM_COMPANY_OVK_IS_IN_STRATEGIC_LIST' => $data["isInStrategicList"] ?? null, // Наличие в стратегическом списке
            'UF_CRM_COMPANY_OVK_IS_IN_OPK_ULS' => $data["isInOpkUls"] ?? null, // Наличие в списке ОПК УЛС
            'UF_CRM_COMPANY_OVK_IS_IN_SANCTION_LIST' => $data["isInSanctionList"] ?? null, // Наличие в санкционном списке
            'UF_CRM_COMPANY_OVK_IS_IN_PEP_LIST' => $data["isInPepList"] ?? null, // Наличие в списке ПЭП
            'UF_CRM_COMPANY_OVK_IS_IN_764_LIST' => $data["isIn764List"] ?? null, // Наличие в списке 764
            'UF_CRM_COMPANY_OVK_IS_IN_FINANCIAL_PYRAMYDE' => $data["isInFinancialPyramyde"] ?? null, // Наличие в финансовой пирамиде
            'UF_CRM_COMPANY_OVK_IS_IN_ILLEGAL_CREDITOR' => $data["isInIllegalCreditor"] ?? null, // Наличие в списке нелегальных кредиторов
            'UF_CRM_COMPANY_OVK_RESULT' => $data["result"] ?? null, // Результат
        ];
    }
    public static function getInfo(int $entityTypeId, int $elementID, bool $kladr = false): bool|array|string
    {
        if ($entityTypeId === \CCrmOwnerType::Company) {
            return self::getInfoByCompanyId($elementID, $kladr);
        }

        $companyId = self::getCompanyIdBySmart($entityTypeId, $elementID);
        $contactsIdList = self::getContactListByCompanyId($companyId);
        // Получаем данные элемента
        $itemData = self::getItemData($entityTypeId, $elementID);

        if ($errorResponse = self::checkForErrors($contactsIdList)) {
            self::handleError($entityTypeId, $elementID, $errorResponse);
            return json_encode($errorResponse,JSON_UNESCAPED_UNICODE);
        }

        $result = self::initializeResult($companyId, $contactsIdList);
        if($entityTypeId == 134 && $itemData['UF_CRM_56_1705406400'] == "") {
            $nbkiReportConsentPeriod = "1";
        } elseif($entityTypeId == 134 && $itemData['UF_CRM_56_1705406400'] !== "") {
            $nbkiReportConsentPeriod = "3";
        }

        if($entityTypeId == 149 && $itemData['UF_CRM_IS_CONTRACTS'] == true){
            $nbkiReportConsentPeriod = "3";
        } elseif($entityTypeId == 149 && $itemData['UF_CRM_IS_CONTRACTS'] == false) {
            $nbkiReportConsentPeriod = "1";
        }

        $result['isFirstRequest'] = ($entityTypeId == 149);
        $result['nbkiReportConsentPeriod'] = $nbkiReportConsentPeriod;
        $result['isV2'] = true;

        // Получаем паспортную информацию
        $passportInfo = self::getPassportInfo($contactsIdList, $result['organization'], $result['isFirstRequest'], $result['nbkiReportConsentPeriod']);

        if ($errorResponse = self::checkForErrors($passportInfo)) {
            self::handleError($entityTypeId, $elementID, $errorResponse);
            return json_encode($errorResponse,JSON_UNESCAPED_UNICODE);
        }

        // Получаем регистрационные данные
        $regData = self::getRegistrationData($contactsIdList, $result['organization'], $kladr);


        if ($errorResponse = self::checkForErrors($regData)) {
            self::handleError($entityTypeId, $elementID, $errorResponse);
            return json_encode($errorResponse,JSON_UNESCAPED_UNICODE);
        }
        Logs\File::AddMessage($passportInfo, "passportInfo", LOG_BP);
        Logs\File::AddMessage($regData, "regData", LOG_BP);

        // Объединение данных
        $result['payload'] = self::mergeData($passportInfo, $regData);

        if ($entityTypeId !== 149) {
            $clientData = [
                'companyId' => $companyId,
                'company' => $contactsIdList,
                'isOrganization' => $result['organization']
            ];
            $bitrixData = self::getBitrixData($entityTypeId, $elementID, $clientData);

            if ($errorResponse = self::checkForErrors($bitrixData)) {
                self::handleError($entityTypeId, $elementID, $errorResponse);
                return json_encode($errorResponse,JSON_UNESCAPED_UNICODE);
            }
            $result['bitrixData'] = $bitrixData;
        }

        Logs\File::AddMessage($result, "result bodyArray", LOG_BP);

        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }
    private static function handleError(int $entityTypeId, int $elementID, array $errorResponse): void
    {
        if ($entityTypeId == 134) {
            self::moveToStage($entityTypeId, $elementID, 'DT134_104:UC_8397AG');
        }
        /*\CRest::call('crm.timeline.comment.add', [
            'fields' => [
                "ENTITY_ID" => $elementID,
                "ENTITY_TYPE" => 'DYNAMIC_' . $entityTypeId,
                "COMMENT" => "[b]" . json_encode($errorResponse, JSON_UNESCAPED_UNICODE) . "[/b]"
            ]
        ]);*/
    }
    public static function getInfoByCompanyId(int $companyId, bool $kladr = true): string
    {
        // Получаем данные по контактам компании
        $contactsIdList = self::getContactListByCompanyId($companyId);

        if ($errorResponse = self::checkForErrors($contactsIdList)) {
            return json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
        }

        // Инициализация результата
        $result = self::initializeResult($contactsIdList);
        $result['isFirstRequest'] = true; // Является ли это первым запросом

        // Получаем паспортную информацию
        $passportInfo = self::getPassportInfo($contactsIdList, $result['organization'], $result['isFirstRequest']);

        if ($errorResponse = self::checkForErrors($passportInfo)) {
            return json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
        }

        // Получаем регистрационные данные
        $regData = self::getRegistrationData($contactsIdList, $result['organization'], $kladr);

        if ($errorResponse = self::checkForErrors($regData)) {
            return json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
        }

        // Объединение данных
        $result['payload'] = self::mergeData($passportInfo, $regData);

        // Логируем результат
        Logs\File::AddMessage($result, "result", LOG_BP);

        // Возвращаем результат в формате JSON
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    public static function getOvkByCompanyId($companyId) {

        $timeData = Logs\TimeData::start();
        $point = "BX_SE";
        $companyData = self::getCompanyData($companyId);

        if (!$companyData) {
            return json_encode(['error' => 'Company data not found'], JSON_UNESCAPED_UNICODE);
        }

        $inn = $companyData['UF_CRM_6433D7C925893'];
        $url = strpos($_SERVER['HTTP_HOST'], 'test') ? sprintf('https://internal.dev.seller-capital.ru/GetOvk?inn=%s', $inn) : sprintf('https://internal.seller-capital.ru/GetOvk?inn=%s', $inn);
        //$url = sprintf('https://internal.seller-capital.ru/GetOvk?inn=%s', $inn);

        // Формируем объект для логирования
        $objectData = [
            'METHOD' => 'GET',
            'ITEM_TITLE' => "SE: Получение чек-листа ОВК по " . $companyData['TITLE'],
            'ITEM_ID' => $companyId,
            'ITEM_TYPE_ID' => \CCrmOwnerType::Company,
            'INIT_OBJECT_URL' => "https://".$_SERVER['HTTP_HOST']."/crm/type/".\CCrmOwnerType::Company."/details/".$companyId."/",
        ];

        // Формируем логируемые данные
        $logData = [
            'objectData' => $objectData,
            'methodName' => __METHOD__,
            'controllerName' => __CLASS__,
            'method' => 'GET',
            'timeData' => $timeData,
        ];
        if(defined("TOKEN_KEY")) {
            // Используем KPLab\ApiRequest для выполнения GET-запроса
            $headersRequest = [
                'Authorization' => 'Bearer ' . TOKEN_KEY,
                'Content-Type' => 'application/json'
            ];
        }
        // Вызов метода sendRequest с передачей $logData
        $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'GET', $headersRequest, null, $logData);

        if (!$jsonResponse || $jsonResponse['status'] !== 'Success') {
            return json_encode(['error' => 'Failed to retrieve data'], JSON_UNESCAPED_UNICODE);
        }

        $fields = self::prepareOvkFields(json_decode($jsonResponse['response'], true));
        $fields['UF_CRM_DATE_CHEK_OVK'] = date('d.m.Y H:i:s');

        // Получаем фабрику и обновляем данные компании
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $item = $factory->getItem($companyId);
        if (!$item) {
            return json_encode(['error' => 'Company item not found'], JSON_UNESCAPED_UNICODE);
        }

        $item->setFromCompatibleData($fields);
        $operation = $factory->getUpdateOperation($item);
        $operation->disableCheckAccess()->enableCheckWorkflows()->enableCheckRequiredUserFields();

        $operationResult = $operation->launch();
        if (!$operationResult->isSuccess()) {
            return json_encode(['error' => $operationResult->getErrorMessages()], JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['success' => 'Данные ovk успешно сохранились'], JSON_UNESCAPED_UNICODE);
    }
    //endregion getInfo

    public static function createRequest($jsonData, $objectData, $timeData, $point = "", $EnrichViaBuffer = false, $emulation = false, $test = false) {
        // Определяем URL в зависимости от режима (продакшен или тест)
        $host = $_SERVER['HTTP_HOST'];
        $url = str_contains($host, 'test')
            ? ($EnrichViaBuffer ? 'https://internal.dev.seller-capital.ru/Bitrix/EnrichViaBuffer' : 'https://internal.dev.seller-capital.ru/Bitrix/CreateBalancePlatformRequest')
            : ($EnrichViaBuffer ? 'https://internal.seller-capital.ru/Bitrix/EnrichViaBuffer' : 'https://internal.seller-capital.ru/Bitrix/CreateBalancePlatformRequest');

        if (defined("TOKEN_KEY")) {
            // Формируем объект для логирования
            $objectData['METHOD'] = 'POST'; // Добавляем метод POST
            $logData = [
                'objectData' => $objectData,
                'methodName' => __METHOD__,
                'controllerName' => __CLASS__,
                'method' => 'POST',
                'timeData' => $timeData,
                'point' => $point,
            ];

            // Формируем заголовки запроса
            $headersRequest = [
                "key" => TOKEN_KEY,
                "Content-Type" => "application/json"
            ];

            // Логируем данные перед отправкой
            /*Logs\File::AddMessage([
                'URL' => $url,
                'Headers' => $headersRequest,
                'Data' => $jsonData,
                'LogData' => $logData
            ], "Request Data", LOG_BP);*/

            // Выполняем запрос через KPLab\ApiRequest
            $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $headersRequest, $jsonData, $logData);

            // Логируем ответ
            //Logs\File::AddMessage($jsonResponse, "Response Data", LOG_BP);
            return $jsonResponse;
        }

        return null;
    }
    public static function getRequest($jsonData, $objectData, $timeData, $point = "", $EnrichViaBuffer = false,
                                      $emulation = false) {

        //$url = 'https://internal.seller-capital.ru/GetOvk';
        $url = strpos($_SERVER['HTTP_HOST'], 'test') ? "https://internal.dev.seller-capital.ru/GetOvk" : "https://internal.seller-capital.ru/GetOvk";
        if(defined("TOKEN_KEY")) {
            // Формируем объект для логирования
            $logData = [
                'objectData' => $objectData,
                'methodName' => __METHOD__,
                'controllerName' => __CLASS__,
                'method' => 'GET',
                'timeData' => $timeData,
                'point' => $point,
            ];
            // Формируем заголовки запроса
            $headersRequest = [
                "key" => TOKEN_KEY,
                "Content-Type" => "application/json"
            ];
            // Логируем данные перед отправкой
            /*Logs\File::AddMessage([
                'URL' => $url,
                'Headers' => $headersRequest,
                'Data' => $jsonData,
                'LogData' => $logData
            ], "Request Data", LOG_BP);*/

            // Выполняем запрос через KPLab\ApiRequest
            $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'GET', $headersRequest, $jsonData, $logData);

            // Логируем ответ
            //Logs\File::AddMessage($jsonResponse, "Response Data", LOG_BP);
            return $jsonResponse;
        }

        return null;
    }

    //region NEW methods
    const ENTITY_TYPE_ID = 149;
    const GUARANTOR_FIELDS = [
        'UF_CRM_49_1683882204',
        'UF_CRM_49_1683882348',
        'UF_CRM_49_1693180767',
        'UF_CRM_49_1693180780',
        'UF_CRM_49_1693180790',
        'UF_CRM_49_1693180802'
    ];
    const INDIVIDUAL_FIELDS = [
        'innFl' => 'RQ_INN',
        "passportSeries" => 'RQ_IDENT_DOC_SER',
        "passportNumber" => 'RQ_IDENT_DOC_NUM',
        "surname" => 'RQ_LAST_NAME',
        "name" => 'RQ_FIRST_NAME',
        "patronymic" => 'RQ_SECOND_NAME',
        "birthday" => 'UF_CRM_1684493639',
        "birthPlace" => 'UF_CRM_1647929611',
        "passportIssuer" => 'RQ_IDENT_DOC_ISSUED_BY',
        "passportIssuerCode" => 'RQ_IDENT_DOC_DEP_CODE',
        "passportIssuedAt" => 'RQ_IDENT_DOC_DATE',
        "clientType" => 'clientType'
    ];
    const CONSENT_DATE_FIELDS = [
        'company' => 'UF_CRM_1700138022',
        'contact' => 'UF_CRM_1700137692'
    ];
    public static function getInfoGuarantors($smartId)
    {
        if (!Loader::includeModule('crm')) {
            throw new \Exception("Модуль CRM не загружен");
        }

        $result = [];

        $entityTypeId = self::ENTITY_TYPE_ID;
        $factory = Container::getInstance()->getFactory($entityTypeId);
        $item = $factory->getItem($smartId);

        if ($item) {
            $itemData = $item->getData();

            //Logs\File::AddMessage($itemData,"itemData",LOG_BP);

            foreach (self::GUARANTOR_FIELDS as $field) {

                if (isset($itemData["{$field}"]) && $itemData["{$field}"] !== "") {

                    if($entityTypeId == 149 && $itemData['UF_CRM_IS_CONTRACTS'] == true){
                        $nbkiReportConsentPeriod = "3";
                    } elseif($entityTypeId == 149 && $itemData['UF_CRM_IS_CONTRACTS'] == false) {
                        $nbkiReportConsentPeriod = "1";
                    }

                    $guarantorValue = $itemData["{$field}"];
                    Logs\File::AddMessage($guarantorValue,"guarantorValue",LOG_BP);
                    $guarantorData = self::getGuarantorData($guarantorValue, $nbkiReportConsentPeriod);

                    if($guarantorData['errorFields'])
                        return $guarantorData;

                    if ($guarantorData) {
                        $result[] = $guarantorData;
                    }
                }
            }
        }

        return $result;
    }
    public static function getGuarantorData(string $guarantorValue, $nbkiReportConsentPeriod): ?array
    {

        Logs\File::AddMessage("guarantorValue: $guarantorValue", "getGuarantorData",LOG_BP);

        // Определяем тип поручителя и его ID
        if (preg_match('/CO_(\d+)$/', $guarantorValue, $matches)) {
            $guarantorId = (int)$matches[1];
            $guarantorTypeName = \CCrmOwnerType::CompanyName;
            $guarantorType = \CCrmOwnerType::Company; // Используем константу для типа компании
        } elseif (preg_match('/C_(\d+)$/', $guarantorValue, $matches)) {
            $guarantorId = (int)$matches[1];
            $guarantorTypeName = \CCrmOwnerType::ContactName; // Используем константу для типа контакта
            $guarantorType = \CCrmOwnerType::Contact; // Используем константу для типа контакта
        } else {
            return null; // Неверный формат, возвращаем null
        }


        Logs\File::AddMessage("ID: $guarantorId, тип: $guarantorType, типName: $guarantorTypeName", "getGuarantorData", LOG_BP);

        // Получение реквизитов поручителя через API
        $requisite = self::_getRequisiteData($guarantorId, $guarantorTypeName);

        if (!$requisite) {
            Logs\File::AddMessage("Не удалось получить реквизиты для ID: $guarantorId, тип: $guarantorType", "getGuarantorData error", LOG_BP);
            return null; // Реквизиты не найдены
        }

        //$guarantor['guarantorType'] = $guarantorType;

        if($requisite['PRESET_ID'] == 3 || $requisite['PRESET_ID'] == 2){
            $guarantor['not'] = 'organization';
            $guarantor['ids'] = $guarantorId;
            $guarantor['fullName'] = $requisite['RQ_LAST_NAME'] . " " . $requisite['RQ_FIRST_NAME'] . " " .$requisite['RQ_SECOND_NAME'];
            $regData = self::_getRegistrationDataById($guarantor, false);
        }
        if($requisite['PRESET_ID'] == 1){
            $guarantor['not'] = '';
            $guarantor['ids'] = $guarantorId;
            $guarantor['fullName'] = $requisite['RQ_COMPANY_FULL_NAME'];
            $regData = self::_getRegistrationDataById($guarantor, true);
        }

        if ($regData['errorFields'])
            return $regData;


        $data = [];
        foreach (self::INDIVIDUAL_FIELDS as $field => $fieldValue) {
            if($fieldValue == 'UF_CRM_1684493639') {
                $requisite[$fieldValue] = date("d.m.Y", strtotime($requisite[$fieldValue]));
            }
            $data[$guarantorId][$field] = $requisite[$fieldValue] ?? '';
        }

        $consentDate = self::getConsentDate($guarantorId, $guarantorType);
        if (is_array($consentDate) && $consentDate['errorFields'])
            return $consentDate;

        $data[$guarantorId]['creditAmount'] = 0;
        $data[$guarantorId]['bkiReportConsentDate'] = $consentDate;
        $data[$guarantorId]['bkiReportDealDate'] = $consentDate;
        $data[$guarantorId]['bkiReportBirthPlaceCode'] = '643';
        $data[$guarantorId]['bkiReportStateCode'] = '643';
        $data[$guarantorId]['guarantorType'] = $guarantorType;
        $data[$guarantorId]['nbkiReportConsentPeriod'] = $nbkiReportConsentPeriod;

        foreach ($data as $i => $p)
        {
            $result = array_merge($p, $regData[$i]);
        }

        return $result;
    }
    private static function getConsentDate(int $guarantorId, $guarantorType)
    {

        $factory = Container::getInstance()->getFactory($guarantorType);
        $item = $factory->getItem($guarantorId);
        $guarantor = $item->getData();

        if($guarantorType === \CCrmOwnerType::Contact) {
            $guarantorConsentDate = $guarantor[self::CONSENT_DATE_FIELDS['contact']];
        } else {
            $guarantorConsentDate = $guarantor[self::CONSENT_DATE_FIELDS['company']];
        }

        if (empty($guarantorConsentDate)) {
            return ['errorFields' => "($guarantorId) Нет даты согласия !"];
        }
        return self::formatDate($guarantorConsentDate, "d.m.Y");
    }
    private static function _getRequisiteData(int $guarantorId, string $guarantorTypeName): ?array
    {
        $requisite = new EntityRequisite();
        $requisites = $requisite->getList([
            'filter' => [
                '=ENTITY_TYPE_ID' => $guarantorTypeName === 'CONTACT' ? \CCrmOwnerType::Contact : \CCrmOwnerType::Company,
                '=ENTITY_ID' => $guarantorId,
            ],
            'select' => ['*','UF_*']
        ])->fetchAll();

        if (empty($requisites)) {
            return null;
        }

        if($guarantorTypeName === 'COMPANY') {
            $factory = Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $item = $factory->getItem($guarantorId);
            if ($item) {
                $itemData = $item->getData();
                $requisites[0]['clientType'] = $itemData['UF_CRM_1684145100226'];

                Logs\File::AddMessage("requisites: ".print_r($requisites[0],true), "_getRequisiteData",LOG_BP);
                return $requisites[0];
            }
            return null;
        } else {
            return $requisites[0];
        }

    }
    public static function createGuarantorRequest(
        $jsonData,
        $objectData,
        $timeData,
        $point = "",
        $legalEntityInn = null,
        $emulation = false
    ) {

        //$url = "https://api.seller-capital.ru/Guarantor/AddOrUpdatePerson?legalEntityInn={$legalEntityInn}";
        //$url = "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";

        $url = strpos($_SERVER['HTTP_HOST'], 'test') ? "https://internal.dev.seller-capital.ru/Bitrix/AddOrUpdateGuarantors" : "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";

        // Логируем запрос с дополнительным контекстом
        Logs\File::AddMessage([
            'url' => $url,
            'jsonData' => $jsonData,
            'objectData' => $objectData,
            'timeData' => $timeData,
            'point' => $point,
            'emulation' => $emulation
        ], "createGuarantorRequest", LOG_BP);

        // Используем класс KPLab\ApiRequest для отправки запроса
        if (defined("TOKEN_KEY")) {
            // Формируем заголовки запроса
            $headersRequest = [
                "key" => TOKEN_KEY,
                "Content-Type" => "application/json"
            ];

            // Формируем объект для логирования
            $logData = [
                'objectData' => $objectData,
                'methodName' => __FUNCTION__,
                'controllerName' => static::class,
                'method' => 'POST',
                'timeData' => $timeData,
                'point' => $point,
            ];

            $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $headersRequest, $jsonData, $logData);
            return $jsonResponse;
        }

        /*if(defined("TOKEN_KEY")) {
            $jsonResponse = \KPLab\Curl::post_v2(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);
            return $jsonResponse;
        }
        */
        return null;
    }
    public static function AddOrUpdateGuarantorsRequest(
        $jsonData,
        $objectData,
        $timeData,
        $point = "",
        $legalEntityInn = null,
        $emulation = false
    ) {

        // Новый URL API
        //$url = "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";
        $url = strpos($_SERVER['HTTP_HOST'], 'test') ? "https://internal.dev.seller-capital.ru/Bitrix/AddOrUpdateGuarantors" : "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";
        // Логируем запрос с дополнительным контекстом
        Logs\File::AddMessage([
            'url' => $url,
            'jsonData' => $jsonData,
            'objectData' => $objectData,
            'timeData' => $timeData,
            'point' => $point,
            'emulation' => $emulation
        ], "createGuarantorRequest", LOG_BP);

        // Используем класс KPLab\ApiRequest для отправки запроса
        if (defined("TOKEN_KEY")) {
            // Формируем заголовки запроса
            $headersRequest = [
                "key" => TOKEN_KEY,
                "Content-Type" => "application/json"
            ];

            // Формируем объект для логирования
            $logData = [
                'objectData' => $objectData,
                'methodName' => __FUNCTION__,
                'controllerName' => static::class,
                'method' => 'POST',
                'timeData' => $timeData,
                'point' => $point,
            ];

            $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $headersRequest, $jsonData, $logData);
            return $jsonResponse;
        }

        return null;
    }
    //endregion

    private static function moveToStage($entityTypeId, $elementID, $stageId): void
    {
        // Получаем фабрику для работы с элементами указанного типа
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

        if (!$factory) {
            throw new \Exception("Фабрика не найдена для entityTypeId: {$entityTypeId}");
        }

        // Получаем элемент по его ID
        $item = $factory->getItem($elementID);

        if ($item) {
            // Устанавливаем новую стадию
            $item->setStageId($stageId);

            // Сохраняем изменения
            $operation = $factory->getUpdateOperation($item);
            $operation->disableCheckAccess()->enableCheckWorkflows()->enableCheckRequiredUserFields()->enableAfterSaveActions()->enableBizProc()->enableAutomation();
            $result = $operation->launch();

            // Логируем результат
            Logs\File::AddMessage($result->isSuccess() ? 'Stage updated successfully' : $result->getErrorMessages(), "moveToStageWithFactory result", LOG_BP);

            // Проверка успешности перемещения
            if (!$result->isSuccess()) {
                Logs\File::AddMessage($result->getErrorMessages(), "moveToStageWithFactory error", LOG_BP);
            }
        } else {
            Logs\File::AddMessage("Item not found", "moveToStageWithFactory error", LOG_BP);
        }
    }
}
