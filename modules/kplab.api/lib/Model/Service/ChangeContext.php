<?php

namespace KPLab\API\V2\Model\Service;

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