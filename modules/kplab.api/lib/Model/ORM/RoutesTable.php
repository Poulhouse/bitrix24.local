<?php
namespace KPLab\API\V2\Model\ORM;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Data\DataManager;

class RoutesTable extends DataManager
{
    public static function getTableName()
    {
        return 'kplab_api_routes'; // Название таблицы
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('ROUTE_PATH', [
                'required' => true
            ]),
            new Entity\StringField('CONTROLLER_NAME', [
                'required' => true
            ]),
            new Entity\StringField('METHOD_NAME', [
                'required' => true
            ]),
            new Entity\StringField('HTTP_METHOD', [
                'required' => true
            ]),
            new Entity\StringField('ACTIVE', [
                'required' => true
            ]),
            new Entity\StringField('LOG_LEVEL', [
                'title' => 'Уровень логирования',
                'default_value' => 'errors',
            ]),
        ];
    }
}
