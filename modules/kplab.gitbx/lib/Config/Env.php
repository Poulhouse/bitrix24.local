<?php

namespace KPLab\GitBx\Config;

use Bitrix\Main\Config\Option;

class Env
{
    public const MODULE_ID = 'kplab.gitbx';

    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    public const ROLE_SOURCE = 'source';
    public const ROLE_TARGET = 'target';

    public static function getEnv(): string
    {
        $env = (string) Option::get(self::MODULE_ID, 'env', self::ENV_TEST);

        if (!in_array($env, [self::ENV_TEST, self::ENV_PROD])) {
            $env = self::ENV_TEST;
        }

        return $env;
    }

    public static function getRole(): string
    {
        $role = (string) Option::get(self::MODULE_ID, 'role', self::ROLE_SOURCE);

        if (!in_array($role, [self::ROLE_SOURCE, self::ROLE_TARGET])) {
            $role = self::ROLE_SOURCE;
        }

        return $role;
    }

    public static function isTest(): bool
    {
        return self::getEnv() === self::ENV_TEST;
    }

    public static function isProd(): bool
    {
        return self::getEnv() === self::ENV_PROD;
    }

    public static function isSource(): bool
    {
        return self::getRole() === self::ROLE_SOURCE;
    }

    public static function isTarget(): bool
    {
        return self::getRole() === self::ROLE_TARGET;
    }
}