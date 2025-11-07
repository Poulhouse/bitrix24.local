<?php
namespace KPLab\Market\Orm;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\Market\Service\Rest;

class InstallationsTable extends Entity\DataManager
{
    protected static $dataClass;

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getEntityDataClass(): string
    {
        if (self::$dataClass)
            return self::$dataClass;

        Loader::includeModule('highloadblock');

        $hlId = (int)Option::get('kplab.market', 'HL_INSTALLS_ID');
        if (!$hlId)
            throw new \RuntimeException('HL_INSTALLS_ID not configured in module options');

        $hl = HighloadBlockTable::getById($hlId)->fetch();
        $entity = HighloadBlockTable::compileEntity($hl);
        Rest::log('installationsTable_getDataClass', $entity->getDataClass());
        return self::$dataClass = $entity->getDataClass();
    }

    public static function add(array $fields)
    {

        Rest::log('installationsTable_add', $fields);
        $c = static::getEntityDataClass();
        $result = $c::add($fields);

        if (!$result->isSuccess()) {
            Rest::log('installationsTable_add_error', $result->getErrorMessages());
        } else {
            Rest::log('installationsTable_add_success', ['ID' => $result->getId()]);
        }
        return $result;
    }

    public static function update($id, array $fields)
    {

        Rest::log('installationsTable_update', $fields);
        $c = static::getEntityDataClass();
        return $c::update($id, $fields);
    }

    public static function getList(array $params = [])
    {

        Rest::log('installationsTable_getList', $params);
        $c = static::getEntityDataClass();
        return $c::getList($params);
    }
}
