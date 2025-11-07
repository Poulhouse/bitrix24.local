<?php
namespace KPLab\Market\Orm;

use Bitrix\Main\Entity;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;

class AppTable extends Entity\DataManager
{
    protected static $dataClass;

    public static function getEntityDataClass(): string
    {
        if (self::$dataClass) return self::$dataClass;
        Loader::includeModule('highloadblock');
        $hlId = (int)Option::get('kplab.market', 'HL_INSTALLS_ID');
        if ($hlId > 0) {
            $hl = HighloadBlockTable::getById($hlId)->fetch();
        } else {
            throw new \RuntimeException('HL_INSTALLS_ID option not set');
        }
        $entity = HighloadBlockTable::compileEntity($hl);
        return self::$dataClass = $entity->getDataClass();
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
