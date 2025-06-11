<?php namespace KPLab\API\V2\Model\DTO\SellersRequisite;


use KPLab\API\V2\Model\DTO\Sellers\SellerLegalEntityDTO;
class SellerLegalRequisiteData implements Interface\SellerRequisiteDataInterface
{
    private int $entityId;
    private SellerLegalEntityDTO $dto;

    public function __construct(int $entityId, SellerLegalEntityDTO $dto)
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
        return [
            'ENTITY_TYPE_ID' => $this->getEntityTypeId(),
            'ENTITY_ID' => $this->getEntityId(),
            'PRESET_ID' => 1,
            'TITLE' => $this->dto->companyName,
            'NAME' => $this->dto->companyName,
            'RQ_COMPANY_NAME' => $this->dto->companyName,
            'RQ_COMPANY_FULL_NAME' => $this->dto->companyFullName,
            'RQ_INN' => $this->dto->inn,
            'RQ_KPP' => $this->dto->kpp,
            'RQ_OGRN' => $this->dto->ogrn,
            'RQ_OKPO' => $this->dto->okpo,
            'RQ_COMPANY_REG_DATE' => $this->dto->companyRegDate,
            'UF_CRM_1688964741' => $this->dto->fnsDepartment,
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