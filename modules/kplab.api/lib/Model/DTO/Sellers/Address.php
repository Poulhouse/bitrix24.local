<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 * @OA\Schema(
 *   schema="Address",
 *   type="object",
 *   required={"type","fiasId"},
 *   @OA\Property(property="type",   type="string", enum={"registration","actual","legal"}, example="registration"),
 *   @OA\Property(property="fiasId", type="string", example="6c37c61c-e195-4651-b4fe-0707efc77be6")
 * )
 */
class Address
{
    public string $type;
    public string $fiasId;

    /**
     * @throws ArgumentException
     */
    public static function init(array $data): self
    {
        if (empty($data['type']) || empty($data['fiasId'])) {
            throw new ArgumentException("Неполные данные Address");
        }
        $a = new self();
        $a->type   = (string)$data['type'];
        $a->fiasId = (string)$data['fiasId'];
        return $a;
    }
}