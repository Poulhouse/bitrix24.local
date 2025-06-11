<?php

namespace KPLab\API\V2\Model\DTO\Common;

class BankAccountDTO
{
    public ?string $crmId = null;
    public string $sellerInn;
    public string $title;
    public string $nameBank;
    public string $bankIdCode;
    public string $checkAccount;
    public ?string $adjAccount = null;
    public bool $isCurrent; //Расчетный счет
    public bool $isNominal; //Номинальный счет
    public bool $isPrimary; //Основной счет

    public static function required(): array
    {
        return [
            'title',
            'nameBank',
            'bankIdCode',
            'checkAccount',
            'isCurrent',
            'isNominal',
            'isPrimary',
        ];
    }
}