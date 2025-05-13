<?php

namespace KPLab\API\V2\Helpers;

use KPLab\Logs\File;

define("LOG_LOCK_TIMEOUTS", $_SERVER['DOCUMENT_ROOT']."/local/logs/lock_timeouts.log");
define("LOG_LOCK_ERRORS", $_SERVER['DOCUMENT_ROOT']."/local/logs/lock_errors.log");

class Locker
{
    private string $lockDir;
    private int $timeout;
    private array $lockHandles = [];
    private bool $debug = true;

    public function __construct(string $lockDir = '/local/locks/', int $timeout = 30)
    {
        $this->lockDir = $_SERVER['DOCUMENT_ROOT'] .'/'. trim($lockDir, '/') . '/';
        $this->timeout = $timeout;
        $this->ensureLockDirExists();
    }

    /**
     * Получает эксклюзивную блокировку с таймаутом
     */
    public function acquire(string $identifier, int $waitTimeout = 0): bool
    {
        $lockFile = $this->generateLockPath($identifier);
        $startTime = time();

        $handle = fopen($lockFile, 'w+');
        if (!$handle) {
            $this->errorLog("Не удалось открыть файл блокировки: {$lockFile}");
            return false;
        }

        // Неблокирующая попытка получить lock
        if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            $this->log("Файл блокировки создан: {$lockFile}");
            $this->lockHandles[$identifier] = $handle;
            fwrite($handle, json_encode([
                'pid' => getmypid(),
                'time' => time(),
                'identifier' => $identifier
            ]));
            fflush($handle);
            return true;
        }

        // Если lock занят и разрешено ожидание
        if ($wouldBlock && $waitTimeout > 0) {
            $this->log("Ожидание блокировки: {$identifier}");

            do {
                usleep(100000); // 100ms
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $this->lockHandles[$identifier] = $handle;
                    fwrite($handle, json_encode([
                        'pid' => getmypid(),
                        'time' => time(),
                        'identifier' => $identifier
                    ]));
                    return true;
                }
            }
            while (time() - $startTime < $waitTimeout);
        }

        fclose($handle);
        return false;
    }

    /**
     * Освобождает блокировку
     */
    public function release(string $identifier): void
    {
        if (isset($this->lockHandles[$identifier])) {
            $handle = $this->lockHandles[$identifier];
            flock($handle, LOCK_UN);
            fclose($handle);
            unset($this->lockHandles[$identifier]);

            $lockFile = $this->generateLockPath($identifier);
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    /**
     * Проверяет наличие активной блокировки
     */
    public function isLocked(string $identifier): bool
    {
        $lockFile = $this->generateLockPath($identifier);

        if (!file_exists($lockFile)) {
            return false;
        }

        // Проверяем зависшие блокировки
        $lockTime = filemtime($lockFile);
        if (time() - $lockTime > $this->timeout) {
            $currentTime = time() - $lockTime;
            $this->log("Обнаружена зависшая блокировка  {$currentTime} > {$this->timeout}: {$identifier}");
            $this->forceRelease($identifier);
            return false;
        }

        return true;
    }

    /**
     * Принудительно освобождает блокировку
     */
    public function forceRelease(string $identifier): void
    {
        $this->release($identifier); // Сначала корректное освобождение

        $lockFile = $this->generateLockPath($identifier);
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Ожидает освобождения блокировки с таймаутом
     */
    public function waitForRelease(string $identifier, ?int $customTimeout = null): bool
    {
        $timeout = $customTimeout ?? $this->timeout;
        $lockFile = $this->generateLockPath($identifier);
        $startTime = time();

        $this->log("Начало ожидания блокировки для: {$identifier}");
        $this->log("Файл блокировки: {$lockFile}");

        while (true) {
            // Проверяем существование файла блокировки
            if (!file_exists($lockFile)) {
                $this->log("Блокировка освобождена: {$identifier}");
                return true;
            }

            // Проверяем таймаут
            if (time() - $startTime > $timeout) {
                $currentTime = time() - $startTime;
                $this->log("Таймаут ожидания блокировки {$currentTime} > {$timeout}: {$identifier}");
                $this->forceRelease($identifier);
                return false;
            }

            // Проверяем зависшую блокировку
            $lockTime = filemtime($lockFile);
            if (time() - $lockTime > $timeout) {
                $currentTime = time() - $lockTime;
                $this->log("Обнаружена зависшая блокировка  {$currentTime} > {$timeout}: {$identifier}");
                $this->forceRelease($identifier);
                return false;
            }

            usleep(100000); // Пауза 100ms между проверками
        }
    }
    public function log(string $message): void
    {
        if ($this->debug) {
            File::AddMessage($message, "Locker", LOG_LOCK_TIMEOUTS);
        }
    }
    public function errorLog(string $message): void
    {
        if ($this->debug) {
            File::AddMessage($message, "Locker", LOG_LOCK_ERRORS);
        }
    }
    public function renameLock(string $oldIdentifier, string $newIdentifier): bool
    {
        $oldPath = $this->generateLockPath($oldIdentifier);
        $newPath = $this->generateLockPath($newIdentifier);

        if (!file_exists($oldPath)) {
            return false;
        }

        // Атомарное переименование
        if (!rename($oldPath, $newPath)) {
            $this->errorLog("Failed to rename lock: {$oldPath} → {$newPath}");
            return false;
        }

        $this->log("Блокировка переименована: {$oldPath} → {$newPath}");

        // Обновляем handle в массиве
        if (isset($this->lockHandles[$oldIdentifier])) {
            $this->lockHandles[$newIdentifier] = $this->lockHandles[$oldIdentifier];
            unset($this->lockHandles[$oldIdentifier]);
        }

        return true;
    }

    private function generateLockPath(string $identifier): string
    {
        $safeId = preg_replace('/[^a-z0-9_-]/i', '_', $identifier);
        return $this->lockDir . 'lock_' . $safeId . '.lock';
    }
    private function ensureLockDirExists(): void
    {
        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0775, true);
        }
    }
    public function __destruct()
    {
        foreach ($this->lockHandles as $identifier => $handle) {
            $this->release($identifier);
        }
    }
}