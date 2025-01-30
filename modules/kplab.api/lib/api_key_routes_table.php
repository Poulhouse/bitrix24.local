<?php
namespace KPLab\API\V2;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Data\DataManager;

class ApiKeyRoutesTable extends DataManager
{
    public static function getTableName()
    {
        return 'kplab_api_key_routes'; // Название таблицы
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\IntegerField('API_KEY_ID', [
                'required' => true
            ]),
            new Entity\IntegerField('ROUTE_ID', [
                'required' => true
            ]),
            new Entity\ReferenceField(
                'API_KEY',
                ApiKeysTable::class,
                ['=this.API_KEY_ID' => 'ref.ID'],
                ['join_type' => 'INNER']
            ),
            new Entity\ReferenceField(
                'ROUTE',
                RoutesTable::class,
                ['=this.ROUTE_ID' => 'ref.ID'],
                ['join_type' => 'INNER']
            )
        ];
    }
}
