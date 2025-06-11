<?php namespace KPLab\API\V2\Model\Service;

use KPLab\API\V2\Model\Interface\ValidatableSyncObjectInterface;

class SyncObjectBuilder implements ValidatableSyncObjectInterface
{
    private array $requisite = [];
    private array $addresses = [];
    private array $bankDetails = [];
    private array $bankNominalDetails = [];
    private array $contactPersons = [];
    private array $contactDetails = [];
    private array $beneficialOwnersDetails = [];
    private string $representativeGuid;
    private string $orgType;
    private string $id;
    private string $limitSum;
    public function withCompanyData(array $company): self
    {
        // внутренний ID в 1С
        $this->id = trim((string)($company['UF_CRM_COMPANY_SS_AM_ID'] ?? ''));
        $limit = $company['UF_CRM_1697107946'] ?? '0|RUB';
        $this->limitSum = (float)str_replace('|RUB', '', $limit);
        return $this;
    }
    public function withRequisite(array $requisite): self
    {
        if (isset($requisite[0]) && is_array($requisite[0])) {
            $this->requisite = $requisite[0]; // берём первый
        } else {
            $this->requisite = $requisite;    // уже один реквизит
        }

        return $this;
    }
    public function withRepresentative(string $guid): self
    {
        $this->representativeGuid = $guid;
        return $this;
    }
    public function withBeneficialOwners(array $owners): self
    {
        $this->beneficialOwnersDetails = $owners ?? [];
        return $this;
    }
    public function withContactPersonDetails(array $contactPersons): self
    {
        $this->contactPersons = $contactPersons;
        return $this;
    }
    public function withContactDetails(array $contactDetails): self
    {
        $this->contactDetails = $contactDetails;
        return $this;
    }
    public function withAddresses(array $addresses): self
    {
        if (isset($addresses) && is_array($addresses)) {
            $this->addresses = $addresses;
        } else {
            $this->addresses = [];
        }
        return $this;
    }
    public function withBankDetails(array $banks): self
    {
        $this->bankDetails = $banks['bankDetails'] ?? [];
        $this->bankNominalDetails = $banks['bankNominalDetails'] ?? [];
        return $this;
    }
    public function withOrgType(string $orgType): self
    {
        $this->orgType = $orgType;
        return $this;
    }

    /**
     * Собирает и возвращает один syncObject
     *
     * @return array
     * @throws \DateMalformedStringException
     */
    public function build(): array
    {
        $rq = $this->requisite;
        $typeId = ($this->orgType === CompanyService::TYPE_ORG) ? 2 : 1;

        $syncObject = [
            'id' => $this->id,
            'limitSum' => $this->limitSum,
            'typeId' => $typeId,
            'taxNum' => $rq['RQ_INN'] ?? '',
        ];

        if ($this->orgType === CompanyService::TYPE_ORG) {
            $syncObject += [
                'name' => $rq['RQ_COMPANY_NAME'] ?? '',
                'shortName' => $rq['RQ_COMPANY_NAME'] ?? '',
                'fullName' => $rq['RQ_COMPANY_FULL_NAME'] ?? '',
                'regNum' => $rq['RQ_OGRN'] ?? '',
                'regDate' => $this->normalizeDate($rq['RQ_COMPANY_REG_DATE'] ?? ''),
                'regNumOrg' => $rq['UF_CRM_1688964741'] ?? '',
                'kpp' => $rq['RQ_KPP'] ?? '',
                'okpo' => $rq['RQ_OKPO'] ?? ''
            ];
        }

        // для ИП добавляем поля regMark/regNum/regDate/regNumOrg
        if ($this->orgType === CompanyService::TYPE_IP)
        {
            $syncObject += [
                'regMark' => 1,
                'regNum' => $rq['RQ_OGRNIP'] ?? '',
                'regDate' => $this->normalizeDate($rq['RQ_COMPANY_REG_DATE'] ?? ''),
                'regNumOrg' => $rq['UF_CRM_1688964741'] ?? '',
                'lastName'      => $rq['RQ_LAST_NAME']                   ?? '',
                'firstName'     => $rq['RQ_FIRST_NAME']                  ?? '',
                'middleName'    => $rq['RQ_SECOND_NAME']                 ?? '',
                'birthDate'     => $this->normalizeDate($rq['UF_CRM_1684493639'] ?? ''),
                'birthPlace'    => $rq['UF_CRM_1647929611']              ?? '',
                'socialNum'     => $rq['UF_CRM_1684476607']              ?? '',
                'docType'       => $this->mapDocType((int)($rq['UF_CRM_RQ_TYPE_OF_DOCUMENT'] ?? 0)),
                'docSeries'     => $rq['RQ_IDENT_DOC_SER']               ?? '',
                'docNumber'     => $rq['RQ_IDENT_DOC_NUM']               ?? '',
                'docIssueDate'  => $this->normalizeDate($rq['RQ_IDENT_DOC_DATE'] ?? ''),
                'docIssuerText' => $rq['RQ_IDENT_DOC_ISSUED_BY']         ?? '',
                'docDeptCode'   => $rq['RQ_IDENT_DOC_DEP_CODE']          ?? '',
            ];
        }
        if ($this->orgType === CompanyService::TYPE_FL)
        {
            $syncObject += [
                'regMark' => 0,
                'lastName'      => $rq['RQ_LAST_NAME']                   ?? '',
                'firstName'     => $rq['RQ_FIRST_NAME']                  ?? '',
                'middleName'    => $rq['RQ_SECOND_NAME']                 ?? '',
                'birthDate'     => $this->normalizeDate($rq['UF_CRM_1684493639'] ?? ''),
                'birthPlace'    => $rq['UF_CRM_1647929611']              ?? '',
                'socialNum'     => $rq['UF_CRM_1684476607']              ?? '',
                'docType'       => $this->mapDocType((int)($rq['UF_CRM_RQ_TYPE_OF_DOCUMENT'] ?? 0)),
                'docSeries'     => $rq['RQ_IDENT_DOC_SER']               ?? '',
                'docNumber'     => $rq['RQ_IDENT_DOC_NUM']               ?? '',
                'docIssueDate'  => $this->normalizeDate($rq['RQ_IDENT_DOC_DATE'] ?? ''),
                'docIssuerText' => $rq['RQ_IDENT_DOC_ISSUED_BY']         ?? '',
                'docDeptCode'   => $rq['RQ_IDENT_DOC_DEP_CODE']          ?? '',
            ];
        }

        // универсальные блоки
        if (!empty($this->representativeGuid))
        {
            $syncObject['representativeGuid'] = $this->representativeGuid;
        }
        if (!empty($this->contactDetails))
        {
            $syncObject['contactDetails'] = $this->contactDetails;
        }
        if (!empty($this->addresses))
        {
            $syncObject['addressDetails'] = $this->addresses;
        }
        if (!empty($this->bankDetails))
        {
            $syncObject['bankDetails'] = $this->bankDetails;
        }
        if (!empty($this->bankNominalDetails))
        {
            $syncObject['bankNominalDetails'] = $this->bankNominalDetails;
        }
        if (!empty($this->contactPersons))
        {
            $syncObject['contactPersonDetails'] = $this->contactPersons;
        }
        if (!empty($this->beneficialOwnersDetails))
        {
            $syncObject['beneficialOwnersDetails'] = $this->beneficialOwnersDetails;
        }

        return $syncObject;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function normalizeDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        return (new \DateTime($date))->format('d.m.Y');
    }
    private function mapDocType(int $identDoc): int
    {
        return match ($identDoc) {
            21907 => 21,
            21908 => 27,
            21944 => 12,
            21909 => 31,
            default => 0,
        };
    }

