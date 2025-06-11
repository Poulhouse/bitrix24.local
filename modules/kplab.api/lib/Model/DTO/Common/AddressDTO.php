<?php

namespace KPLab\API\V2\Model\DTO\Common;

class AddressDTO
{
    public string $type; // registration, actual, legal
    public string $fiasId;
    public static function required(): array
    {
        return [
            'type',
            'fiasId',
        ];
    }
}