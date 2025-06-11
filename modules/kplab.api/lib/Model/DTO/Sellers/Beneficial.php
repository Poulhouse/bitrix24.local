<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 * @OA\Schema(
 *   schema="Beneficial",
 *   type="object",
 *   required={"type"},
 *   @OA\Schema(ref="#/components/schemas/PersonData")
 * )
 */
abstract class Beneficial extends PersonData
{

    /**
     * @param array{type:string, ...} $data
     * @return DataFL|DataIP
     * @throws ArgumentException
     * @throws \DateMalformedStringException
     */
    public static function init(array $data): DataFL|DataIP
    {

        return match ($data['type']) {
            'FL' => DataFL::init($data),
            'IP' => DataIP::init($data),
            default => throw new ArgumentException("Неподдерживаемый тип Beneficiars: {$data['type']}"),
        };
    }
}