<?php
namespace KPLab\API\V2\Service;

use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use Bitrix\Crm\Service\Container;
use KPLab\CRM\AddressTable;
use KPLab\Logs;
use Bitrix\Location\Entity\Address as EntityAddress;

define("LOG_PERSON_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_person.log");
class PersonService
{
    private string $personInn;
    public string $personId;
    public ?string $rqId;
    public function __construct(string $personInn) {
        $this->personInn = $personInn;
    }
    public function find(): int
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
        foreach ($itemsCompany as $itemCompany)
        {
            $this->personId = $itemCompany->getId();
            return $this->personId;
        }
        return 0;
    }

    public function add(\KPLab\API\V2\DTO\PersonDTO $personDTO): static
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $newItem = $factoryCompany->createItem();
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

        $newItem->set("UF_CRM_1684145100226", $TypeId);
        $newItem->set("UF_CRM_COMPANY_SS_ORG", [5]);
        $newItem->set("UF_CRM_6433DBB98DD53", 17611);
        $newItem->set("UF_CRM_6433D7C925893", $personDTO->inn);
        $newItem->set("UF_CRM_COMPANY_SS_AM_ID", $personDTO->guid);

        $fullName = $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName;

        // Сохранение новой карточки
        $operation = $factoryCompany->getAddOperation($newItem);
        $operation->disableAllChecks();
        $operation->launch();

        $newItem->setTitle($fullName);
        $newItem->save();

        $this->personId = $newItem->getId();
        $this->createRequisite($this->personId, $personDTO);
        $this->processAddressRequisites($this->rqId, $this->personId, $personDTO);

        foreach($personDTO->contactDetails as $contactDetail){
            $TYPE_ID = "";
            $VALUE_TYPE = "";

            if($contactDetail['valueType'] == 1) $VALUE_TYPE = 'MOBILE';
            if($contactDetail['valueType'] == 2) $VALUE_TYPE = 'WORK';
            if($contactDetail['valueType'] == 3) $VALUE_TYPE = 'HOME';
            if($contactDetail['typeId'] == 1) $TYPE_ID = 'PHONE';
            if($contactDetail['typeId'] == 2) $TYPE_ID = 'EMAIL';

            $ar = [
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $this->personId,
                'TYPE_ID' => $TYPE_ID,
                'VALUE_TYPE' => $VALUE_TYPE,
                'VALUE' => $contactDetail['valueText']
            ];
            $multi = new \CCrmFieldMulti();
            $multi->Add($ar);
        }

        \KPLab\OneC\ContactPersons::getDetails($this->personId, $personDTO->contactPersonDetails,"CO_");

        return $this;

    }
    public function getId(): string
    {
        return $this->personId;
    }

    public function update($personId, \KPLab\API\V2\DTO\PersonDTO $personDTO): static
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $item = $factoryCompany->getItem($personId);
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

        $item->set("UF_CRM_1684145100226", $TypeId);
        $item->set("UF_CRM_COMPANY_SS_ORG", [5]);
        $item->set("UF_CRM_6433DBB98DD53", 17611);
        $item->set("UF_CRM_6433D7C925893", $personDTO->inn);
        $item->set("UF_CRM_COMPANY_SS_AM_ID", $personDTO->guid);

        $fullName = $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName;
        $item->setTitle($fullName);

        // Сохранение новой карточки
        $item->save();
        $operation = $factoryCompany->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operation->launch();

        $this->findCompanyRQ($this->personId, $personDTO->inn);
        if($this->rqId == 0) {
            $this->createRequisite($this->personId, $personDTO);
        }
        else {
            $this->updateRequisite($this->rqId, $personDTO);
        }
        $this->processAddressRequisites($this->rqId, $this->personId, $personDTO);
        foreach($personDTO->contactDetails as $contactDetail){
            $TYPE_ID = "";
            $VALUE_TYPE = "";

            if($contactDetail['valueType'] == 1) $VALUE_TYPE = 'MOBILE';
            if($contactDetail['valueType'] == 2) $VALUE_TYPE = 'WORK';
            if($contactDetail['valueType'] == 3) $VALUE_TYPE = 'HOME';
            if($contactDetail['typeId'] == 1) $TYPE_ID = 'PHONE';
            if($contactDetail['typeId'] == 2) $TYPE_ID = 'EMAIL';

            $ar = [
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $this->personId,
                'TYPE_ID' => $TYPE_ID,
                'VALUE_TYPE' => $VALUE_TYPE,
                'VALUE' => $contactDetail['valueText']
            ];
            $multi = new \CCrmFieldMulti();
            $multi->Add($ar);
        }
        \KPLab\OneC\ContactPersons::getDetails($this->personId, $personDTO->contactPersonDetails,"CO_");
        return $this;
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
    private function createRequisite($personId, \KPLab\API\V2\DTO\PersonDTO $personDTO) : void
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
    private function updateRequisite($rqId, \KPLab\API\V2\DTO\PersonDTO $personDTO): void
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
        \B24Rest::call('crm.requisite.update', $params);
    }
    private function processAddressRequisites(mixed $rqId, int $cardId, \KPLab\API\V2\DTO\PersonDTO $personDTO): void
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
}