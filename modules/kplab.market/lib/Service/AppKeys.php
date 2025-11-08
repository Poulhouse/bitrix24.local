<?php
namespace KPLab\Market\Service;

class AppKeys
{
    protected static function getDataClass(): string
    {
        return HighloadLocator::getApplicationsDataClass();
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
