<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Model\DTO\Common\PassportDTO;
use KPLab\API\V2\Factory\FileFactory;

class PassportFactory
{
    public static function fromArray(array $data): PassportDTO
    {
        $dto = new PassportDTO();
        $dto->issuer = $data['issuer'] ?? '';
        $dto->number = $data['number'] ?? '';
        $dto->series = $data['series'] ?? '';
        $dto->issuedAt = $data['issuedAt'] ?? '';
        $dto->issuerCode = $data['issuerCode'] ?? '';
        $dto->files = isset($data['files']) && is_array($data['files'])
            ? FileFactory::many($data['files'])
            : [];

        return $dto;
    }

    /**
     * @param array $items
     * @return PassportDTO[]
     */
    public static function many(array $items): array
    {
        return array_map([self::class, 'fromArray'], $items);
    }
}