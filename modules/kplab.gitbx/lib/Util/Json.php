<?php

namespace KPLab\GitBx\Util;

use KPLab\GitBx\Exception\SnapshotException;
class Json
{
    public static function encode($data): string
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SnapshotException('JSON encode error: ' . json_last_error_msg());
        }

        return $json;
    }

    public static function decode(string $json, bool $assoc = true)
    {
        $data = json_decode($json, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SnapshotException('JSON decode error: ' . json_last_error_msg());
        }

        return $data;
    }
}