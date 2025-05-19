<?php
namespace KPLab\API\V2\Model\ORM;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Data\DataManager;

class ApiKeysTable extends DataManager
{
    public static function getTableName()
    {
        return 'kplab_api_keys';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('API_KEY', [
                'required' => true
            ]),
            new Entity\IntegerField('USER_ID', [
                'default_value' => 1 // По умолчанию ID админа
            ]),
            new Entity\StringField('SERVICE_NAME', [
                'required' => true
            ]),
            new Entity\StringField('STATUS', [
                'default_value' => 'active'
            ]),
            new Entity\StringField('MANUAL_ENTRY', [
                'default_value' => 'N' // По умолчанию ключ сгенерирован автоматически
            ]),
            new Entity\StringField('KEY_LOCATION', [
                'default_value' => 'header', // body, header или query
                'required' => true
            ]),
            new Entity\StringField('KEY_PARAM_NAME', [
                'required' => true, // Имя параметра в теле, заголовке или query
                'default_value' => 'Authorization' // Например, Authorization или apiKey
            ])
        ];
    }
}

