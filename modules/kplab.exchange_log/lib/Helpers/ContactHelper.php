<?php namespace Kplab\Exchange_log\Helpers;

class ContactHelper
{
    /**
     * Извлекает FM-поле (PHONE, EMAIL и т.д.) в виде структурированных пар: VALUE + VALUE_TYPE.
     */
    public static function extractMultiField(array $data, string $fieldCode): array
    {
        if (empty($data['FM'][$fieldCode]) || !is_array($data['FM'][$fieldCode])) {
            return [];
        }

        $result = [];

        foreach ($data['FM'][$fieldCode] as $item) {
            if (!empty($item['VALUE'])) {
                $value = trim((string)$item['VALUE']);
                $type = $item['VALUE_TYPE'] ?? '';
                $result[] = [
                    'VALUE' => $value,
                    'VALUE_TYPE' => $type,
                ];
            }
        }

        // Убираем дубликаты (по комбинации VALUE + TYPE)
        $result = array_unique($result, SORT_REGULAR);

        // Сортировка по VALUE естественная
        usort($result, fn($a, $b) => strnatcasecmp($a['VALUE'], $b['VALUE']));

        return $result;
    }

    /**
     * Извлекает все контактные данные из FM: PHONE, EMAIL, IM, WEB.
     * Возвращает массив вида:
     * [
     *   'PHONE' => [...],
     *   'EMAIL' => [...],
     *   'IM'    => [...],
     *   'WEB'   => [...],
     * ]
     */
    public static function extractAllContactDetails(array $data): array
    {
        $result = [];
        $types = ['PHONE', 'EMAIL', 'IM', 'WEB'];

        foreach ($types as $type) {
            $result[$type] = self::extractMultiField($data, $type);
        }

        return $result;
    }

    public static function getValueTypeLabel(string $type, string $fieldCode): string
    {
        // Общие типы для всех FM
        $commonMap = [
            'WORK'     => 'Рабочий',
            'MOBILE'   => 'Мобильный',
            'FAX'      => 'Факс',
            'HOME'     => 'Домашний',
            'PAGER'    => 'Пейджер',
            'MAILING'  => 'Для рассылки',
            'OTHER'    => 'Другой',
        ];

        // Специфические можно расширить:
        $customMap = [
            'PHONE' => [
                'WORK'     => 'Рабочий',
                'MOBILE'   => 'Мобильный',
                'FAX'      => 'Факс',
                'HOME'     => 'Домашний',
                'PAGER'    => 'Пейджер',
                'MAILING'  => 'Для рассылок',
                'OTHER'    => 'Другой',
            ],
            'EMAIL' => [
                'WORK'     => 'Рабочий',
                'HOME'     => 'Частный',
                'MAILING'  => 'Для рассылок',
                'OTHER'    => 'Другой',
            ],
            'IM'    => [
                'SKYPE'   => 'Skype',
                'TELEGRAM' => 'Telegram',
                'VIBER'   => 'Viber',
                'ICQ'     => 'ICQ',
                'OTHER'   => 'Другое',
            ],
            'WEB'   => [
                'WORK'  => 'Рабочий сайт',
                'HOME'  => 'Домашний сайт',
                'OTHER' => 'Другой сайт',
            ],
        ];

        $map = $customMap[$fieldCode] ?? $commonMap;
        return $map[$type] ?? $type;
    }

}