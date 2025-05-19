<?php namespace KPLab\API\V2\Model\Service;

use Bitrix\Location\Entity\Address as EntityAddress;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\CRM\AddressTable;
use KPLab\Logs;

define("LOG_LEGAL_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_legal.log");

class LegalService
{

    private string $legalInn;
    public int $legalId;
    public string $partnerName;
    public array $beneficialOwners;
    public ?string $rqId;
    public string $representative;
    public ?string $lockerId;
    //private Locker $locker;

    public function __construct(string $legalInn) {
        $this->legalInn = $legalInn;
        //$this->locker = new Locker();
    }

    public function add(LegalDTO $legalDTO): static
    {
        try {
            ChangeContext::setSource($this->partnerName);

            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $this->getRepresentative($legalDTO->representativeGuid);
            $this->createCompany($factoryCompany, $legalDTO);

            $this->processRequisites($legalDTO);
            $this->processContactPersons($legalDTO);
            $this->processBeneficialOwners($legalDTO);

        } finally {
            ChangeContext::clear();
            return $this;
        }
    }

    public function update($legalId, LegalDTO $legalDTO): static
    {
        try {
            // Устанавливаем контекст перед созданием компании
            ChangeContext::setSource($this->partnerName);

            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $this->getRepresentative($legalDTO->representativeGuid);
            $this->updateCompany($legalId, $factoryCompany, $legalDTO);

            $this->processRequisites($legalDTO);
            $this->processContactPersons($legalDTO);
            $this->processBeneficialOwners($legalDTO);

        }
        finally {
            ChangeContext::clear();
            return $this;
        }
    }
    public function getId(): int
    {
        return $this->legalId;
    }

