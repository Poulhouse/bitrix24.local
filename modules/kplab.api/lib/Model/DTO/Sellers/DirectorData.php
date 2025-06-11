<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 * @OA\Schema(
 *   schema="DirectorData",
 *   type="object",
 *   required={"type"},
 *   discriminator=@OA\Discriminator(
 *     propertyName="type",
 *     mapping={ "FL"="#/components/schemas/DataFL" }
 *   ),
 *   oneOf={ @OA\Schema(ref="#/components/schemas/DataFL") }
 * )
 */
class DirectorData extends BaseData
{

    /**
     * @param array{type:string, ...} $data
     * @return DataFL
     * @throws ArgumentException|\DateMalformedStringException
     */
    public static function init(array $data): DataFL
    {
        if (empty($data['type'])) {
            throw new ArgumentException("Не передано поле type в directorData");
        }
        return match ($data['type']) {
            'FL' => DataFL::init($data),
            default => throw new ArgumentException("Неподдерживаемый тип directorData: {$data['type']}"),
        };
    }
}