<?php
namespace KPLab\API\V2\Model\ORM;

use Bitrix\Main\Entity;
use Bitrix\Main\Type;

class LogsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'kplab_api_logs'; // Имя таблицы в базе данных
    }

    public static function getMap()
    {
        return [
            'ID' => [
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
            ],
            'TITLE' => [
                'data_type' => 'string',
                'title' => 'Название',
                'required' => true,
            ],
            'PARTNER_NAME' => [
                'data_type' => 'string',
                'title' => 'Партнер',
            ],
            'METHOD_NAME' => [
                'data_type' => 'string',
                'title' => 'Метод',
            ],
            'OBJECT_URL' => [
                'data_type' => 'string',
                'title' => 'Объект интеграции',
            ],
            'REQUEST_URL' => [
                'data_type' => 'string',
                'title' => 'URL запроса',
            ],
            'CONTROLLER_NAME' => [
                'data_type' => 'string',
                'title' => 'Контроллер',
            ],
            'REQUEST_METHOD' => [
                'data_type' => 'string',
                'title' => 'Метод запроса',
            ],
            'REQUEST_STATUS' => [
                'data_type' => 'string',
                'title' => 'Статус запроса',
            ],
            'RESPONSE' => [
                'data_type' => 'text',
                'title' => 'Ответ',
            ],
            'EXECUTION_TIME' => [
                'data_type' => 'float',
                'title' => 'Время выполнения',
            ],
            'REQUEST_BODY' => [
                'data_type' => 'text',
                'title' => 'Тело запроса',
            ],
            'REQUEST_TYPE' => [
                'data_type' => 'string',
                'title' => 'Тип запроса',
            ],
            'REQUEST_HEADERS' => [
                'data_type' => 'text',
                'title' => 'Заголовки запроса',
            ],
            'TASK_ID' => [
                'data_type' => 'string',
                'title' => 'ID задачи',
            ],
            'REQUEST_TIME' => [
                'data_type' => 'datetime',
                'title' => 'Время запроса',
                'default_value' => new Type\DateTime(),
            ],
            'REQUEST_TYPE_ID' => [
                'data_type' => 'integer',
                'title' => 'ID Типа запроса',
            ],
        ];
    }
}