    public function setPartnerName($value): void
    {
        $this->partnerName = $value;
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
        if($itemsCompany) {
            foreach ($itemsCompany as $itemCompany)
            {
                $this->legalId = $itemCompany->getId();
            }
        } else {
            $this->legalId = 0;
        }

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
    private function getIdBeneficialOwners($legalId, $beneficialOwnersDetails): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $this->beneficialOwners = []; // Инициализируем массив

        foreach ($beneficialOwnersDetails as $beneficialOwnersDetail) {
            $params = [
                'filter' => [
                    'UF_CRM_COMPANY_SS_AM_ID' => $beneficialOwnersDetail['guid'], //Идентификатор МКК из 1С
                ],
                'select' => ['ID'],
                'limit' => 1,
            ];
            $companies = $factoryCompany->getItems($params);
            if($companies) {
                foreach($companies as $company) {
                    $this->beneficialOwners[] = [
                        'id'=>'CO_'.$company->getId(),
                        'prop' => $beneficialOwnersDetail['proportion']
                    ];
                }
            } else {
                $this->beneficialOwners[] = [
                    'id'=> null,
                    'prop' => null
                ];
                $message = "[b]Ошибка при получении данных[/b]\nБенефициар {$beneficialOwnersDetail['guid']} не существует в Битрикс\nСначала засинхронизируйте,\nпрежде чем будет доступен для приемки!";
                \local\B24Rest::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $legalId,
                        "ENTITY_TYPE" => "COMPANY",
                        "COMMENT" => "{$message}"
                    ]
                ]);
            }
        }
    }
    private function setBeneficialOwners($legalId, $beneficialOwnersDetails) : void
    {

        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $this->getIdBeneficialOwners($legalId, $beneficialOwnersDetails);
        $company = $factoryCompany->getItem($legalId);

        $mapUFbeneficialOwners = [
            'UF_CRM_1702272911' => 'UF_CRM_1702274893', //owner 1 => proportion 1
            'UF_CRM_1702272991' => 'UF_CRM_1702274922', //owner 2 => proportion 2
            'UF_CRM_1702273016' => 'UF_CRM_1702274951', //owner 3 => proportion 3
            'UF_CRM_1702273043' => 'UF_CRM_1702274976', //owner 4 => proportion 4
            'UF_CRM_1702273072' => 'UF_CRM_1702275014', //owner 4 => proportion 4
        ];

        // Сначала очищаем все поля
        foreach ($mapUFbeneficialOwners as $ownerField => $propField) {
            $company->set($ownerField, null);
            $company->set($propField, null);
        }

        // Заполняем поля по порядку, но не больше чем есть полей
        $fieldIndex = 0;
        foreach ($this->beneficialOwners as $beneficialOwner) {
            if ($fieldIndex >= count($mapUFbeneficialOwners)) {
                break; // Не больше чем зарезервировано полей
            }

            // Получаем пару полей по текущему индексу
            $fields = array_slice($mapUFbeneficialOwners, $fieldIndex, 1);
            $ownerField = key($fields);
            $propField = current($fields);

            $company->set($ownerField, $beneficialOwner['id']);
            $company->set($propField, $beneficialOwner['prop']);

            $fieldIndex++;
        }
        // Обновление карточки
        $operation = $factoryCompany->getUpdateOperation($company);
        $operation->disableAllChecks();
        $operation->launch();
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
    private function processBankDetails(mixed $rqId, int $cardId, LegalDTO $legalDTO): void
    {
        $dataArray = $legalDTO->toArray();
    }
    private function processAddressRequisites(mixed $rqId, int $cardId, LegalDTO $legalDTO): void
    {
        $dataArray = $legalDTO->toArray();
        if (!isset($rqId) || !isset($legalDTO->addressDetails) || !is_array($legalDTO->addressDetails)) {
            return;
        }

        foreach ($legalDTO->addressDetails as $address) {
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
        $this->processBankDetails($this->rqId, $this->legalId, $legalDTO);
    }
    private function processBeneficialOwners(LegalDTO $legalDTO): void
    {
        $this->setBeneficialOwners($this->legalId, $legalDTO->beneficialOwnersDetails);
    }

    private function processContactPersons(LegalDTO $legalDTO): void
    {
        \KPLab\OneC\ContactPersons::getDetails(
            $this->legalId,
            $legalDTO->contactPersonDetails,
            "CO_"
        );
    }

    public function processCompanyAsync($legalId, array $legalArray): void
    {
        try {
            $legalDTO = LegalDTO::init($legalArray);
            $this->processRequisites($legalDTO);
            $this->processContacts($legalDTO);
            $this->processContactPersons($legalDTO);

        } catch (\Exception $e) {
            Logs\File::AddMessage("Ошибка обработки компании {$legalId}: " . $e->getMessage(),"company_background", LOG_LEGAL_SERVICE);
        }
    }

    private function getRepresentative($representativeGuid): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        if(!$representativeGuid) {
            $this->representative = null;
        } else {
            $params = [
                'filter' => [
                    'UF_CRM_COMPANY_SS_AM_ID' => $representativeGuid, //Идентификатор МКК из 1С
                ],
                'select' => ['ID'],
                'limit' => 1,
            ];
            $companies = $factoryCompany->getItems($params);
            if ($companies) {
                foreach ($companies as $company) {
                    $this->representative = 'CO_'.$company->getId();
                }
            } else {
                $this->representative = null;
            }
        }
    }

    private function createCompany($factoryCompany, LegalDTO $legalDTO): void
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
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => 'ORG']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }
        if($legalDTO->guid != "") {
            $fields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $legalDTO->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_COMPANY_SS_AM_ID" => $legalDTO->guid,
                "UF_CRM_1615200179" => $this->representative,
                "UF_CRM_1697107946" => $legalDTO->limitSum
            ];
        }
        else {
            $fields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $legalDTO->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_1615200179" => $this->representative,
                "UF_CRM_1697107946" => $legalDTO->limitSum
            ];
        }

        $company = $factoryCompany->createItem($fields);

        if($legalDTO->contactDetails) {
            // Получаем текущие контакты компании
            $fm = $company->getFm();
            // Обрабатываем новые контакты
            $fm = (new Tool)->processFM($legalDTO, $fm);
            // Сохраняем изменения
            $company->setFm($fm);
        }
        $company->setTitle($legalDTO->shortName);
        $company->save();

        // Сохранение новой карточки
        $operation = $factoryCompany->getAddOperation($company);
        $operation->disableAllChecks();
        $result = $operation->launch();

        if(!$result->isSuccess()) {
            Logs\File::AddMessage($result->getErrors(),"Ошибки создания компании (getErrors)", LOG_LEGAL_SERVICE);
            Logs\File::AddMessage($result->getErrorMessages(),"Ошибки создания компании (getErrorMessages)", LOG_LEGAL_SERVICE);
        }
        $this->legalId = $company->getId();
    }

    private function updateCompany($legalId, $factoryCompany, LegalDTO $legalDTO): void
    {
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
        $company->set("UF_CRM_1615200179", $this->representative);
        $company->set("UF_CRM_1697107946", $legalDTO->limitSum);

        if($legalDTO->contactDetails) {
            // Получаем текущие контакты компании
            $fm = $company->getFm();
            // Обрабатываем новые контакты
            $fm = (new Tool)->processFM($legalDTO, $fm);
            // Сохраняем изменения
            $company->setFm($fm);
        }

        $company->setTitle($legalDTO->shortName);

        // Обновление карточки
        $operation = $factoryCompany->getUpdateOperation($company);
        $operation->disableAllChecks();
        $operation->launch();
    }

}