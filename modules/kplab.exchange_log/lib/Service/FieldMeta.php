<?php namespace Kplab\Exchange_log\Service;

class FieldMeta
{
    protected static array $cache = [];

    public static function get(int $entityTypeId): array
    {
        if (isset(self::$cache[$entityTypeId])) {
            return self::$cache[$entityTypeId];
        }

        // Стандартные CRM-сущности
        $map = [
            1 => 'crm.lead.fields',
            2 => 'crm.deal.fields',
            3 => 'crm.contact.fields',
            4 => 'crm.company.fields',
        ];

        if (isset($map[$entityTypeId])) {
            $fields = \CRest::call($map[$entityTypeId])['result'] ?? [];
        } else {
            // Смарт‑процессы (через crm.item.fields)
            $fields = \CRest::call('crm.item.fields', [
                'entityTypeId' => $entityTypeId,
                'useOriginalUfNames' => 'Y',
            ])['result']['fields'] ?? [];
        }

        // Сохраняем в кэш
        self::$cache[$entityTypeId] = $fields;

        return $fields;
    }

    public static function getTitle(int $entityTypeId, string $fieldCode): string
    {
        $meta = self::get($entityTypeId);
        return $meta[$fieldCode]['formLabel']
            ?? $meta[$fieldCode]['title']
            ?? $fieldCode;
    }
}