<?php
namespace KPLab\Market\Orm;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\Market\Service\HighloadLocator;
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

        $dataClass = HighloadLocator::getInstallationsDataClass();
        Rest::log('installationsTable_getDataClass', $dataClass);

        return self::$dataClass = $dataClass;
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
