<?php

namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\SystemException;
use KPLab\API\V2\HttpClientFactory;
use KPLab\API\V2\Infrastructure\Http\Auth\CustomAuthHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Interfaces\Http\Auth\AuthScheme;
use KPLab\CRM\AddressTable;
use Bitrix\Location;
use KPLab\Logs;
use Bitrix\Main;
use Throwable;

define("LOG_ADDRESS_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_address.log");

class AddressService
{
    public array $addressDetails;
    private DadataService $dadata;
    private Location\Service\AddressService $locationService;
    public ?string $rqId;
    public ?string $cardId;
    public string $typeDTO;
    protected const DA_DATA_TOKEN = '5b9fdc5d0fea8d0a58d94d34289b2551a31c2f75';
    public function __construct()
    {
        // Получаем синглтон Bitrix LocationService
        $this->locationService = Location\Service\AddressService::getInstance();

        $base = new BitrixHttpClientAdapter();
        $auth = new CustomAuthHttpClientDecorator(
            $base,
            new AuthScheme(self::DA_DATA_TOKEN, 'Token'),
            'Authorization'
        );
        $httpClient = HttpClientFactory::build($auth, logging: false);
        $this->dadata = new DadataService($httpClient);

        \Bitrix\Main\Loader::includeModule('location');
    }

    public function init(): void
    {
        foreach ($this->addressDetails as $address) {
            try {
                $addressTypeId = null;
                if($this->typeDTO == 'person') {
                    if ($address['addressType'] == 1) $addressTypeId = 4;
                    if ($address['addressType'] == 2) $addressTypeId = 1;
                }elseif($this->typeDTO == 'legal') {
                    if ($address['addressType'] == 1) $addressTypeId = 6;
                    if ($address['addressType'] == 2) $addressTypeId = 1;
                }

                if ($addressTypeId === null) {
                    continue;
                }
                if (!empty($address['fiasId'])) {
                    $fiasId = $address['fiasId'];
                    $addressData = $this->dadata->findByFiasId($fiasId);
                    if (!$addressData) {
                        Logs\File::AddMessage($fiasId, "Dadata не вернула данные по FIAS", LOG_ADDRESS_SERVICE);
                        continue;
                    }
                    $this->resolveAndSaveAddress($addressData, $fiasId, $addressTypeId, $this->rqId, $this->cardId, 'FIAS');
                }
                else {
                    $city   = $address['location'] ?? '';
                    $flat   = $address['apart'] ?? '';
                    $house  = $address['house'] ?? '';
                    $region = $address['province'] ?? '';
                    $street = $address['street'] ?? '';
                    $block  = $address['block'] ?? '';
                    $build  = $address['build'] ?? '';
                    $country = $address['country'] ?? 'Россия';
                    $postal = $address['postCode'] ?? '';

                    if ($block) {
                        $house .= ' корпус ' . $block;
                    }
                    if ($build) {
                        $house .= ' строение ' . $build;
                    }

                    $query = "{$region} {$city} {$street} {$house} {$flat}";
                    $addressData = $this->dadata->suggestAddressByString($query);
                    if (!$addressData) {
                        Logs\File::AddMessage($query, "Dadata не вернула данные по строке", LOG_ADDRESS_SERVICE);
                        continue;
                    }
                    $fiasId = $addressData['fias_id'];
                    $this->resolveAndSaveAddress($addressData, $fiasId, $addressTypeId, $this->rqId, $this->cardId, 'Строка');
                }
            }
            catch (Throwable $e) {
                Logs\File::AddMessage($e->getMessage(), "Ошибка в processAddressRequisites: {$address['addressType']}", LOG_ADDRESS_SERVICE);
                throw $e;
            }
        }
    }

