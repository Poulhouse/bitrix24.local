<?php

namespace Kplab\Exchange_log\Helpers;

class ChangeContext
{
    private static ?string $currentSource = null;

    public static function setSource(string $source): void
    {
        self::$currentSource = $source;
    }

    public static function getSource(): ?string
    {
        return self::$currentSource;
    }

    public static function clear(): void
    {
        self::$currentSource = null;
    }
}