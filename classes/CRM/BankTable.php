<?php

namespace KPLab\CRM;

use Bitrix\Crm\BankDetailTable as BaseBankTable;
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;

/**
 * Класс BankTable расширяет функциональность стандартной модели b_crm_bank_detail,
 * добавляя в карту дополнительные поля: PRIMARY_ACC.
 */
class BankTable extends BaseBankTable
{
    /**
     * Расширяем карту сущности, добавляя дополнительные колонки.
     * Эти поля должны физически присутствовать в таблице b_crm_bank_detail.
     *
     * @return array
     */
    public static function getMap(): array
    {
        $map = parent::getMap();
        $map['PRIMARY_ACC'] = ['data_type' => 'boolean'];
        return $map;
    }

    /**
     * Вставка или обновление записи в b_crm_bank_detail
     *
     * @param array $data параметры вставки/обновления, например:
     *   [
     *     'ENTITY_TYPE_ID' => 4,
     *     'ENTITY_ID' => 123,
     *     'RQ_ACC_NUM' => '40702810123456789012',
     *     'RQ_BANK_NAME' => 'Сбербанк',
     *     'PRIMARY_ACC' => true,
     *     // … другие поля из getMap()
     *   ]
     *
     * @return void
     * @throws SqlQueryException
     */
    public static function upsertExtended(array $data): void
    {
        // 1. Определяем «уникальные» колонки для MERGE
        //    У вас может быть логика: по ENTITY_TYPE_ID+ENTITY_ID+RQ_ACC_NUM
        //    или просто по ENTITY_TYPE_ID+ENTITY_ID, если по одному счёту на сущность.
        $uniqueKeys = ['ID', 'ENTITY_TYPE_ID', 'ENTITY_ID'];

        // 2. Формируем поля для вставки и для обновления
        //    Для MERGE можно передать один и тот же массив:
        //    первые — INSERT, вторые — UPDATE.
        $insertFields = $data;
        $updateFields = $data;

        // 3. Генерируем SQL MERGE
        $connection = Application::getConnection();
        $helper     = $connection->getSqlHelper();
        $queries    = $helper->prepareMerge(
            static::getTableName(),  // 'b_crm_bank_detail'
            $uniqueKeys,
            $insertFields,
            $updateFields
        );

        // 4. Выполняем
        foreach ($queries as $sql)
        {
            $connection->queryExecute($sql);
        }
    }
}