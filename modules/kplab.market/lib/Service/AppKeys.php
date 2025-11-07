<?php
namespace KPLab\Market\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Highloadblock\HighloadBlockTable;

class AppKeys
{
    protected static function getDataClass(): string
    {
        Loader::includeModule('highloadblock');
        $hlId = (int)Option::get('kplab.market', 'HL_APPS_ID');
        if (!$hlId)
            throw new \RuntimeException('HL_APPS_ID not configured');

        $hl = HighloadBlockTable::getById($hlId)->fetch();
        $entity = HighloadBlockTable::compileEntity($hl);
        return $entity->getDataClass();
    }

    public static function getByCode(string $appCode): ?array
    {
        $c = static::getDataClass();
        return $c::getList(['filter' => ['=UF_CODE' => $appCode]])->fetch() ?: null;
    }

    public static function getAllActive(): array
    {
        $c = static::getDataClass();
        return $c::getList(['filter' => ['=UF_STATUS' => 'ACTIVE']])->fetchAll();
    }
}
