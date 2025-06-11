<?php

namespace KPLab\API\V2\Model\DTO\Common;

class FileDTO
{
    public string $fileName;
    public string $file; // base64 string
    public static function required(): array
    {
        return [
            'fileName',
            'file',
        ];
    }
}