<?php

namespace KPLab\API\V2\Model\DTO\Common;

class DocumentDTO
{
    public int $type;

    /** @var FileDTO[] */
    public array $files = [];

    public static function required(): array
    {
        return [
            'type',
            'files',
        ];
    }

}