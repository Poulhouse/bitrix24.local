<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

use KPLab\API\V2\Model\DTO\Common\AddressDTO;
use KPLab\API\V2\Model\DTO\Common\BankAccountDTO;
use KPLab\API\V2\Model\DTO\Common\DocumentDTO;

use KPLab\API\V2\Interfaces\AbstractSeller;

class SellerLegalEntityDTO implements AbstractSeller
{
    public string $type = 'UL';
    public string $inn;
    public ?string $kpp = null;
    public string $companyName;
    public ?string $companyFullName = null;
    public string $companyRegDate;
    public ?string $fnsDepartment = null;
    public ?string $ogrn = null;
    public ?string $okpo = null;
    public ?string $oktmo = null;
    public ?string $okved = null;
    public string $serviceEDO;

    /** @var string[]|null */
    public ?array $marketplaceLinks = null;

    /** @var AddressDTO[] */
    public array $address = [];

    public ?SellerPersonDTO $directorData = null;

    /** @var SellerPersonDTO[] */
    public array $beneficiars = [];

    /** @var DocumentDTO[]|null */
    public ?array $documents = null;

    /** @var BankAccountDTO[]|null */
    public ?array $bankAccounts = null;

    public static function required(): array
    {
        return [
            'type',
            'inn',
            'companyName',
            'companyRegDate',
            'serviceEDO',
            'directorData',
        ];
    }

    public function toArray(): array
    {
        return [
            'type'              => $this->type,
            'inn'               => $this->inn,
            'kpp'               => $this->kpp,
            'ogrn'              => $this->ogrn,
            'okpo'              => $this->okpo,
            'oktmo'             => $this->oktmo,
            'okved'             => $this->okved,
            'companyName'       => $this->companyName,
            'companyFullName'   => $this->companyFullName,
            'companyRegDate'    => $this->companyRegDate,
            'fnsDepartment'     => $this->fnsDepartment,
            'serviceEDO'        => $this->serviceEDO,
            'bankAccounts'      => array_map(
                fn($bank) => method_exists($bank, 'toArray') ? $bank->toArray() : (array)$bank,
                $this->bankAccounts ?? []
            ),
            'address'           => array_map(
                fn($addr) => method_exists($addr, 'toArray') ? $addr->toArray() : (array)$addr,
                $this->address ?? []
            ),
            'directorData'      => $this->directorData, // зависит от реализации
            'beneficiars'       => $this->beneficiars,
        ];
    }


}