<?php
namespace Kplab\Exchange_log;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\Type;

class ExchangeLogTable extends DataManager
{
    public static function getTableName()
    {
        return 'kplab_exchange_log'; // Название таблицы
    }

    public static function getMap()
    {
        return [
            'ID' => [
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
            ],
            'ENTITY_TYPE_ID' => [
                'data_type' => 'integer',
                'title' => 'ID типа сущности',
                'required' => true,
            ],
            'ENTITY_ID' => [
                'data_type' => 'integer',
                'title' => 'ID Элемента',
                'required' => true,
            ],
            'FIELD_NAME' => [
                'data_type' => 'string',
                'title' => 'Название поля',
                'required' => true,
            ],
            'OLD_VALUE' => [
                'data_type' => 'text',
                'title' => 'Старое значение',
                'required' => false,
            ],
            'NEW_VALUE' => [
                'data_type' => 'text',
                'title' => 'Новое значение',
                'required' => true,
            ],
            'USER_ID' => [
                'data_type' => 'integer',
                'title' => 'Ответственный',
                'required' => true,
            ],
            'SERVICE_UPDATE_NAME' => [
                'data_type' => 'string',
                'title' => 'Сервис источник обновления',
                'required' => true,
            ],
            'CHANGE_DATE' => [
                'data_type' => 'datetime',
                'title' => 'Дата и время изменения',
                'default_value' => function() {
                    return new Type\DateTime();
                },
                'required' => true,
            ],
        ];
    }
}
