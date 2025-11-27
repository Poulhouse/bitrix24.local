<?php

namespace KPLab\GitBx\Diff;

class DeepComparator
{
    public const MODE_STRICT = 'strict';
    public const MODE_LOOSE  = 'loose';

    /** @var array<string,bool> */
    protected array $ignoredKeys;

    /** @var array<string,mixed[]> */
    protected array $cache = [];

    protected string $mode;

    public function __construct(
        array $ignoredKeys = ['ID', 'SORT', 'XML_ID'],
        string $mode = self::MODE_STRICT
    ) {
        $this->ignoredKeys = array_fill_keys($ignoredKeys, true);
        $this->mode = $mode;
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_STRICT, self::MODE_LOOSE], true)) {
            throw new \InvalidArgumentException("Unknown compare mode: $mode");
        }
        $this->mode = $mode;
        $this->resetCache();
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Главный метод сравнения
     */
    public function compare(array $a, array $b): array
    {
        $hashA = $this->hash($a);
        $hashB = $this->hash($b);

        $cacheKey = $this->mode . ':' . $hashA . ":" . $hashB;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $diff = $this->compareInternal($a, $b);

        return $this->cache[$cacheKey] = $diff;
    }

    protected function hash(array $a): string
    {
        return md5(serialize($a));
    }

    protected function compareInternal(array $a, array $b): array
    {
        $diff = [];

        // Быстрое объединение ключей (без лишних аллокаций)
        $keys = array_keys($a + $b);

        foreach ($keys as $key) {

            if (isset($this->ignoredKeys[$key])) {
                continue;
            }

            $existsInA = array_key_exists($key, $a);
            $existsInB = array_key_exists($key, $b);

            if (!$existsInA && $existsInB) {
                $diff[$key] = [
                    'old' => null,
                    'new' => $b[$key]
                ];
                continue;
            }

            if ($existsInA && !$existsInB) {
                $diff[$key] = [
                    'old' => $a[$key],
                    'new' => null
                ];
                continue;
            }

            $valA = $a[$key];
            $valB = $b[$key];

            // Массивы сравниваем рекурсивно
            if (is_array($valA) && is_array($valB)) {
                $sub = $this->compare($valA, $valB);
                if (!empty($sub)) {
                    $diff[$key] = $sub;
                }
                continue;
            }

            // Скалярные / простые значения
            if (!$this->isEqual($valA, $valB)) {
                $diff[$key] = [
                    'old' => $valA,
                    'new' => $valB
                ];
            }
        }

        return $diff;
    }

    /**
     * Сравнение двух скалярных значений с учётом режима.
     */
    protected function isEqual($a, $b): bool
    {
        // STRiCT: как было
        if ($this->mode === self::MODE_STRICT) {
            return $a === $b;
        }

        // LOOSE режим:

        // 1. Обрезаем строки
        if (is_string($a)) {
            $a = trim($a);
        }
        if (is_string($b)) {
            $b = trim($b);
        }

        // 2. null / '' / [] как "пустое значение"
        if ($this->isEmptyValue($a) && $this->isEmptyValue($b)) {
            return true;
        }

        // 3. numeric строки и числа считаем равными по значению
        if (is_numeric($a) && is_numeric($b)) {
            return (float)$a == (float)$b;
        }

        // 4. bool приведение
        if (is_bool($a) || is_bool($b)) {
            return (bool)$a === (bool)$b;
        }

        // 5. строковое сравнение по == (но уже после тримов и приведения)
        return $a == $b;
    }

    protected function isEmptyValue($v): bool
    {
        if ($v === null) {
            return true;
        }
        if ($v === '') {
            return true;
        }
        if (is_array($v) && count($v) === 0) {
            return true;
        }
        return false;
    }

    public function addIgnoredKey(string $key): void
    {
        $this->ignoredKeys[$key] = true;
    }

    public function removeIgnoredKey(string $key): void
    {
        unset($this->ignoredKeys[$key]);
    }

    public function resetCache(): void
    {
        $this->cache = [];
    }
}
