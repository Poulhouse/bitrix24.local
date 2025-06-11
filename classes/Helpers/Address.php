<?php
namespace KPLab\Helpers;

use Bitrix\Main\Web\HttpClient;
use KPLab\Logs\File;
use KPLab\CRM\AddressTable;
use Bitrix\Location\Entity\Address as EntityAddress;
use Bitrix\Location\Service\AddressService;

define("LOG_CRM_ADDRESS", $_SERVER['DOCUMENT_ROOT']."/local/classes/CRM/Address.log");
class Address
{
    /**
     * Обработка адресов для реквизита.
     */
    public static function processAddressRequisites(mixed $rqId, int $entityTypeId, int $cardId, array $dataArray): void
    {
        if (!isset($rqId) || !isset($dataArray['address']) || !is_array($dataArray['address'])) {
            return;
        }

        foreach ($dataArray['address'] as $address) {
            if (empty($address['fiasId'])) {
                continue;
            }

            $addressFiasId = $address['fiasId'];

            // Определяем тип адреса для дальнейшей обработки
            switch ($address['type']) {
                case 'registration':
                    $addressTypeId = 4;
                    break;
                case 'actual':
                    $addressTypeId = 1;
                    break;
                case 'legal':
                    $addressTypeId = 6;
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
            // Логируем результат обработки для отладки
            File::AddMessage($addressData, "dadata", LOG_CRM_ADDRESS);

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
                File::AddMessage(implode(', ', $result->getErrorMessages()), "Не удалось создать детальный адрес", LOG_CRM_ADDRESS);
            }

            // Получаем идентификатор созданного location address:
            $locAddrId = $locationAddress->getId();

            // Формируем массив для записи с использованием расширенной ORM-модели
            $data = [
                'TYPE_ID'        => $addressTypeId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite, // Или другой тип, если требуется
                'ENTITY_ID'      => intval($rqId),
                'ANCHOR_ID'      => $cardId,
                'ANCHOR_TYPE_ID'      => $entityTypeId,
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
            File::AddMessage($data, "Обработка адресов для реквизита " . $address['type'], LOG_CRM_ADDRESS);
        }
    }
}
