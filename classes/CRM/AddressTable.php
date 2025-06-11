<?php
namespace KPLab\CRM;

use Bitrix\Crm\AddressTable as BaseAddressTable;
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Entity;

/**
 * Класс AddressTable расширяет функциональность стандартной модели b_crm_addr,
 * добавляя в карту дополнительные поля: STREET, BUILDING, FIAS_ID.
 */
class AddressTable extends BaseAddressTable
{
    /**
     * Расширяем карту сущности, добавляя дополнительные колонки.
     * Эти поля должны физически присутствовать в таблице b_crm_addr.
     *
     * @return array
     */
    public static function getMap(): array
    {
        // Получаем карту родительской модели
        $map = parent::getMap();

        // Добавляем виртуальные поля, которые отражают дополнительные колонки в таблице
        $map['LOCALITY'] = ['data_type' => 'string'];
        $map['STREET'] = ['data_type' => 'string'];
        $map['BUILDING'] = ['data_type' => 'string'];
        $map['STEAD'] = ['data_type' => 'string'];
        $map['BLOCK_S'] = ['data_type' => 'string'];
        $map['BLOCK_K'] = ['data_type' => 'string'];
        $map['FLAT'] = ['data_type' => 'string'];
        $map['ROOM'] = ['data_type' => 'string'];
        $map['FIAS_ID'] = ['data_type' => 'string'];
        $map['OKATO'] = ['data_type' => 'string'];

        return $map;
    }

    /**
     * Метод upsertExtended выполняет вставку/обновление записи в таблице b_crm_addr
     * с учётом дополнительных полей: STREET, BUILDING, FIAS_ID.
     *
     * @param array $data Массив данных, например:
     *   [
     *     'TYPE_ID'        => 1,
     *     'ENTITY_TYPE_ID' => 4,
     *     'ENTITY_ID'      => 123,
     *     'ANCHOR_ID'      => 123,
     *     'ADDRESS_1'      => 'Пушкина, 10',
     *     'ADDRESS_2'      => 'Квартира 5',
     *     'CITY'           => 'Санкт-Петербург',
     *     // дополнительные колонки:
     *     'STREET'         => 'Пушкина',
     *     'BUILDING'       => '10',
     *     'BLOCK_S'       => 'строение',
     *     'BLOCK_K'       => 'корпус',
     *     'FIAS_ID'        => 'ABCD-1234-EFGH-5678'
     *   ]
     *
     * @return void
     * @throws SqlQueryException
     */
    public static function upsertExtended(array $data): void
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $tableName = 'b_crm_addr';
        $requiredColumns = [
            'LOCALITY'   => "VARCHAR(255)",
            'STREET'   => "VARCHAR(255)",
            'BUILDING' => "VARCHAR(255)",
            'STEAD' => "VARCHAR(255)",
            'BLOCK_S'  => "VARCHAR(50)",
            'BLOCK_K'  => "VARCHAR(50)",
            'FLAT'  => "VARCHAR(50)",
            'ROOM'  => "VARCHAR(50)",
            'FIAS_ID'  => "VARCHAR(50)",
            'OKATO'   => "VARCHAR(50)",
        ];
        // 1. Получаем список текущих колонок в таблице
        $existingColumns = [];
        $res = $connection->query("SHOW COLUMNS FROM {$tableName}");
        while ($row = $res->fetch()) {
            $existingColumns[] = strtoupper($row['Field']);
        }

