<?php

namespace KPLab\GitBx\Util;

use Bitrix\Main\Application;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;
use KPLab\GitBx\Config\ModuleSettings;

class Logger
{
    public const LEVEL_DEBUG     = 'DEBUG';
    public const LEVEL_INFO      = 'INFO';
    public const LEVEL_WARNING   = 'WARNING';
    public const LEVEL_ERROR     = 'ERROR';
    public const LEVEL_CRITICAL  = 'CRITICAL';

    /**
     * Единая точка входа
     */
    public static function log(string $message, string $level = self::LEVEL_INFO, array $context = []): void
    {
        // Проверка включения логов
        if (!ModuleSettings::isLoggingEnabled()) {
            return;
        }

        $relative = ModuleSettings::get('dir_logs');

        if (!$relative) {
            return;
        }
        // убираем первый слеш, если есть
        $relative = ltrim($relative, '/');

        // строим абсолютный путь
        $dir = Application::getDocumentRoot() . '/' . $relative;

        // Создать каталог, если отсутствует
        if (!Directory::isDirectoryExists($dir)) {
            Directory::createDirectory($dir);
        }

        $filePath = $dir . 'gitbx_' . date('Y-m-d') . '.log';

        // Формируем запись
        $entry = self::formatEntry($message, $level, $context);

        // Записываем атомарно
        File::putFileContents($filePath, $entry . PHP_EOL, File::APPEND);
    }

    /**
     * Формат записи для лога.
     */
    protected static function formatEntry(string $message, string $level, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');

        // Кто инициировал (если есть)
        $userId = self::detectUserId();

        $contextStr = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        return "[{$timestamp}] [{$level}] [user={$userId}] {$message} {$contextStr}";
    }

    /**
     * Определяем текущего администратора.
     */
    protected static function detectUserId(): int
    {
        global $USER;

        if (\is_object($USER) && $USER->IsAuthorized()) {
            return (int)$USER->GetID();
        }

        // Для REST/CLI
        return 0;
    }

    /**
     * Удобные обёртки для разных уровней
     */
    public static function debug(string $message, array $ctx = []): void
    {
        self::log($message, self::LEVEL_DEBUG, $ctx);
    }

    public static function info(string $message, array $ctx = []): void
    {
        self::log($message, self::LEVEL_INFO, $ctx);
    }

    public static function warning(string $message, array $ctx = []): void
    {
        self::log($message, self::LEVEL_WARNING, $ctx);
    }

    public static function error(string $message, array $ctx = []): void
    {
        self::log($message, self::LEVEL_ERROR, $ctx);
    }

    public static function critical(string $message, array $ctx = []): void
    {
        self::log($message, self::LEVEL_CRITICAL, $ctx);
    }
}
