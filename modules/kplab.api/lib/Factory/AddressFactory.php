<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Model\DTO\Common\AddressDTO;

class AddressFactory
{
    public static function fromArray(array $data): AddressDTO
    {
        $dto = new AddressDTO();
        $dto->type = $data['type'] ?? '';
        $dto->fiasId = $data['fiasId'] ?? '';
        return $dto;
    }
    public static function many(array $items): array
    {
        return array_map([self::class, 'fromArray'], $items);
    }
}