    public function validate(): self
    {
        if (empty($this->orgType)) {
            throw new \InvalidArgumentException("Тип компании (orgType) не задан.");
        }

        if (empty($this->requisite)) {
            throw new \InvalidArgumentException("Реквизиты отсутствуют.");
        }

        if (empty($this->addresses)) {
            throw new \InvalidArgumentException("Адреса не заданы.");
        }

        if ($this->orgType === CompanyService::TYPE_ORG) {
            if (empty($this->representativeGuid)) {
                throw new \InvalidArgumentException("Для ЮЛ не указан руководитель.");
            }
        }

        if (in_array($this->orgType, [CompanyService::TYPE_FL, CompanyService::TYPE_IP], true)) {
            $missingFields = [];
            if (empty($this->requisite['RQ_FIRST_NAME'])) {
                $missingFields[] = 'RQ_FIRST_NAME (Имя)';
            }
            if (empty($this->requisite['RQ_LAST_NAME'])) {
                $missingFields[] = 'RQ_LAST_NAME (Фамилия)';
            }
            if (empty($this->requisite['UF_CRM_1647929611'])) {
                $missingFields[] = 'UF_CRM_1647929611 (Место рождения)';
            }
            if (empty($this->requisite['UF_CRM_1684493639'])) {
                $missingFields[] = 'UF_CRM_1684493639 (Дата рождения)';
            }
            if (empty($this->requisite['UF_CRM_RQ_TYPE_OF_DOCUMENT'])) {
                $missingFields[] = 'UF_CRM_RQ_TYPE_OF_DOCUMENT (Документ УЛ)';
            }
            if (empty($this->requisite['RQ_IDENT_DOC_SER'])) {
                $missingFields[] = 'RQ_IDENT_DOC_SER (Серия документа)';
            }
            if (empty($this->requisite['RQ_IDENT_DOC_NUM'])) {
                $missingFields[] = 'RQ_IDENT_DOC_NUM (Номер документа)';
            }
            if (empty($this->requisite['RQ_IDENT_DOC_ISSUED_BY'])) {
                $missingFields[] = 'RQ_IDENT_DOC_ISSUED_BY (Кем выдано)';
            }
            if (empty($this->requisite['RQ_IDENT_DOC_DATE'])) {
                $missingFields[] = 'RQ_IDENT_DOC_DATE (Дата выдачи)';
            }
            if (empty($this->requisite['RQ_IDENT_DOC_DEP_CODE'])) {
                $missingFields[] = 'RQ_IDENT_DOC_DEP_CODE (Код подразделения)';
            }

            if (!empty($missingFields)) {
                throw new \InvalidArgumentException("Ошибка в реквизите '{$this->requisite['NAME']}': Отсутствуют обязательные поля:\n- " . implode("\n- ", $missingFields));
            }
        }

        return $this;
    }
}