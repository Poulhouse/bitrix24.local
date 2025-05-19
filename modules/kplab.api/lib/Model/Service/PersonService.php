<?php
namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\Multifield\Collection;
use Bitrix\Crm\Multifield\Type\Email;
use Bitrix\Crm\Multifield\Type\Phone;
use Bitrix\Crm\Multifield\Value;
use Bitrix\Location\Entity\Address as EntityAddress;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\API\V2\Model\DTO\PersonDTO;
use KPLab\CRM\AddressTable;
use KPLab\Logs;

define("LOG_PERSON_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_person.log");
class PersonService
{
    private string $personInn;
    public int $personId;
    public string $partnerName;
    public ?string $rqId;
    public function __construct(string $personInn) {
        $this->personInn = $personInn;
    }

    public function find(): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $params = [
            'filter' => [
                'UF_CRM_6433D7C925893' => $this->personInn,
            ],
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
        $itemsCompany = $factoryCompany -> getItems($params);
        if($itemsCompany) {
            foreach ($itemsCompany as $itemCompany)
            {
                $this->personId = $itemCompany->getId();
            }
        } else {
            $this->personId = 0;
        }
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws \Exception
     */
    public function add(PersonDTO $personDTO): static
    {
        try {
            // Устанавливаем контекст перед созданием компании
            ChangeContext::setSource($this->partnerName);

            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $this->createCompany($factoryCompany, $personDTO);

            $this->processRequisites($personDTO);
            $this->processContactPersons($personDTO);

        } finally {
            ChangeContext::clear();
            return $this;
        }
    }
    public function getId(): string
    {
        return $this->personId;
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function update($personId, PersonDTO $personDTO): static
    {
        try {
            // Устанавливаем контекст перед созданием компании
            ChangeContext::setSource($this->partnerName);
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $this->updateCompany($personId, $factoryCompany, $personDTO);

            $this->processRequisites($personDTO);
            $this->processContactPersons($personDTO);

        }
        finally {
            ChangeContext::clear();
            return $this;
        }
    }

    public function setPartnerName($value): void
    {
        $this->partnerName = $value;
    }

    private function createCompany($factoryCompany, PersonDTO $personDTO): void
    {

        $TypeId = null;
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()){
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => ($personDTO->regMark == "0") ? 'FL' : 'IP']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }
        if($personDTO->guid != "") {
            $fields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $personDTO->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_COMPANY_SS_AM_ID" => $personDTO->guid,
                "UF_CRM_1697107946" => $personDTO->limitSum
            ];
        }
        else {
            $fields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $personDTO->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_1697107946" => $personDTO->limitSum
            ];
        }

        $company = $factoryCompany->createItem($fields);
        $fullName = $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName;
        $company->setTitle($fullName);

        if($personDTO->contactDetails) {
            // Получаем текущие контакты компании
            $fm = $company->getFm();
            // Обрабатываем новые контакты
            $fm = (new Tool)->processFM($personDTO, $fm);
            // Сохраняем изменения
            $company->setFm($fm);
        }

        // Сохранение новой карточки
        $operation = $factoryCompany->getAddOperation($company);
        $operation->disableAllChecks();
        $operation->launch();

        $this->personId = $company->getId();
    }
    private function updateCompany($personId, $factoryCompany, PersonDTO $personDTO): void
    {
        $company = $factoryCompany->getItem($personId);
        $TypeId = null;
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()) {
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => ($personDTO->regMark == "0") ? 'FL' : 'IP']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }

        $company->set("UF_CRM_1684145100226", $TypeId);
        $company->set("UF_CRM_COMPANY_SS_ORG", [5]);
        $company->set("UF_CRM_6433DBB98DD53", 17611);
        $company->set("UF_CRM_6433D7C925893", $personDTO->inn);
        $company->set("UF_CRM_COMPANY_SS_AM_ID", $personDTO->guid);
        $company->set("UF_CRM_1697107946", $personDTO->limitSum);

        if($personDTO->contactDetails) {
            // Получаем текущие контакты компании
            $fm = $company->getFm();
            // Обрабатываем новые контакты
            $fm = (new Tool)->processFM($personDTO, $fm);
            // Сохраняем изменения
            $company->setFm($fm);
        }

        $fullName = $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName;
        $company->setTitle($fullName);

        // Обновление карточки
        $operation = $factoryCompany->getUpdateOperation($company);
        $operation->disableAllChecks();
        $operation->launch();
    }

    private function findCompanyRQ($personId, $personInn): void
    {
        if(!is_null($personInn)) {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $personId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,"RQ_INN" => $personInn],
                'select' => ['*','UF_*']
            ]);
        }
        else {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $personId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
                'select' => ['*','UF_*']
            ]);
        }
        $requisite = $requisiteResult->fetchAll();
        if(isset($requisite[0])) {
            $this->rqId = $requisite[0]['ID'];
        } else {
            $this->rqId = 0;
        }
    }
    private function mapDocType(int $identDoc): string
    {
        return match ($identDoc) {
            21 => 'Паспорт гражданина Российской Федерации',
            27 => 'Свидетельство о рождении гражданина Российской Федерации',
            31 => 'Иной документ',
        };
    }
    private function createRequisite($personId, PersonDTO $personDTO) : void
    {
        $type = ($personDTO->regMark == "0") ? 'FL' : 'IP';
        $docType = $this->mapDocType($personDTO->docType);
        $PRESET_ID = 2;
        $params = [
            "fields" => [
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
                "ENTITY_ID" => $personId,
                "PRESET_ID" => $PRESET_ID,
                'TITLE' => $type . " " . $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_FIRST_NAME' => $personDTO->firstName,
                'RQ_LAST_NAME' => $personDTO->lastName,
                'RQ_SECOND_NAME' => $personDTO->middleName,
                'RQ_IDENT_DOC' => $docType,
                'RQ_IDENT_DOC_SER' => $personDTO->docSeries,
                'RQ_IDENT_DOC_NUM' => $personDTO->docNumber,
                'RQ_IDENT_DOC_DATE' => $personDTO->docIssueDate,
                'RQ_IDENT_DOC_ISSUED_BY' => $personDTO->docIssuerText,
                'RQ_IDENT_DOC_DEP_CODE' => $personDTO->docDeptCode,
                'UF_CRM_1647929611' => $personDTO->birthPlace,
                'UF_CRM_1684493639' => $personDTO->birthDate,
                'RQ_INN' => $personDTO->inn,
                'RQ_OGRNIP' => $personDTO->regNum,
                'RQ_COMPANY_REG_DATE' => $personDTO->regDate,
                'UF_CRM_1688964741' => $personDTO->regNumOrg,
            ]
        ];

        Logs\File::AddMessage($params,"params RequisiteTable ADD", LOG_PERSON_SERVICE);

        // Создаём реквизит через REST API и получаем его идентификатор
        $rqResponse = \Bitrix\Crm\RequisiteTable::add($params);

        Logs\File::AddMessage($rqResponse,"RequisiteTable ADD", LOG_PERSON_SERVICE);

        //$rqResponse = \B24Rest::call('crm.requisite.add', $params);
        $this->rqId = $rqResponse['data'];
    }
    private function updateRequisite($rqId, PersonDTO $personDTO): void
    {
        $type = ($personDTO->regMark == "0") ? 'FL' : 'IP';
        $docType = $this->mapDocType($personDTO->docType);

        $params = [
            "id" => $rqId,
            "fields" => [
                'TITLE' => $type . " " . $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_FIRST_NAME' => $personDTO->firstName,
                'RQ_LAST_NAME' => $personDTO->lastName,
                'RQ_SECOND_NAME' => $personDTO->middleName,
                'RQ_IDENT_DOC' => $docType,
                'RQ_IDENT_DOC_SER' => $personDTO->docSeries,
                'RQ_IDENT_DOC_NUM' => $personDTO->docNumber,
                'RQ_IDENT_DOC_DATE' => $personDTO->docIssueDate,
                'RQ_IDENT_DOC_ISSUED_BY' => $personDTO->docIssuerText,
                'RQ_IDENT_DOC_DEP_CODE' => $personDTO->docDeptCode,
                'UF_CRM_1647929611' => $personDTO->birthPlace,
                'UF_CRM_1684493639' => $personDTO->birthDate,
                'RQ_INN' => $personDTO->inn,
                'RQ_OGRNIP' => $personDTO->regNum,
                'RQ_COMPANY_REG_DATE' => $personDTO->regDate,
                'UF_CRM_1688964741' => $personDTO->regNumOrg,
            ]
        ];

        // Выполняем обновление через REST API
        \local\B24Rest::call('crm.requisite.update', $params);
    }
    private function processBankDetails(mixed $rqId, int $cardId, PersonDTO $personDTO): void
    {
        $dataArray = $personDTO->toArray();
    }
    private function processAddressRequisites(mixed $rqId, int $cardId, PersonDTO $personDTO): void
    {
        $dataArray = $personDTO->toArray();
        if (!isset($rqId) || !isset($dataArray['addressDetails']) || !is_array($dataArray['addressDetails'])) {
            return;
        }

        foreach ($dataArray['addressDetails'] as $address) {
            if (!empty($address['fiasId'])) {
                $addressFiasId = $address['fiasId'];

                // Определяем тип адреса для дальнейшей обработки
                switch ($address['addressType']) {
                    case '1':
                        $addressTypeId = 4;
                        break;
                    case '2':
                        $addressTypeId = 1;
                        break;
                    default:
                        continue 2;
                }

                // Получаем данные адреса с помощью Dadata
                $http = new HttpClient();
                $http->setHeader('Content-Type', 'application/json');
                $http->setHeader('Accept', 'application/json');
                // Лучше вынести токен в конфигурацию
                $http->setHeader('Authorization', 'Token 440b60bed73f6e0d78a0eb09ca91971f8c079590');
                $requestBody = ['query' => $addressFiasId];
                $http->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address", json_encode($requestBody));
                $responseJson = $http->getResult();
                $responseArray = json_decode($responseJson, true);

                if (empty($responseArray['suggestions'][0]['data'])) {
                    continue;
                }

                $addressData = $responseArray['suggestions'][0]['data'];

                // Извлечение нужных полей
                $city      = $addressData['city']         ?? '';
                $flat      = $addressData['flat']         ?? '';
                $house     = $addressData['house']        ?? '';
                $region    = $addressData['region']       ?? '';
                $district  = $addressData['city_district']?? '';
                $street    = $addressData['street']       ?? '';
                $blockType = $addressData['block_type_full']?? '';
                $block     = $addressData['block']        ?? '';
                $country   = $addressData['country']      ?? '';
                $postalCode= $addressData['postal_code']   ?? '';

                // Если присутствуют дополнительные данные, объединяем информацию о доме
                if ($blockType === 'корпус' || $blockType === 'строение') {
                    $house .= ' ' . $blockType . ' ' . $block;
                }

                // Формируем поля для базового адреса
                $address1 = $street . ", " . $house;
                $address2 = $flat;

                $languageId = LANGUAGE_ID; // или другой нужный вам язык
                $locationAddress = new EntityAddress($languageId);

                // Устанавливаем необходимые поля.
                // Здесь можно использовать константы классов Bitrix\Location\Field\Type или использовать строки, если в вашей сборке так настроено.
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADDRESS_LINE_1, $address1);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADDRESS_LINE_2, $address2);
                $locationAddress->setFieldValue(EntityAddress\FieldType::LOCALITY, $city);
                $locationAddress->setFieldValue(EntityAddress\FieldType::POSTAL_CODE, $postalCode);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADM_LEVEL_1, $region);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADM_LEVEL_2, $district);
                $locationAddress->setFieldValue(EntityAddress\FieldType::COUNTRY, $country);
                $locationAddress->setFieldValue(EntityAddress\FieldType::STREET, $street);
                $locationAddress->setFieldValue(EntityAddress\FieldType::BUILDING, $house);
                $locationAddress->setFieldValue(EntityAddress\FieldType::FIAS_ID, $addressFiasId);

                // Если необходима дополнительная обработка (например, нормализация данных), можно добавить её здесь.
                // Сохраняем детальный адрес:
                $result = $locationAddress->save();
                if(!$result->isSuccess())
                {
                    // Обработка ошибки создания location address
                    Logs\File::AddMessage(implode(', ', $result->getErrorMessages()), "Не удалось создать детальный адрес", LOG_CRM_ADDRESS);
                }

                // Получаем идентификатор созданного location address:
                $locAddrId = $locationAddress->getId();

                // Формируем массив для записи с использованием расширенной ORM-модели
                $data = [
                    'TYPE_ID'        => $addressTypeId,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite, // Или другой тип, если требуется
                    'ENTITY_ID'      => intval($rqId),
                    'ANCHOR_ID'      => $cardId,
                    'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
                    'ADDRESS_1'      => $address1,
                    'ADDRESS_2'      => $address2,
                    'CITY'           => $city,
                    'POSTAL_CODE'    => $postalCode,
                    'REGION'         => $district,     // Можно поменять местами, если нужно
                    'PROVINCE'       => $region,
                    'COUNTRY'        => $country,
                    'LOC_ADDR_ID'    => $locAddrId,
                    // Дополнительные поля, которые сохранены в b_crm_addr:
                    'STREET'         => $street,
                    'BUILDING'       => $house,
                    'FIAS_ID'        => $addressFiasId,
                ];

                // Обновляем или создаем запись в CRM через AddressTable
                AddressTable::upsertExtended($data);

                // Логируем результат обработки для отладки
                Logs\File::AddMessage($data, "Обработка адресов для реквизита " . $address['addressType'], LOG_CRM_ADDRESS);
            }
            else {
                // Определяем тип адреса для дальнейшей обработки
                switch ($address['addressType']) {
                    case '1':
                        $addressTypeId = 4;
                        break;
                    case '2':
                        $addressTypeId = 1;
                        break;
                    default:
                        continue 2;
                }

                // Извлечение нужных полей
                $city      = $address['location']         ?? '';
                $flat      = $address['apart']         ?? '';
                $house     = $address['house']        ?? '';
                $region    = $address['province']       ?? '';
                $street    = $address['street']       ?? '';
                $block     = $address['block']        ?? '';
                $build     = $address['build']        ?? '';
                $country   = $address['country']      ?? 'Россия';
                $postalCode= $address['postCode']   ?? '';

                if($block) {
                    $blockType = 'корпус';
                    $house .= ' ' . $blockType . ' ' . $block;
                }
                if($build) {
                    $blockType = 'строение';
                    $house .= ' ' . $blockType . ' ' . $build;
                }

                $address1 = $street . ", " . $house;
                $address2 = $flat;

                $addressString = "{$region} {$city} {$street} {$house} {$flat}";

                $http = new HttpClient();
                $http->setHeader('Content-Type', 'application/json');
                $http->setHeader('Accept', 'application/json');
                // Лучше вынести токен в конфигурацию
                $http->setHeader('Authorization', 'Token 440b60bed73f6e0d78a0eb09ca91971f8c079590');
                $requestBody = ['query' => $addressString];
                $http->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address", json_encode($requestBody));
                $responseJson = $http->getResult();
                $responseArray = json_decode($responseJson, true);

                if (empty($responseArray['suggestions'][0]['data'])) {
                    continue;
                }

                $addressData = $responseArray['suggestions'][0]['data'];
                $addressFiasId = $addressData['fias_id'];

                $languageId = LANGUAGE_ID; // или другой нужный вам язык
                $locationAddress = new EntityAddress($languageId);

                // Устанавливаем необходимые поля.
                // Здесь можно использовать константы классов Bitrix\Location\Field\Type или использовать строки, если в вашей сборке так настроено.
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADDRESS_LINE_1, $address1);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADDRESS_LINE_2, $address2);
                $locationAddress->setFieldValue(EntityAddress\FieldType::LOCALITY, $city);
                $locationAddress->setFieldValue(EntityAddress\FieldType::POSTAL_CODE, $postalCode);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADM_LEVEL_1, $region);
                $locationAddress->setFieldValue(EntityAddress\FieldType::ADM_LEVEL_2, "");
                $locationAddress->setFieldValue(EntityAddress\FieldType::COUNTRY, $country);
                $locationAddress->setFieldValue(EntityAddress\FieldType::STREET, $street);
                $locationAddress->setFieldValue(EntityAddress\FieldType::BUILDING, $house);
                $locationAddress->setFieldValue(EntityAddress\FieldType::FIAS_ID, $addressFiasId);

                $locationAddress->save();

                // Получаем идентификатор созданного location address:
                $locAddrId = $locationAddress->getId();

                // Формируем массив для записи с использованием расширенной ORM-модели
                $data = [
                    'TYPE_ID'        => $addressTypeId,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite, // Или другой тип, если требуется
                    'ENTITY_ID'      => intval($rqId),
                    'ANCHOR_ID'      => $cardId,
                    'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
                    'ADDRESS_1'      => $address1,
                    'ADDRESS_2'      => $address2,
                    'CITY'           => $city,
                    'POSTAL_CODE'    => $postalCode,
                    'REGION'         => "",     // Можно поменять местами, если нужно
                    'PROVINCE'       => $region,
                    'COUNTRY'        => $country,
                    'LOC_ADDR_ID'    => $locAddrId,
                    // Дополнительные поля, которые сохранены в b_crm_addr:
                    'STREET'         => $street,
                    'BUILDING'       => $house,
                    'FIAS_ID'        => $addressFiasId,
                ];

                // Обновляем или создаем запись в CRM через AddressTable
                AddressTable::upsertExtended($data);
            }

        }
    }

    private function processRequisites(PersonDTO $personDTO): void
    {
        $this->findCompanyRQ($this->personId, $personDTO->inn);

        if ($this->rqId == 0) {
            $this->createRequisite($this->personId, $personDTO);
        } else {
            $this->updateRequisite($this->rqId, $personDTO);
        }

        $this->processAddressRequisites($this->rqId, $this->personId, $personDTO);
        $this->processBankDetails($this->rqId, $this->personId, $personDTO);
    }

    //region Обработка контактов компании
    private function processFM(PersonDTO $personDTO, Collection $existingCollection = null): Collection
    {
        $collection = $existingCollection ?? new Collection();

        foreach ($personDTO->contactDetails as $contact) {
            // Определяем тип поля
            switch ($contact['typeId']) {
                case 1:
                    $typeId = Phone::ID;
                    $valueType = $this->mapPhoneValueType($contact['valueType']);
                    break;
                case 2:
                    $typeId = Email::ID;
                    $valueType = $this->mapEmailValueType($contact['valueType']);
                    break;
                default:
                    continue 2; // Пропускаем неизвестные типы
            }

            // Создаем временный Value для проверки
            $tempValue = (new Value())
                ->setTypeId($typeId)
                ->setValueType($valueType)
                ->setValue($contact['valueText']);

            // 1. Проверяем полное совпадение
            if ($collection->has($tempValue)) {
                continue;
            }

            // 2. Проверяем дубли по значению (без учета типа)
            $existingValues = $this->findSimilarValues($collection, $typeId, $tempValue->getValue());

            if (!empty($existingValues)) {
                // Обновляем существующую запись
                $this->updateExistingValue($existingValues[0], $valueType);
                continue;
            }

            // 3. Добавляем как новое значение
            $collection->add($tempValue);


        }
        Logs\File::AddMessage($collection->toArray(), "fm processContacts", LOG_LEGAL_SERVICE);

        return $collection;
    }
    private function findSimilarValues(Collection $collection, string $typeId, string $value): array
    {
        $similar = [];
        foreach ($collection->filterByType($typeId) as $existingValue) {
            if ($typeId === Phone::ID) {
                // Для телефонов нормализуем перед сравнением
                if (normalizePhone($existingValue->getValue()) === normalizePhone($value)) {
                    $similar[] = $existingValue;
                }
            } else {
                // Для email и других типов сравниваем как есть (с точным соответствием)
                if ($existingValue->getValue() === $value) {
                    $similar[] = $existingValue;
                }
            }
        }
        return $similar;
    }
    private function updateExistingValue(Value $value, string $newValueType): void
    {
        // Обновляем только если тип изменился
        if ($value->getValueType() !== $newValueType) {
            $value->setValueType($newValueType);
        }
    }
    private function mapPhoneValueType(int $inputType): string
    {
        $map = [
            1 => Phone::VALUE_TYPE_MOBILE,
            2 => Phone::VALUE_TYPE_WORK,
            3 => Phone::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Phone::VALUE_TYPE_WORK;
    }
    private function mapEmailValueType(int $inputType): string
    {
        $map = [
            1 => Email::VALUE_TYPE_HOME,
            2 => Email::VALUE_TYPE_WORK,
            3 => Email::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Email::VALUE_TYPE_WORK;
    }
    //endregion

    private function processContactPersons(PersonDTO $personDTO): void
    {
        \KPLab\OneC\ContactPersons::getDetails(
            $this->personId,
            $personDTO->contactPersonDetails,
            "CO_"
        );
    }
}