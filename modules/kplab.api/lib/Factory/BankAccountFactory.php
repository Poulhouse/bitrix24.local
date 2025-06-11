<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Model\DTO\Common\BankAccountDTO;

class BankAccountFactory
{
    public static function fromArray(array $data): BankAccountDTO
    {
        $dto = new BankAccountDTO();
        $dto->crmId         = $data['crmId'] ?? null;
        $dto->sellerInn     = $data['sellerInn'] ?? '';
        $dto->title         = $data['title'] ?? '';
        $dto->nameBank      = $data['nameBank'] ?? '';
        $dto->bankIdCode    = $data['bankIdCode'] ?? '';
        $dto->checkAccount  = $data['checkAccount'] ?? '';
        $dto->adjAccount    = $data['adjAccount'] ?? null;

        return $dto;
    }

    /**
     * @param array $items
     * @return BankAccountDTO[]
     */
    public static function many(array $items): array
    {
        return array_map([self::class, 'fromArray'], $items);
    }
}