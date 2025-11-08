<?php
namespace KPLab\Market\Service;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class HighloadLocator
{
    private const MODULE_ID = 'kplab.market';

    private static array $hlCache = [];
    private static array $dataClassCache = [];

    /**
     * @throws \RuntimeException
     */
    public static function getApplicationsId(): int
    {
        return (int)self::resolve('apps')['ID'];
    }

    /**
     * @throws \RuntimeException
     */
    public static function getInstallationsId(): int
    {
        return (int)self::resolve('installs')['ID'];
    }

    /**
     * @throws \RuntimeException
     */
    public static function getApplicationsDataClass(): string
    {
        return self::getDataClass('apps');
    }

    /**
     * @throws \RuntimeException
     */
    public static function getInstallationsDataClass(): string
    {
        return self::getDataClass('installs');
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    public static function getApplicationsDefinition(): array
    {
        return self::resolve('apps');
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    public static function getInstallationsDefinition(): array
    {
        return self::resolve('installs');
    }

    /**
     * @throws \RuntimeException
     */
    private static function resolve(string $key): array
    {
        if (isset(self::$hlCache[$key])) {
            return self::$hlCache[$key];
        }

        Loader::includeModule('highloadblock');

        if ($key === 'apps') {
            $optionKey = 'HL_APPS_ID';
            $name = 'KPLabApplications';
        } else {
            $optionKey = 'HL_INSTALLS_ID';
            $name = 'KPLabAppInstallations';
        }

        $hlId = (int)Option::get(self::MODULE_ID, $optionKey);
        $hl = null;
        if ($hlId > 0) {
            $hl = HighloadBlockTable::getById($hlId)->fetch();
        }

        if (!$hl) {
            $hl = HighloadBlockTable::getList(['filter' => ['=NAME' => $name]])->fetch();
            if ($hl) {
                Option::set(self::MODULE_ID, $optionKey, $hl['ID']);
            }
        }

        if (!$hl) {
            throw new \RuntimeException(sprintf('Highload block %s is not configured', $name));
        }

        return self::$hlCache[$key] = $hl;
    }

    /**
     * @throws \RuntimeException
     */
    private static function getDataClass(string $key): string
    {
        if (isset(self::$dataClassCache[$key])) {
            return self::$dataClassCache[$key];
        }

        $definition = self::resolve($key);
        $entity = HighloadBlockTable::compileEntity($definition);

        return self::$dataClassCache[$key] = $entity->getDataClass();
    }
}
