<?php

namespace KPLab\GitBx\Config;

use Bitrix\Main\Config\Option;

class ModuleSettings
{
    public const MODULE_ID = Env::MODULE_ID;

    public static function get(string $name, $default = null)
    {
        return Option::get(self::MODULE_ID, $name, $default);
    }

    public static function set(string $name, $value): void
    {
        Option::set(self::MODULE_ID, $name, (string) $value);
    }

    public static function remoteUrl(): string
    {
        return rtrim(self::get('remote_url', ''), '/');
    }

    public static function remoteToken(): string
    {
        return self::get('remote_token', '');
    }

    public static function currentToken(): string
    {
        return self::get('current_token');
    }
    public static function dirSnapshots(): string
    {
        $relative = self::get('dir_snapshots');

        if (!$relative) {
            // дефолт
            $relative = '/local/modules/kplab.gitbx/storage/snapshots/';
        }

        // приводим к абсолютному
        $abs = \Bitrix\Main\Application::getDocumentRoot() . '/' . ltrim($relative, '/');
        $abs = rtrim($abs, '/');

        // создаём каталог если нет
        if (!\Bitrix\Main\IO\Directory::isDirectoryExists($abs)) {
            \Bitrix\Main\IO\Directory::createDirectory($abs);
        }

        return $abs;
    }


    public static function apiTimeout(): int
    {
        return (int) self::get('api_timeout', 5);
    }

    public static function isLoggingEnabled(): bool
    {
        return self::get('logging', 'N') === 'Y';
    }
}