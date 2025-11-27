<?php

namespace KPLab\GitBx\Diff\Providers;
use KPLab\GitBx\Diff\DeepComparator;
use KPLab\GitBx\Util\Normalizer;
class CrmFieldsProvider implements DiffProviderInterface
{
    protected DeepComparator $cmp;

    public function __construct()
    {
        // Для UF-полей разумно использовать LOOSE
        $this->cmp = new DeepComparator(
            ignoredKeys: ['ID', 'SORT'],
            mode: DeepComparator::MODE_LOOSE
        );
    }

    public function getKey(): string
    {
        return 'crm_fields';
    }

    public function normalize(array $data): array
    {
        return Normalizer::normalizeEntityTree($data, function (array $field) {

            // Убираем нестабильные ключи
            unset($field['ID'], $field['XML_ID'], $field['SORT']);

            // SETTINGS
            if (isset($field['SETTINGS']) && is_array($field['SETTINGS'])) {
                $field['SETTINGS'] = $this->normalizeSettings($field['SETTINGS']);
            }

            ksort($field);

            return $field;
        });
    }

    protected function normalizeSettings(array $settings): array
    {
        unset($settings['SHOW_NO_VALUE'], $settings['DEFAULT_VALUE']);

        if (isset($settings['ENUM']) && is_array($settings['ENUM'])) {
            $settings['ENUM'] = Normalizer::normalizeEnumByXmlId($settings['ENUM']);
        }

        ksort($settings);

        return $settings;
    }

    /**
     * Основной diff UF полей
     */
    public function diff(array $test, array $prod): array
    {
        $result = [];

        foreach ($test as $entity => $testFields) {

            $prodFields = $prod[$entity] ?? [];

            $result[$entity] = [
                'added'   => [],
                'removed' => [],
                'changed' => []
            ];

            // ----------------------------------------
            // ВАЖНО: ловим полностью пустые структуры
            // ----------------------------------------
            $isTestEmpty = empty($testFields);
            $isProdEmpty = empty($prodFields);

            if ($isTestEmpty && !$isProdEmpty) {
                $result[$entity]['removed'] = $prodFields;
                continue;
            }

            if (!$isTestEmpty && $isProdEmpty) {
                $result[$entity]['added'] = $testFields;
                continue;
            }

            // ----------------------------------------
            // added
            // ----------------------------------------
            foreach ($testFields as $code => $data) {
                if (!isset($prodFields[$code])) {
                    $result[$entity]['added'][$code] = $data;
                }
            }

            // ----------------------------------------
            // removed
            // ----------------------------------------
            foreach ($prodFields as $code => $data) {
                if (!isset($testFields[$code])) {
                    $result[$entity]['removed'][$code] = $data;
                }
            }

            // ----------------------------------------
            // changed
            // ----------------------------------------
            foreach ($testFields as $code => $fieldTest) {

                if (!isset($prodFields[$code])) {
                    continue;
                }

                $fieldProd = $prodFields[$code];

                $diff = $this->diffField($fieldTest, $fieldProd);

                if (!empty($diff)) {
                    $result[$entity]['changed'][$code] = $diff;
                }
            }
        }

        return $result;
    }

    /**
     * Diff одного UF-поля
     */
    protected function diffField(array $testField, array $prodField): array
    {
        $diff = [];

        // Обычные атрибуты UF поля
        foreach ($testField as $key => $testValue) {

            // ENUM обрабатываем отдельно
            if ($key === 'SETTINGS') {
                $settingsDiff = $this->diffSettings($testValue, $prodField[$key] ?? []);
                if (!empty($settingsDiff)) {
                    $diff['SETTINGS'] = $settingsDiff;
                }
                continue;
            }

            if (!array_key_exists($key, $prodField)) {
                $diff[$key] = ['old' => null, 'new' => $testValue];
                continue;
            }

            if ($testValue !== $prodField[$key]) {
                $diff[$key] = [
                    'old' => $prodField[$key],
                    'new' => $testValue
                ];
            }
        }

        return $diff;
    }

    /**
     * Diff SETTINGS
     */
    protected function diffSettings(array $testSettings, array $prodSettings): array
    {
        $diff = [];

        // ENUM diff
        if (isset($testSettings['ENUM'])) {
            $enumDiff = $this->diffEnum($testSettings['ENUM'], $prodSettings['ENUM'] ?? []);
            if (!empty($enumDiff)) {
                $diff['ENUM'] = $enumDiff;
            }
        }

        // остальные настройки
        foreach ($testSettings as $key => $testValue) {
            if ($key === 'ENUM') continue;

            if (!array_key_exists($key, $prodSettings)) {
                $diff[$key] = ['old' => null, 'new' => $testValue];
                continue;
            }

            if ($prodSettings[$key] !== $testValue) {
                $diff[$key] = [
                    'old' => $prodSettings[$key],
                    'new' => $testValue
                ];
            }
        }

        return $diff;
    }

    /**
     * Diff ENUM-значений UF
     */
    protected function diffEnum(array $testEnum, array $prodEnum): array
    {
        $diff = [
            'added'   => [],
            'removed' => [],
            'changed' => []
        ];

        // added
        foreach ($testEnum as $xmlId => $item) {
            if (!isset($prodEnum[$xmlId])) {
                $diff['added'][$xmlId] = $item;
            }
        }

        // removed
        foreach ($prodEnum as $xmlId => $item) {
            if (!isset($testEnum[$xmlId])) {
                $diff['removed'][$xmlId] = $item;
            }
        }

        // changed
        foreach ($testEnum as $xmlId => $itemTest) {

            if (!isset($prodEnum[$xmlId])) continue;

            $itemProd = $prodEnum[$xmlId];

            $sub = $this->cmp->compare($itemTest, $itemProd);

            if (!empty($sub)) {
                $diff['changed'][$xmlId] = $sub;
            }
        }

        return $this->cleanupEmptyDiff($diff);
    }

    protected function cleanupEmptyDiff(array $diff): array
    {
        foreach ($diff as $key => $value) {
            if (empty($value)) {
                unset($diff[$key]);
            }
        }
        return $diff;
    }
}