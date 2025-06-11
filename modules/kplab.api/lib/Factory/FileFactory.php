<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Model\DTO\Common\FileDTO;
class FileFactory
{
    public static function fromArray(array $data): FileDTO
    {
        $dto = new FileDTO();
        $dto->fileName = $data['fileName'] ?? '';
        $dto->file     = $data['file'] ?? '';
        return $dto;
    }

    /**
     * @param array $items
     * @return FileDTO[]
     */
    public static function many(array $items): array
    {
        return array_map([self::class, 'fromArray'], $items);
    }
}