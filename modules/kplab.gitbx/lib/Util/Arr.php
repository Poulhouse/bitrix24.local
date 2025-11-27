<?php

namespace KPLab\GitBx\Util;

class Arr
{
    public static function indexBy(array $items, string $key): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!array_key_exists($key, $item)) {
                continue;
            }

            $result[(string) $item[$key]] = $item;
        }

        return $result;
    }

    public static function sortByKey(array $array): array
    {
        ksort($array);
        return $array;
    }
}