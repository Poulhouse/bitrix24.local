<?php

namespace Skania;
use \Bitrix\Main\Loader;

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\CompanyTable;
use Bitrix\Crm\FieldMultiTable;

class _Crm {

    /*
     * поиск карточки по номеру телефона
     */
    public function  findClientWithPhone(string $phoneNumber) : Array
    {

        $cards = [
            'contacts' => [],
            'companies' => [],
        ];

        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        if (str_starts_with($phoneNumber, '7') || str_starts_with($phoneNumber, '8')) {
            $phoneNumber = substr($phoneNumber, 1); // Убираем один символ
        } elseif (str_starts_with($phoneNumber, '7', 1)) {
            $phoneNumber = substr($phoneNumber, 2); // Убираем два символа
        }

        $fieldMulti = FieldMultiTable::getList([
            'filter' => [
                'TYPE_ID' => 'PHONE',
                'VALUE' => "%$phoneNumber%",
            ],
            'select' => ['ELEMENT_ID', 'ENTITY_ID']
        ]);

        while ($field = $fieldMulti->fetch()) {
            $entityId = $field['ENTITY_ID'];
            $elementId = $field['ELEMENT_ID'];

            if ($entityId === 'CONTACT') {
                $cards['contacts'][] = $elementId;
            } elseif ($entityId === 'COMPANY') {
                $cards['companies'][] = $elementId;
            }
        }

        return $cards;
    }
}