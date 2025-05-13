<?php namespace KPLab\API\V2\Service;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use Bitrix\Crm\Service\Container;
use KPLab\CRM\AddressTable;
use KPLab\Logs;
use Bitrix\Location\Entity\Address as EntityAddress;
use KPLab\API\V2\Helpers\Locker;
use KPLab\API\V2\DTO\LegalDTO;

define("LOG_LEGAL_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_legal.log");

class LegalService
{

    private string $legalInn;
    public string $legalId;
    public ?string $rqId;
    public ?string $lockerId;
    private Locker $locker;

    public function __construct(string $legalInn) {
        $this->legalInn = $legalInn;
        $this->locker = new Locker();
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws \Exception
     */
    public function add(LegalDTO $legalDTO): static
    {
        $lockIdentifier = 'company_inn_' . $this->legalInn;

        // Блокируем по ИНН (до создания компании)
        if (!$this->locker->acquire($lockIdentifier, 2)) { // Ждём до 2 секунд
            $this->locker->errorLog($lockIdentifier. ' | Не удалось получить блокировку: операция уже выполняется');
        }

        try {
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $this->legalId = $this->createCompany($factoryCompany, $legalDTO);
            $this->processRequisites($legalDTO);
            $this->processContacts($legalDTO);
            $this->processContactPersons($legalDTO);

        } finally {
            // 5. Гарантированное освобождение текущей блокировки
            $this->locker->release($lockIdentifier);
            $this->locker->log("Файл блокировки удален для: {$lockIdentifier}");

            return $this;
        }
    }


    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function update($legalId, LegalDTO $legalDTO): static
    {
        $lockIdentifier = 'company_inn_' . $this->legalInn;

        // Блокируем по ID (при обновлении компании)
        if (!$this->locker->acquire($lockIdentifier, 2)) { // Ждём до 2 секунд
            $this->locker->errorLog($lockIdentifier. ' | Не удалось получить блокировку: операция уже выполняется');
        }

        try {
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $company = $this->updateCompany($legalId, $factoryCompany, $legalDTO);

            // Обновление карточки
            $operation = $factoryCompany->getUpdateOperation($company);
            $operation->disableAllChecks();
            $operation->launch();


            $this->processRequisites($legalDTO);
            $this->processContacts($legalDTO);
            $this->processContactPersons($legalDTO);

        }
        finally {
            // 4. Гарантированное освобождение блокировки
            $this->locker->release($lockIdentifier);
            return $this;
        }
    }
    public function getId(): int
    {
        return $this->legalId;
    }
    public function find(): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $params = [
            'filter' => [
                'UF_CRM_6433D7C925893' => $this->legalInn,
            ],
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
        $itemsCompany = $factoryCompany -> getItems($params);
        foreach ($itemsCompany as $itemCompany)
        {
            $this->legalId = $itemCompany->getId();
        }
        $this->legalId = 0;
    }

    private function findCompanyRQ($legalId, $legalInn): void
    {
        if(!is_null($legalInn)) {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $legalId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "RQ_INN" => $legalInn],
                'select' => ['*','UF_*']
            ]);
        }
        else {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $legalId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
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
    private function createRequisite($legalId, LegalDTO $legalDTO) : void
    {
        $type = 'ORG';
        $PRESET_ID = 1;
        $params = [
            "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
            "ENTITY_ID" => $legalId,
            "PRESET_ID" => $PRESET_ID,
            'TITLE' => $legalDTO->shortName,
            'NAME' => $legalDTO->shortName,
            'RQ_INN' => $legalDTO->inn,
            'RQ_KPP' => $legalDTO->kpp,
            'RQ_OGRN' => $legalDTO->regNum,
            'RQ_OKPO' => $legalDTO->okpo,
            'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($legalDTO->regDate)),
            'UF_CRM_1688964741' => $legalDTO->regNumOrg,
            'RQ_COMPANY_NAME' => $legalDTO->shortName,
            'RQ_COMPANY_FULL_NAME' => $legalDTO->fullName
        ];

        Logs\File::AddMessage($params,"ORG params RequisiteTable ADD", LOG_LEGAL_SERVICE);

        // Создаём реквизит через REST API и получаем его идентификатор
        $rqResponse = \Bitrix\Crm\RequisiteTable::add($params);

        //Logs\File::AddMessage($rqResponse,"ORG RequisiteTable ADD", LOG_LEGAL_SERVICE);

        //$rqResponse = \B24Rest::call('crm.requisite.add', $params);
        $this->rqId = $rqResponse->getId();
    }
    private function updateRequisite($rqId, LegalDTO $legalDTO): void
    {
        $params = [
            'TITLE' => $legalDTO->shortName,
            'NAME' => $legalDTO->shortName,
            'RQ_INN' => $legalDTO->inn,
            'RQ_KPP' => $legalDTO->kpp,
            'RQ_OGRN' => $legalDTO->regNum,
            'RQ_OKPO' => $legalDTO->okpo,
            'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($legalDTO->regDate)),
            'UF_CRM_1688964741' => $legalDTO->regNumOrg,
            'RQ_COMPANY_NAME' => $legalDTO->shortName,
            'RQ_COMPANY_FULL_NAME' => $legalDTO->fullName
        ];


        $rqResponse = \Bitrix\Crm\RequisiteTable::update($rqId, $params);
    }
    private function processAddressRequisites(mixed $rqId, int $cardId, LegalDTO $legalDTO): void
    {
        $dataArray = $legalDTO->toArray();
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
                        $addressTypeId = 6;
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

    private function processRequisites(LegalDTO $legalDTO): void
    {
        $this->findCompanyRQ($this->legalId, $legalDTO->inn);

        if ($this->rqId == 0) {
            $this->createRequisite($this->legalId, $legalDTO);
        } else {
            $this->updateRequisite($this->rqId, $legalDTO);
        }

        $this->processAddressRequisites($this->rqId, $this->legalId, $legalDTO);
    }
    private function processContacts(LegalDTO $legalDTO): void
    {
        foreach ($legalDTO->contactDetails as $contact) {
            $typeMap = [1 => 'PHONE', 2 => 'EMAIL'];
            $valueMap = [1 => 'MOBILE', 2 => 'WORK', 3 => 'HOME'];

            $multi = new \CCrmFieldMulti();
            $multi->Add([
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $this->legalId,
                'TYPE_ID' => $typeMap[$contact['typeId']] ?? '',
                'VALUE_TYPE' => $valueMap[$contact['valueType']] ?? '',
                'VALUE' => $contact['valueText']
            ]);
        }
    }
    private function processContactPersons(LegalDTO $legalDTO): void
    {
        \KPLab\OneC\ContactPersons::getDetails(
            $this->legalId,
            $legalDTO->contactPersonDetails,
            "CO_"
        );
    }

    private function createCompany($factoryCompany, LegalDTO $legalDTO) {
        $company = $factoryCompany->createItem();
        $company->setTitle($legalDTO->shortName);
        $TypeId = null;
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()){
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => 'ORG']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }
        $company->set("UF_CRM_1684145100226", $TypeId);
        $company->set("UF_CRM_COMPANY_SS_ORG", [5]);
        $company->set("UF_CRM_6433DBB98DD53", 17611);
        $company->set("UF_CRM_6433D7C925893", $legalDTO->inn);
        $company->set("UF_CRM_COMPANY_SS_AM_ID", $legalDTO->guid);

        // Сохранение новой карточки
        $operation = $factoryCompany->getAddOperation($company);
        $operation->disableAllChecks();
        $operation->launch();

        return $company->getId();
    }

    private function updateCompany($legalId, $factoryCompany, LegalDTO $legalDTO) {
        $company = $factoryCompany->getItem($legalId);
        $TypeId = null;
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()) {
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => 'ORG']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }

        $company->set("UF_CRM_1684145100226", $TypeId);
        $company->set("UF_CRM_COMPANY_SS_ORG", [5]);
        $company->set("UF_CRM_6433DBB98DD53", 17611);
        $company->set("UF_CRM_6433D7C925893", $legalDTO->inn);
        $company->set("UF_CRM_COMPANY_SS_AM_ID", $legalDTO->guid);

        $company->setTitle($legalDTO->shortName);

        return $company;
    }

}