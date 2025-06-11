<?php

namespace KPLab\API\V2\Factory;

use KPLab\API\V2\Interfaces\AbstractSeller;
use KPLab\API\V2\Model\DTO\Sellers\SellerPersonDTO;
use KPLab\API\V2\Model\DTO\Sellers\SellerLegalEntityDTO;

use KPLab\API\V2\Factory\AddressFactory;
use KPLab\API\V2\Factory\PassportFactory;
use KPLab\API\V2\Factory\DocumentFactory;
use KPLab\API\V2\Factory\BankAccountFactory;

class SellerDtoFactory
{
    public static function create(array $data): AbstractSeller
    {
        if (!isset($data['sellerData']['type'])) {
            throw new \InvalidArgumentException("Missing required 'type' field.");
        }

        return match ($data['sellerData']['type']) {
            'UL' => self::createUL($data),
            'IP', 'FL' => self::createPerson($data),
            default => throw new \InvalidArgumentException("Unsupported type: {$data['sellerData']['type']}"),
        };
    }

    public static function createPerson(array $data): SellerPersonDTO
    {
        $dto = new SellerPersonDTO();

        $dto->type            = $data['type'] ?? '';
        $dto->inn             = $data['inn'] ?? '';
        $dto->ogrnip          = $data['ogrnip'] ?? null;
        $dto->okpo            = $data['okpo'] ?? null;
        $dto->okved           = $data['okved'] ?? null;
        $dto->companyRegDate  = $data['companyRegDate'] ?? null;
        $dto->fnsDepartment   = $data['fnsDepartment'] ?? null;
        $dto->firstName       = $data['firstName'] ?? '';
        $dto->lastName        = $data['lastName'] ?? '';
        $dto->secondName      = $data['secondName'] ?? null;
        $dto->birthday        = $data['birthday'] ?? null;
        $dto->birthPlace      = $data['birthPlace'] ?? null;
        $dto->serviceEDO      = $data['serviceEDO'] ?? '';
        $dto->isManual        = $data['isManual'] ?? false;
        $dto->marketplaceLinks = $data['marketplaceLinks'] ?? null;

        $dto->address         = isset($data['address']) && is_array($data['address'])
            ? AddressFactory::many($data['address']) : [];

        $dto->passport = isset($data['passport']) && is_array($data['passport'])
            ? (
            isset($data['passport'][0]) && is_array($data['passport'][0])
                ? PassportFactory::many($data['passport'])    // массив паспортов
                : PassportFactory::fromArray($data['passport']) // один паспорт
            )
            : null;

        $dto->documents       = isset($data['documents']) && is_array($data['documents'])
            ? DocumentFactory::many($data['documents']) : null;

        $dto->bankAccounts    = isset($data['bankAccounts']) && is_array($data['bankAccounts'])
            ? BankAccountFactory::many($data['bankAccounts']) : null;

        return $dto;
    }

    public static function createUL(array $data): SellerLegalEntityDTO
    {
        $dto = new SellerLegalEntityDTO();

        $dto->type              = 'UL';
        $dto->inn               = $data['sellerData']['inn'] ?? '';
        $dto->kpp               = $data['sellerData']['kpp'] ?? null;
        $dto->companyName       = $data['sellerData']['companyName'] ?? '';
        $dto->companyFullName   = $data['sellerData']['companyFullName'] ?? null;
        $dto->companyRegDate    = $data['sellerData']['companyRegDate'] ?? '';
        $dto->fnsDepartment     = $data['sellerData']['fnsDepartment'] ?? null;
        $dto->ogrn              = $data['sellerData']['ogrn'] ?? null;
        $dto->okpo              = $data['sellerData']['okpo'] ?? null;
        $dto->oktmo             = $data['sellerData']['oktmo'] ?? null;
        $dto->okved             = $data['sellerData']['okved'] ?? null;
        $dto->serviceEDO        = $data['sellerData']['serviceEDO'] ?? '';
        $dto->marketplaceLinks  = $data['sellerData']['marketplaceLinks'] ?? null;

        $dto->address = isset($data['sellerData']['address']) && is_array($data['sellerData']['address'])
            ? AddressFactory::many($data['sellerData']['address']) : [];

        if (!isset($data['directorData']) || !is_array($data['directorData']) || empty($data['directorData'])) {
            throw new \InvalidArgumentException("Missing or empty 'directorData' in UL");
        }
        $dto->directorData = self::createPerson($data['directorData']);

        $dto->beneficiars = isset($data['beneficiars']) && is_array($data['beneficiars'])
            ? array_map(fn(array $b) => self::createPerson($b), $data['beneficiars']) : [];

        $dto->documents = isset($data['sellerData']['documents']) && is_array($data['sellerData']['documents'])
            ? DocumentFactory::many($data['sellerData']['documents']) : null;

        $dto->bankAccounts = isset($data['sellerData']['bankAccounts']) && is_array($data['sellerData']['bankAccounts'])
            ? BankAccountFactory::many($data['sellerData']['bankAccounts']) : null;

        return $dto;
    }
}