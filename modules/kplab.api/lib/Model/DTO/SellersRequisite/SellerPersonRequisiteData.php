<?php namespace KPLab\API\V2\Model\DTO\SellersRequisite;

use KPLab\API\V2\Model\DTO\Sellers\SellerPersonDTO;

class SellerPersonRequisiteData implements Interface\SellerRequisiteDataInterface
{

    private int $entityId;
    private SellerPersonDTO $dto;

    public function __construct(int $entityId, SellerPersonDTO $dto)
    {
        $this->entityId = $entityId;
        $this->dto      = $dto;
    }


    public function getEntityTypeId(): int
    {
        return \CCrmOwnerType::Company;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getFields(): array
    {
        $typePrefix = ($this->dto->type == 'FL') ? '' : 'ИП ';

        $fullName = trim(
            ($typePrefix) .
            ($this->dto->lastName   ?? '') . ' ' .
            ($this->dto->firstName  ?? '') . ' ' .
            ($this->dto->secondName ?? '')
        );

        $passport = $this->dto->passport;

        return [
            'ENTITY_TYPE_ID' => $this->getEntityTypeId(),
            'ENTITY_ID' => $this->getEntityId(),
            'PRESET_ID' => 2,
            'AUTOCOMPLETE'                 => "{$this->dto->lastName} {$this->dto->firstName} {$this->dto->secondName}, ИНН {$this->dto->inn}",
            'TITLE'                        => $fullName,
            'NAME'                         => $fullName,
            'RQ_NAME'                      => $fullName,
            'RQ_FIRST_NAME'                => $this->dto->firstName,
            'RQ_LAST_NAME'                 => $this->dto->lastName,
            'RQ_SECOND_NAME'               => $this->dto->secondName,
            'UF_CRM_RQ_TYPE_OF_DOCUMENT'   => 21, // Тип: паспорт
            'RQ_IDENT_DOC_SER'             => $passport->series ?? '',
            'RQ_IDENT_DOC_NUM'             => $passport->number ?? '',
            'RQ_IDENT_DOC_DATE'            => $passport->issuedAt ?? '',
            'RQ_IDENT_DOC_ISSUED_BY'       => $passport->issuer ?? '',
            'RQ_IDENT_DOC_DEP_CODE'        => $passport->issuerCode ?? '',
            'UF_CRM_1647929611'            => $this->dto->birthPlace ?? '',
            'UF_CRM_1684493639'            => $this->dto->birthday ?? '',
            'RQ_INN'                       => $this->dto->inn,
            'RQ_OGRNIP'                    => $this->dto->ogrnip,
            'RQ_COMPANY_REG_DATE'          => $this->dto->companyRegDate ?? '',
            'UF_CRM_1688964741'            => $this->dto->fnsDepartment ?? '',
        ];
    }

    public function getBankDetails(): array
    {
        return $this->dto->bankAccounts ?? [];
    }

    public function getAddressDetails(): array
    {
        return array_map(fn($dto) => get_object_vars($dto), $this->dto->address ?? []);
    }
}