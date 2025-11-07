<?php
namespace KPLab\Market\Orm;

use Bitrix\Main\Entity;
use KPLab\Market\Service\HighloadLocator;

class AppTable extends Entity\DataManager
{
    protected static $dataClass;

    public static function getEntityDataClass(): string
    {
        if (self::$dataClass) return self::$dataClass;
        $dataClass = HighloadLocator::getApplicationsDataClass();
        return self::$dataClass = $dataClass;
    }

    // шорткаты
    public static function add(array $fields)
    {
        $c = static::getEntityDataClass();
        return $c::add($fields);
    }
    public static function update($id, array $fields)
    {
        $c = static::getEntityDataClass();
        return $c::update($id, $fields);
    }
    public static function getList(array $params=[])
    {
        $c = static::getEntityDataClass();
        return $c::getList($params);
    }
}
