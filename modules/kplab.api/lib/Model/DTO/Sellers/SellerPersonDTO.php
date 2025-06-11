<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

use KPLab\API\V2\Model\DTO\Common\AddressDTO;
use KPLab\API\V2\Model\DTO\Common\BankAccountDTO;
use KPLab\API\V2\Model\DTO\Common\DocumentDTO;
use KPLab\API\V2\Model\DTO\Common\PassportDTO;
use KPLab\API\V2\Interfaces\AbstractSeller;

class SellerPersonDTO implements AbstractSeller
{
    public string $type;
    public string $inn;
    public ?string $ogrnip = null;
    public ?string $okpo = null;
    public ?string $okved = null;
    public ?string $companyRegDate = null;
    public ?string $fnsDepartment = null;

    public string $firstName;
    public string $lastName;
    public ?string $secondName = null;
    public ?string $birthday = null;
    public ?string $birthPlace = null;
    public string $serviceEDO;
    public bool $isManual = false;

    /** @var AddressDTO[] */
    public array $address = [];

    public ?PassportDTO $passport = null;

    /** @var string[]|null */
    public ?array $marketplaceLinks = null;

    /** @var DocumentDTO[]|null */
    public ?array $documents = null;

    /** @var BankAccountDTO[]|null */
    public ?array $bankAccounts = null;

    public static function required(): array
    {
        return [
            'type',
            'inn',
            'firstName',
            'lastName',
            'serviceEDO',
        ];
    }

    public function toArray(): array
    {
        return [
            'type'              => $this->type,
            'inn'               => $this->inn,
            'ogrnip'            => $this->ogrnip,
            'okpo'              => $this->okpo,
            'okved'             => $this->okved,
            'companyRegDate'    => $this->companyRegDate,
            'fnsDepartment'     => $this->fnsDepartment,
            'lastName'          => $this->lastName,
            'serviceEDO'        => $this->serviceEDO,
            'firstName'         => $this->firstName,
            'secondName'        => $this->secondName,
            'birthPlace'        => $this->birthPlace,
            'birthday'          => $this->birthday,
            'passport'          => $this->passport instanceof PassportDTO
                ? $this->passport->toArray()
                : [],
            'bankAccounts'      => array_map(
                fn($bank) => method_exists($bank, 'toArray') ? $bank->toArray() : (array)$bank,
                $this->bankAccounts ?? []
            ),
            'address'           => array_map(
                fn($addr) => method_exists($addr, 'toArray') ? $addr->toArray() : (array)$addr,
                $this->address ?? []
            ),
        ];
    }

}