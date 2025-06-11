<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Model\DTO\Common\DocumentDTO;
use KPLab\API\V2\Factory\FileFactory;

class DocumentFactory
{
    public static function fromArray(array $data): DocumentDTO
    {
        $dto = new DocumentDTO();
        $dto->type = $data['type'] ?? 0;
        $dto->files = isset($data['files']) && is_array($data['files'])
            ? FileFactory::many($data['files'])
            : [];

        return $dto;
    }

    /**
     * @param array $items
     * @return DocumentDTO[]
     */
    public static function many(array $items): array
    {
        return array_map([self::class, 'fromArray'], $items);
    }
}