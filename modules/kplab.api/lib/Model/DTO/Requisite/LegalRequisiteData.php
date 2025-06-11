<?php

namespace KPLab\API\V2\Model\DTO\Requisite;

use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\API\V2\Model\DTO\Sellers\SellerLegalEntityDTO;

class LegalRequisiteData implements RequisiteDataInterface
{
    private int $entityId;
    private LegalDTO|SellerLegalEntityDTO $legalDTO;

    public function __construct(int $entityId, LegalDTO|SellerLegalEntityDTO $legalDTO)
    {
        $this->entityId = $entityId;
        $this->legalDTO  = $legalDTO;
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
        $regDate = date('d.m.Y', strtotime($this->legalDTO->regDate));

        return [
            'ENTITY_TYPE_ID'             => $this->getEntityTypeId(),
            'ENTITY_ID'                  => $this->getEntityId(),
            'PRESET_ID'                  => 1,
            'AUTOCOMPLETE'               => "{$this->legalDTO->shortName}, ИНН {$this->legalDTO->inn}",
            'TITLE'                      => $this->legalDTO->shortName,
            'NAME'                       => $this->legalDTO->shortName,
            'RQ_INN'                     => $this->legalDTO->inn,
            'RQ_KPP'                     => $this->legalDTO->kpp,
            'RQ_OGRN'                    => $this->legalDTO->regNum,
            'RQ_OKPO'                    => $this->legalDTO->okpo,
            'RQ_COMPANY_REG_DATE'        => $regDate,
            'UF_CRM_1688964741'          => $this->legalDTO->regNumOrg,
            'RQ_COMPANY_NAME'            => $this->legalDTO->shortName,
            'RQ_COMPANY_FULL_NAME'       => $this->legalDTO->fullName,
        ];
    }

    public function getBankDetails(): array
    {
        $details = [];

        // расчётные счета
        foreach ($this->legalDTO->bankDetails as $b) {
            $primary = ($b['bankAccountPrimaryMark'] == 1) ? 'Да' : 'Нет';
            $details[] = [
                'NAME'               => $b['bankAccountName'] ?? '',
                'RQ_ACC_NUM'         => $b['bankAccountId']   ?? '',
                'RQ_BIK'             => $b['bankId']          ?? '',
                'UF_CRM_PRIMARY_TXT' => $primary,
                'UF_CRM_BD_ACC_TYPE' => 'Расчетный',
            ];
        }

        // номинальные счета
        foreach ($this->legalDTO->bankNominalDetails as $b) {
            $primary = ($b['bankAccountPrimaryMark'] == 1) ? 'Да' : 'Нет';
            $details[] = [
                'NAME'               => $b['bankAccountName'] ?? '',
                'RQ_ACC_NUM'         => $b['bankAccountId']   ?? '',
                'RQ_BIK'             => $b['bankId']          ?? '',
                'UF_CRM_PRIMARY_TXT' => $primary,
                'UF_CRM_BD_ACC_TYPE' => 'Номинальный',
            ];
        }

        return $details;
    }

    public function getAddressDetails(): array
    {
        return $this->legalDTO->addressDetails;
    }
}