        // 2. Проверяем и создаём недостающие колонки
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array(strtoupper($column), $existingColumns, true)) {
                $alterSql = "ALTER TABLE {$tableName} ADD `{$column}` {$definition} NULL DEFAULT NULL";
                $connection->queryExecute($alterSql);
            }
        }

        // 3. Собираем данные для UPSERT
        $typeID = isset($data['TYPE_ID']) ? (int)$data['TYPE_ID'] : 0;
        $entityTypeID = isset($data['ENTITY_TYPE_ID']) ? (int)$data['ENTITY_TYPE_ID'] : 0;
        $entityID = isset($data['ENTITY_ID']) ? (int)$data['ENTITY_ID'] : 0;

        $fields = [
            'ANCHOR_TYPE_ID' => isset($data['ANCHOR_TYPE_ID']) ? (int)$data['ANCHOR_TYPE_ID'] : $entityTypeID,
            'ANCHOR_ID' => isset($data['ANCHOR_ID']) ? (int)$data['ANCHOR_ID'] : $entityID,
            'ADDRESS_1' => isset($data['ADDRESS_1']) && $data['ADDRESS_1'] !== '' ? $data['ADDRESS_1'] : null,
            'ADDRESS_2' => isset($data['ADDRESS_2']) && $data['ADDRESS_2'] !== '' ? $data['ADDRESS_2'] : null,
            'CITY' => isset($data['CITY']) && $data['CITY'] !== '' ? $data['CITY'] : null,
            'LOCALITY' => isset($data['LOCALITY']) && $data['LOCALITY'] !== '' ? $data['LOCALITY'] : null,
            'POSTAL_CODE' => isset($data['POSTAL_CODE']) && $data['POSTAL_CODE'] !== '' ? $data['POSTAL_CODE'] : null,
            'REGION' => isset($data['REGION']) && $data['REGION'] !== '' ? $data['REGION'] : null,
            'PROVINCE' => isset($data['PROVINCE']) && $data['PROVINCE'] !== '' ? $data['PROVINCE'] : null,
            'COUNTRY' => isset($data['COUNTRY']) && $data['COUNTRY'] !== '' ? $data['COUNTRY'] : null,
            'COUNTRY_CODE' => isset($data['COUNTRY_CODE']) && $data['COUNTRY_CODE'] !== '' ?
                $data['COUNTRY_CODE'] : null,
            'LOC_ADDR_ID' => isset($data['LOC_ADDR_ID']) ? (int)$data['LOC_ADDR_ID'] : 0,
            'IS_DEF' => (isset($data['IS_DEF']) && $data['IS_DEF'] === true) ? 1 : 0,
            // дополнительные колонки:
            'STREET' => isset($data['STREET']) && $data['STREET'] !== '' ? $data['STREET'] : null,
            'BUILDING' => isset($data['BUILDING']) && $data['BUILDING'] !== '' ? $data['BUILDING'] : null,
            'STEAD' => isset($data['STEAD']) && $data['STEAD'] !== '' ? $data['STEAD'] : null,
            'BLOCK_S' => isset($data['BLOCK_S']) && $data['BLOCK_S'] !== '' ? $data['BLOCK_S'] : null,
            'BLOCK_K' => isset($data['BLOCK_K']) && $data['BLOCK_K'] !== '' ? $data['BLOCK_K'] : null,
            'FLAT' => isset($data['FLAT']) && $data['FLAT'] !== '' ? $data['FLAT'] : null,
            'ROOM' => isset($data['ROOM']) && $data['ROOM'] !== '' ? $data['ROOM'] : null,
            'FIAS_ID' => isset($data['FIAS_ID']) && $data['FIAS_ID'] !== '' ? $data['FIAS_ID'] : null,
            'OKATO' => isset($data['OKATO']) && $data['OKATO'] !== '' ? $data['OKATO'] : null
        ];

        $connection = Application::getConnection();
        $queries = $connection->getSqlHelper()->prepareMerge(
            'b_crm_addr',
            ['TYPE_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID'],
            array_merge(
                $fields,
                ['TYPE_ID' => $typeID, 'ENTITY_TYPE_ID' => $entityTypeID, 'ENTITY_ID' => $entityID]
            ),
            $fields
        );

        foreach($queries as $query)
        {
            $connection->queryExecute($query);
        }
    }
}
