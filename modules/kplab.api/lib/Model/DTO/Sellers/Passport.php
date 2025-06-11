<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;
/**
 *      @OA\Schema(schema="Passport", type="object",
 *         @OA\Property(property="files", ref="#/components/schemas/PassportFiles"),
 *         @OA\Property(property="issuer", type="string", example="ОВД Пресненского района г. Москвы"),
 *         @OA\Property(property="number", type="string", example="123456"),
 *         @OA\Property(property="series", type="string", example="4510"),
 *         @OA\Property(property="issuedAt", type="string", format="date", example="2005-06-15"),
 *         @OA\Property(property="issuerCode", type="string", example="770-001")
 *      )
 */

/**
 *      @OA\Schema(schema="PassportFiles", type="object",
 *          @OA\Property(property="fileName", type="string", example="charter.pdf"),
 *          @OA\Property(property="file", type="string", format="binary")
 *      )
 *
 */
class Passport
{
    public array $files;
    public string $issuer;
    public string $number;
    public string $series;
    public string $issuedAt;
    public string $issuerCode;

    /**
     * @throws ArgumentException
     */
    public static function init(array $data): self
    {
        if (empty($data['files'])
            || empty($data['issuer'])
            || empty($data['number'])
            || empty($data['series'])
            || empty($data['issuedAt'])
            || empty($data['issuerCode'])
        ) {
            throw new ArgumentException("Неполные данные Passport");
        }
        $p = new self();
        $p->files = $data['files'];
        $p->issuer     = $data['issuer'];
        $p->number     = $data['number'];
        $p->series     = $data['series'];
        $p->issuedAt     = $data['issuedAt'];
        $p->issuerCode     = $data['issuerCode'];
        return $p;
    }
}