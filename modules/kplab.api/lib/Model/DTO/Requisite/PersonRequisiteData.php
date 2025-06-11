<?php namespace KPLab\API\V2\Model\DTO\Requisite;

use KPLab\API\V2\Model\DTO\PersonDTO;

class PersonRequisiteData implements RequisiteDataInterface
{
    private int $entityId;
    private PersonDTO $personDTO;

    public function __construct(int $entityId, PersonDTO $personDTO)
    {
        $this->entityId  = $entityId;
        $this->personDTO = $personDTO;
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
        $typePrefix = ($this->personDTO->regMark === 0) ? '' : 'ИП ';
        $docType = $this->personDTO->docType;

        $fullName = trim(
            ($typePrefix) .
            ($this->personDTO->lastName   ?? '') . ' ' .
            ($this->personDTO->firstName  ?? '') . ' ' .
            ($this->personDTO->middleName ?? '')
        );

        return [
            'ENTITY_TYPE_ID'               => $this->getEntityTypeId(),
            'ENTITY_ID'                    => $this->getEntityId(),
            'PRESET_ID'                    => 2, // IP/FL preset
            'AUTOCOMPLETE'                 => "{$this->personDTO->lastName} {$this->personDTO->firstName} {$this->personDTO->middleName}, ИНН {$this->personDTO->inn}",
            'TITLE'                        => $fullName,
            'NAME'                         => $fullName,
            'RQ_NAME'                      => $fullName,
            'RQ_FIRST_NAME'                => $this->personDTO->firstName,
            'RQ_LAST_NAME'                 => $this->personDTO->lastName,
            'RQ_SECOND_NAME'               => $this->personDTO->middleName,
            'UF_CRM_RQ_TYPE_OF_DOCUMENT'   => $this->mapIdentDoc($docType),
            'RQ_IDENT_DOC_SER'             => $this->personDTO->docSeries,
            'RQ_IDENT_DOC_NUM'             => $this->personDTO->docNumber,
            'RQ_IDENT_DOC_DATE'            => $this->personDTO->docIssueDate,
            'RQ_IDENT_DOC_ISSUED_BY'       => $this->personDTO->docIssuerText,
            'RQ_IDENT_DOC_DEP_CODE'        => $this->personDTO->docDeptCode,
            'UF_CRM_1647929611'            => $this->personDTO->birthPlace,
            'UF_CRM_1684493639'            => $this->personDTO->birthDate,
            'RQ_INN'                       => $this->personDTO->inn,
            'RQ_OGRNIP'                    => $this->personDTO->regNum,
            'RQ_COMPANY_REG_DATE'          => $this->personDTO->regDate,
            'UF_CRM_1688964741'            => $this->personDTO->regNumOrg,
        ];
    }

    public function getBankDetails(): array
    {
        $details = [];

        // current-account details
        foreach ($this->personDTO->bankDetails as $b)
        {
            $primary = ($b['bankAccountPrimaryMark'] == 1) ? 'Да' : 'Нет';
            $details[] = [
                'NAME'               => $b['bankAccountName'] ?? '',
                'RQ_ACC_NUM'         => $b['bankAccountId']   ?? '',
                'RQ_BIK'             => $b['bankId']          ?? '',
                'UF_CRM_PRIMARY_TXT' => $primary,
                'UF_CRM_BD_ACC_TYPE' => 'Расчетный',
            ];
        }

        // nominal-account details
        foreach ($this->personDTO->bankNominalDetails as $b)
        {
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
        return $this->personDTO->addressDetails;
    }
    private function mapIdentDoc(int $doctype): int
    {
        return match ($doctype) {
            21 => 21907,
            27 => 21908,
            12 => 21944,
            31 => 21909,
            default => 0,
        };
    }
}