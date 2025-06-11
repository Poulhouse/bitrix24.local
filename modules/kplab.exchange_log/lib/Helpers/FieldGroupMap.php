<?php
namespace Kplab\Exchange_log\Helpers;

class FieldGroupMap
{
    public static function getGroupTitle(string $group): string
    {
        $titles = [
            'contactDetails' => 'Контактные данные',
            'addressDetails' => 'Адреса',
            'bankDetails'    => 'Банковские реквизиты',
        ];

        return $titles[$group] ?? ucfirst($group);
    }
}