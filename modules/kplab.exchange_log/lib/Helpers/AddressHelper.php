<?php

namespace Kplab\Exchange_log\Helpers;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

class AddressHelper
{
    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function getNormalizedAddressArray(int $companyId): array
    {
        $addressList = \CRest::call('crm.address.list', [
            'filter' => [
                'ANCHOR_ID' => $companyId,
                'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company,
            ],
            'select' => ['TYPE_ID', 'LOC_ADDR_ID']
        ])['result'];

        $controller = new \Bitrix\Location\Controller\Address;
        $result = [];

        foreach ($addressList as $item) {
            $typeId = $item['TYPE_ID'];
            $addrObj = $controller->findById($item['LOC_ADDR_ID']);

            if (empty($addrObj['fieldCollection']) || !is_array($addrObj['fieldCollection'])) {
                continue;
            }

            $components = array_filter($addrObj['fieldCollection']);
            $formattedAddress = implode(', ', $components);

            // Привязываем к типу
            if ($typeId == 1) {
                $result['FACT'] = $formattedAddress;
            } elseif ($typeId == 4 || $typeId == 6) {
                $result['REG'] = $formattedAddress;
            }
        }

        return $result;
    }

}