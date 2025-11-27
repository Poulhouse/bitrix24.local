<?php

namespace KPLab\GitBx\Util;

class Normalizer
{
    public static function loose(array &$data): void
    {
        self::dropKeys($data, ['ID', 'CREATED_TIME', 'UPDATED_TIME', 'CREATED_BY', 'MODIFIED_BY']);
        self::ksortRecursive($data);
    }

    public static function strict(array &$data): void
    {
        self::ksortRecursive($data);
    }

    /**
     * Рекурсивная сортировка по ключам (для ассоц.массивов).
     */
    public static function ksortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }

    /**
     * Удалить набор ключей с любого уровня ассоц.массивов (только верхний уровень каждой "записи").
     */
    public static function dropKeys(array &$data, array $keys): void
    {
        $keysMap = array_fill_keys($keys, true);

        foreach ($data as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($keysMap as $k => $_) {
                if (array_key_exists($k, $row)) {
                    unset($row[$k]);
                }
            }
        }
    }

    /**
     * Нормализация ENUM массива: индекс по XML_ID, без ID/SORT, обрезка строк.
     */
    public static function normalizeEnumByXmlId(array $enum): array
    {
        $result = [];

        foreach ($enum as $item) {
            if (!is_array($item)) {
                continue;
            }

            $xmlId = isset($item['XML_ID']) ? trim((string)$item['XML_ID']) : '';
            if ($xmlId === '') {
                continue;
            }

            unset($item['ID'], $item['SORT']);

            if (isset($item['VALUE'])) {
                $item['VALUE'] = trim((string)$item['VALUE']);
            }

            $result[$xmlId] = $item;
        }

        ksort($result);

        return $result;
    }

    /**
     * Нормализация "дерева сущностей":
     * - сортируем сущности по ключу
     * - сортируем поля внутри сущности по FIELD_NAME/кодам
     * - нормализуем настройки по callback'у
     */
    public static function normalizeEntityTree(
        array $tree,
        callable $fieldNormalizer = null
    ): array {
        foreach ($tree as $entity => &$fields) {
            if (!is_array($fields)) {
                unset($tree[$entity]);
                continue;
            }

            ksort($fields); // UF-коды, ID стадий и т.п.

            foreach ($fields as $code => &$field) {
                if (!is_array($field)) {
                    unset($fields[$code]);
                    continue;
                }

                if ($fieldNormalizer) {
                    $field = $fieldNormalizer($field, $entity, $code);
                }
            }
        }

        ksort($tree);

        return $tree;
    }
}