    /**
     * Проходит по списку адресов и сохраняет их в Location + CRM
     */
    public function process(int $rqId, int $cardId, array $addresses): void
    {
        try {

            Logs\File::AddMessage($addresses, "адреса", LOG_ADDRESS_SERVICE);
            foreach ($addresses as $addr)
            {
                // 1. Определяем typeId
                $typeId = (int)($addr['addressType'] ?? 0);

                if ($typeId <= 0 && isset($addr['type'])) {
                    // символьный тип → addressTypeId
                    switch ($addr['type']) {
                        case 'registration':
                            $typeId = 4;
                            break;
                        case 'actual':
                            $typeId = 1;
                            break;
                        case 'legal':
                            $typeId = 6;
                            break;
                        default:
                            Logs\File::AddMessage($addr, "Неизвестный символьный тип адреса", LOG_ADDRESS_SERVICE);
                            break;
                    }
                }

                Logs\File::AddMessage($typeId, "Тип адреса", LOG_ADDRESS_SERVICE);

                if ($typeId <= 0) {
                    continue; // пропускаем если так и не определили
                }

                // 1) Забираем данные из Dadata
                $data = null;

                if (!empty($addr['fiasId']))
                {
                    $data = $this->dadata->findByFiasId($addr['fiasId']);
                }
                elseif (!empty($addr['query']))
                {
                    $data = $this->dadata->suggestAddressByString($addr['query']);
                }
                elseif (!empty($addr['province']) && !empty($addr['location']) && !empty($addr['street'])) {
                    $city   = $addr['location'];
                    $flat   = $addr['apart'];
                    $house  = $addr['house'];
                    $region = $addr['province'];
                    $street = $addr['street'];
                    $block  = $addr['block'];
                    $build  = $addr['build'];
                    $country = $addr['country'] ?? 'Россия';
                    $postal = $addr['postCode'];

                    if ($block) {
                        $house .= ' корпус ' . $block;
                    }
                    if ($build) {
                        $house .= ' строение ' . $build;
                    }

                    $query = "{$region} {$city} {$street} {$house} {$flat}";

                    Logs\File::AddMessage($query, "query адреса", LOG_ADDRESS_SERVICE);
                    $data = $this->dadata->suggestAddressByString($query);
                }
                else
                {
                    continue;
                }

                if (!is_array($data))
                {
                    Logs\File::AddMessage($addr, "Адрес не найден в Dadata", LOG_ADDRESS_SERVICE);
                    continue;
                }

                // 2) Сохраняем каждый адрес
                $this->saveOneAddress($rqId, $cardId, $typeId, $data);
            }
        }
        catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"Ошибка AddressService->process()", LOG_ADDRESS_SERVICE);
            throw $e;
        }
    }

    /**
     * Возвращает массив addressDetails в том же формате, что и CompanyService.
     */
    public function load(int $personId): array
    {
        try {
            $addressDetails = [];

            // 1) получаем «anchored» адреса через REST
            $res = \CRest::call('crm.address.list', [
                'filter' => [
                    'ANCHOR_ID'      => $personId,
                    'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                ],
                'select' => ['TYPE_ID','LOC_ADDR_ID']
            ])['result'] ?? [];

            $locator = new \Bitrix\Location\Controller\Address();

            foreach ($res as $item)
            {
                $typeId = (int)$item['TYPE_ID'];
                // маппим типы аналогично CompanyService
                switch ($typeId) {
                    case 1:
                        $addressType = 2;
                        break;
                    case 4:
                    case 6:
                        $addressType = 1;
                        break;
                    default:
                        Logs\File::AddMessage([
                            'typeId' => $typeId,
                            'item' => $item
                        ], "Необработанный TYPE_ID в loadAddressDetails()", LOG_ADDRESS_SERVICE);
                        continue 2;
                }

                $locAddrId = $item['LOC_ADDR_ID'];
                $found = $locator->findById($locAddrId);
                $postalCode = $found['fieldCollection'][50] ?? null; //почтовый индекс
                $country = $found['fieldCollection'][100] ?? null; //страна
                $region = $found['fieldCollection'][200] ?? null; // регион
                $area = $found['fieldCollection'][210] ?? null; // адм.район
                $city = $found['fieldCollection'][999] ?? null; // город
                $locality = $found['fieldCollection'][300] ?? null; // населенный пункт
                $street = $found['fieldCollection'][340] ?? null; // улица
                $house = $found['fieldCollection'][400] ?? null; // номер дома
                $stead = $found['fieldCollection'][502] ?? null; // Участок
                $blockK = $found['fieldCollection'][503] ?? null; // Корпус
                $blockS = $found['fieldCollection'][504] ?? null; // Строение
                $flat = $found['fieldCollection'][506] ?? null; //номер квартиры/помещения
                $other = $found['fieldCollection'][600] ?? null; //дополнительно
                $fias = $found['fieldCollection'][900] ?? null;
                $okato = $found['fieldCollection'][901] ?? null;
                if (!$fias) {
                    continue;
                }

                // вытягиваем из DaData
                $data = $this->dadata->findByFiasId($fias);
                if (!$data) {
                    continue;
                }

                $addressDetails[] = [
                    'addressType' => $addressType,
                    'postCode'      => $postalCode,
                    'countryCode'   => 643,
                    'locationCode'  => $okato,
                    'province'      => $region,
                    'area'          => $area,
                    'location'      => $city ?? ($locality ?? ''),
                    'street'        => $street,
                    'house'         => $house,
                    'block'         => $blockK,
                    'build'         => $blockS,
                    'apart'         => $flat,
                    'fiasId'      => $fias,
                ];



                if (empty($data)) {
                    throw new \RuntimeException("Не удалось найти в DaData");
                }

                // объединяем
                /*$addressDetails[] = array_merge(
                    ['addressType' => $addressType, 'fiasId' => $fias],
                    $this->mapAddressData($data)
                );*/
            }

            return $addressDetails;
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"Ошибка AddressService->load()", LOG_ADDRESS_SERVICE);
            throw $e;
        }

    }
    protected function saveOneAddress(int $rqId, int $cardId, int $typeId, array $data): void
    {
        // а) ищем старую CRM-запись
        $existing = AddressTable::getList([
            'filter' => [
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                'ENTITY_ID'      => $rqId,
                'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
                'ANCHOR_ID'      => $cardId,
                'TYPE_ID'        => $typeId,
            ],
            'select' => ['LOC_ADDR_ID'],
        ])->fetch();

        if (!empty($existing['LOC_ADDR_ID']))
        {
            // обновляем существующий Location-адрес
            $locAddr = $this->locationService->findById((int)$existing['LOC_ADDR_ID']);
        }
        else
        {
            // создаём новый
            $locAddr = new Location\Entity\Address(LANGUAGE_ID);
        }

        // б) очищаем старые и привязываем ссылки
        $locAddr->clearLinks();
        $locAddr->addLink(sprintf('%d.%d.%d', $typeId, \CCrmOwnerType::Company, $rqId),  'CRM_REQUISITE_ADDRESS');
        $locAddr->addLink(sprintf('%d.%d.%d', $typeId, \CCrmOwnerType::Company, $cardId), 'CRM_COMPANY_ADDRESS');

        // в) заполняем поля из Dadata
        $locAddr = $this->fillLocationAddress($locAddr, $data);

        // г) сохраняем через сервис
        $saveResult = $this->locationService->save($locAddr);
        if (!$saveResult->isSuccess())
        {
            Logs\File::AddMessage(
                $saveResult->getErrorMessages(),
                "LocationService::save вернул ошибки",
                LOG_ADDRESS_SERVICE
            );
            return;
        }

        $locAddrId = $locAddr->getId();

        // д) дублируем в CRM-таблицу и шлём событие
        $rqData      = $this->buildRqAddressData($rqId, $cardId, $typeId, $locAddrId, $data);
        $companyData = $this->buildCompanyAddressData($cardId, $typeId, $locAddrId, $data);

        AddressTable::upsertExtended($rqData);
        (new Main\Event('crm','OnAfterAddressRegister',['fields'=>$rqData]))->send();

        AddressTable::upsertExtended($companyData);
        (new Main\Event('crm','OnAfterAddressRegister',['fields'=>$companyData]))->send();
    }
    private function mapAddressData(array $dadata): array
    {
        return [
            'postCode'      => $dadata['postal_code'] ?? '',
            'countryCode'   => 643,
            'regStateNum'   => $dadata['kladr_id'] ?? '',
            'locationCode'  => $dadata['okato'] ?? '',
            'province'      => $dadata['region'] ?? '',
            'location'      => $dadata['city'] ?? ($dadata['settlement'] ?? ''),
            'street'        => $dadata['street'] ?? '',
            'house'         => $dadata['house'] ?? '',
            'block'         => ($dadata['block_type_full'] ?? '') === 'корпус' ? ($dadata['block'] ?? '') : '',
            'build'         => ($dadata['block_type_full'] ?? '') === 'строение' ? ($dadata['block'] ?? '') : '',
            'apart'         => $dadata['flat'] ?? '',
        ];
    }
    private function buildHouseField(array $data) {
        if ($data['block_type_full'] === 'корпус' || $data['block_type_full'] === 'строение') {
            $data['house'] .= ' ' . $data['block_type_full'] . ' ' . $data['block'];
        }
        return $data['house'];
    }
    private function buildAddressLineFields(array $data): array
    {
        $house = $this->buildHouseField($data);
        $addressLine1 = trim(($data['street'] ?? '') . ', ' . $house);
        $addressLine2 = $data['flat'] ?? '';
        return [$addressLine1, $addressLine2];
    }
    private function rebuildFields(array $data): array
    {
        // Собираем строку дома + корпуса/строения
        $house = $this->buildHouseField($data);

        // Формируем поля для базового адреса
        $addressLine1 = $this->buildAddressLineFields($data)[0];
        $addressLine2 = $this->buildAddressLineFields($data)[1];

        return [$house, $addressLine1, $addressLine2];
    }
    private function fillLocationAddress(Location\Entity\Address $locAddr, array $data, string $fiasId = ''): Location\Entity\Address
    {
        try {
            $house = $this->rebuildFields($data)[0];

            // Формируем поля для базового адреса
            $addressLine1 = $this->rebuildFields($data)[1];
            $addressLine2 = $this->rebuildFields($data)[2];

            $locAddr->setFieldValue(Location\Entity\Address\FieldType::ADDRESS_LINE_1, (string)$addressLine1);
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::ADDRESS_LINE_2, (string)$addressLine2);
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::LOCALITY, (string)$data['settlement'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::CITY, (string)$data['city'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::POSTAL_CODE, (string)$data['postal_code'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::ADM_LEVEL_1, (string)$data['region'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::ADM_LEVEL_2, (string)$data['city_district'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::COUNTRY, (string)$data['country'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::STREET, (string)$data['street'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::STEAD, (string)$data['stead'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::BLOCK_K, (string)$data['block'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::BLOCK_S, (string)$data['build'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::BUILDING, (string)$house);
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::FLAT, (string)$data['flat'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::ROOM, (string)$data['room'] ?? '');
            $locAddr->setFieldValue(Location\Entity\Address\FieldType::FIAS_ID, (string)$data['fias_id'] ?? '');
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в createLocationAddressFromData:", LOG_ADDRESS_SERVICE);
            throw $e;
        }
        return $locAddr;
    }
    protected function buildRqAddressData(int $rqId, int $cardId, int $typeId, int $locAddrId, array $data): array
    {
        return [
            'TYPE_ID'        => $typeId,
            'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
            'ENTITY_ID'      => $rqId,
            'ANCHOR_ID'      => $cardId,
            'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
            'ADDRESS_1'      => $this->rebuildFields($data)[1],
            'ADDRESS_2'      => $this->rebuildFields($data)[2],
            'CITY'           => $data['city'] ?? '',
            'LOCALITY'       => $data['settlement'] ?? '',
            'POSTAL_CODE'    => $data['postal_code'] ?? '',
            'REGION'         => $data['city_district'] ?? '',
            'PROVINCE'       => $data['region'] ?? '',
            'COUNTRY'        => $data['country'] ?? '',
            'LOC_ADDR_ID'    => $locAddrId,
            'STREET'         => $data['street'] ?? '',
            'BUILDING'       => $this->rebuildFields($data)[0],
            'STEAD'          => $data['stead'] ?? '',
            'BLOCK_K'        => $data['block'] ?? '',
            'BLOCK_S'        => $data['build'] ?? '',
            'FLAT'           => $data['flat'] ?? '',
            'ROOM'           => $data['room'] ?? '',
            'FIAS_ID'        => $data['fias_id'] ?? '',
        ];
    }
    protected function buildCompanyAddressData(int $cardId, int $typeId, int $locAddrId, array $data): array
    {
        return [
            'TYPE_ID'        => $typeId,
            'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
            'ENTITY_ID'      => $cardId,
            'ANCHOR_ID'      => $cardId,
            'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
            'ADDRESS_1'      => $this->rebuildFields($data)[1],
            'ADDRESS_2'      => $this->rebuildFields($data)[2],
            'CITY'           => $data['city'] ?? '',
            'LOCALITY'       => $data['settlement'] ?? '',
            'POSTAL_CODE'    => $data['postal_code'] ?? '',
            'REGION'         => $data['city_district'] ?? '',
            'PROVINCE'       => $data['region'] ?? '',
            'COUNTRY'        => $data['country'] ?? '',
            'LOC_ADDR_ID'    => $locAddrId,
            'STREET'         => $data['street'] ?? '',
            'BUILDING'       => $this->rebuildFields($data)[0],
            'STEAD'          => $data['stead'] ?? '',
            'BLOCK_K'        => $data['block'] ?? '',
            'BLOCK_S'        => $data['build'] ?? '',
            'FLAT'           => $data['flat'] ?? '',
            'ROOM'           => $data['room'] ?? '',
            'FIAS_ID'        => $data['fias_id'] ?? '',
        ];
    }
    private function resolveAndSaveAddress(array $addressData, string $fiasId, int $addressTypeId, int $rqId, int $cardId, string $logPrefix): void
    {
        $data = [];

        //region 1) Инициализируем AddressService
        try {
            $locationService = Location\Service\AddressService::getInstance();
        } catch (Throwable $e) {
            Logs\File::AddMessage(
                $e->getMessage(),
                "Не удалось получить LocationService ($logPrefix)",
                LOG_ADDRESS_SERVICE
            );
            throw $e;
        }
        //endregion

        //region 2) Ищем или создаём Address
        try {
            $existingCrm = AddressTable::getList([
                'filter' => [
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                    'ENTITY_ID'      => $rqId,
                    'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
                    'ANCHOR_ID'      => $cardId,
                    'TYPE_ID'        => $addressTypeId,
                ],
                'select' => ['LOC_ADDR_ID'],
            ])->fetch();

            if ($existingCrm && $existingCrm['LOC_ADDR_ID']) {
                // у этого конкретного TYPE_ID уже есть связанный location-адрес
                $locAddrId = $existingCrm['LOC_ADDR_ID'];
                $locationAddress = $locationService->findById($locAddrId);
            } else {
                // для этого TYPE_ID ещё нет записи — создаём новый объект
                $locationAddress = new \Bitrix\Location\Entity\Address(LANGUAGE_ID);
            }

            // очищаем любые ранее на добавленные ссылки
            $locationAddress->clearLinks();

            // Всегда привязываем к реквизиту и к компании.
            // Собираем «старый» формат для реквизитов и привязываем адрес
            $compositeRqId = sprintf('%d.%d.%d', $addressTypeId, \CCrmOwnerType::Company, $rqId);

            $locationAddress->addLink($compositeRqId, 'CRM_REQUISITE_ADDRESS');

            // Собираем «старый» формат для компании и привязываем адрес
            $compositeCompanyId = sprintf('%d.%d.%d', $addressTypeId, \CCrmOwnerType::Company, $cardId);
            $locationAddress->addLink($compositeCompanyId, 'CRM_COMPANY_ADDRESS');
        }
        catch (Throwable $e) {
            Logs\File::AddMessage(
                $e->getMessage(),
                "Ошибка при подготовке LocationAddress ($logPrefix)",
                LOG_ADDRESS_SERVICE
            );
            throw $e;
        }
        //endregion

        //region 3) Заполняем поля
        try {
            $locationAddress = $this->fillLocationAddress($locationAddress, $addressData, $fiasId);
        }
        catch (Throwable $e) {
            Logs\File::AddMessage(
                $e->getMessage(),
                "Ошибка при заполнении полей LocationAddress ($logPrefix)",
                LOG_ADDRESS_SERVICE
            );
            throw $e;
        }
        //endregion

        //region 4) Сохраняем через сервис
        try {
            $saveResult = $locationService->save($locationAddress);
            if (!$saveResult->isSuccess()) {
                Logs\File::AddMessage(
                    $saveResult->getErrorMessages(),
                    "LocationService::save вернул ошибки ($logPrefix)",
                    LOG_ADDRESS_SERVICE
                );
            }
        }
        catch (Throwable $e) {
            Logs\File::AddMessage(
                $e->getMessage(),
                "Исключение при сохранении LocationAddress ($logPrefix)",
                LOG_ADDRESS_SERVICE
            );
            throw $e;
        }
        //endregion

        //region 5) Дублируем в CRM-таблицу + отправляем событие + финальный лог
        try {
            $locAddrId = $locationAddress->getId();
            $data = $this->buildRqAddressData($cardId, $rqId, $addressTypeId, $locAddrId, $addressData);
            AddressTable::upsertExtended($data);

            $event = new Main\Event('crm', 'OnAfterAddressRegister', ['fields' => $data]);
            $event->send();

            $data = $this->buildCompanyAddressData($cardId, $addressTypeId, $locAddrId, $addressData);
            AddressTable::upsertExtended($data);

            $event = new Main\Event('crm', 'OnAfterAddressRegister', ['fields' => $data]);
            $event->send();

        }
        catch (Throwable $e) {
            Logs\File::AddMessage(
                $e->getMessage(),
                "Ошибка при upsertExtended/событии/логе ($logPrefix)",
                LOG_ADDRESS_SERVICE
            );
            throw $e;
        }
        //endregion
    }
}