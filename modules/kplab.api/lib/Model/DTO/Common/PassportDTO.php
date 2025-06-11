<?php

namespace KPLab\API\V2\Model\DTO\Common;

class PassportDTO
{
    public string $issuer;
    public string $number;
    public string $series;
    public string $issuedAt;
    public string $issuerCode;

    /** @var FileDTO[] */
    public array $files = [];

    public static function required(): array
    {
        return [
            'series',
            'number',
            'issuedAt',
            'issuer',
            'issuerCode',
        ];
    }

    public function toArray(): array
    {
        return [
            'series'     => $this->series,
            'number'     => $this->number,
            'issuedAt'   => $this->issuedAt,
            'issuer'     => $this->issuer,
            'issuerCode' => $this->issuerCode,
        ];
    }
}