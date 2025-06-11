<?php namespace KPLab\API\V2\Model\DTO;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use InvalidArgumentException;

class LegalDTO
{
    public string $guid;
    public string $fullName;
    public string $shortName;
    public string $inn;
    public string $kpp;
    public string $okpo;
    public ?int $typeId = null;
    public string $regNum;
    public ?string $regDate;
    public ?string $regNumOrg;
    public ?float $limitSum;
    public string $representativeGuid;
    public array $addressDetails;
    public array $contactDetails;
    public array $contactPersonDetails;
    public array $bankDetails;
    public array $bankNominalDetails;
    public array $beneficialOwnersDetails;
    public string $representative;
    public array $toCompanyFields;

    public static function init(array $data): self
    {
        $result = new self();
        $result->validate($data);

        $result->guid = $data['guid'] ?? '';
        $result->fullName = $data['fullName'];
        $result->shortName = $data['shortName'];
        $result->inn = $data['inn'];
        $result->kpp = $data['kpp'];
        $result->okpo = $data['okpo'];
        $result->regNum = $data['regNum'] ?? null;
        $result->regDate = $data['regDate'] ?? null;
        $result->regNumOrg = $data['regNumOrg'] ?? null;
        $result->limitSum = $data['limitSum'] ?? null;
        $result->representativeGuid = $data['representativeGuid'] ?? '';
        $result->addressDetails = $data['addressDetails'] ?? [];
        $result->contactDetails = $data['contactDetails'] ?? [];
        $result->contactPersonDetails = $data['contactPersonDetails'] ?? [];
        $result->bankDetails = $data['bankDetails'] ?? [];
        $result->bankNominalDetails = $data['bankNominalDetails'] ?? [];
        $result->beneficialOwnersDetails = $data['beneficialOwnersDetails'] ?? [];

        return $result;
    }
    private function validate(array $data): void
    {
        $result = new Result();

        // Обязательные строковые поля
        $requiredStrings = [
            'fullName' => 'Полное наименование',
            'shortName' => 'Сокращенное наименование',
            'inn' => 'ИНН',
            'kpp' => 'КПП',
            'okpo' => 'ОКПО',
            'regNum' => 'ОГРН',
            'regDate' => 'Дата регистрации',
            'regNumOrg' => 'Регистрирующий орган ОГРН',
            'representativeGuid' => 'Директор (guid)',
        ];

        foreach ($requiredStrings as $field => $name) {
            if (!isset($data[$field])) {
                $result->addError(new Error("{$name} [{$field}] обязателен для заполнения"));
            }
        }

        // Валидация адресов
        if (empty($data['addressDetails'])) {
            $result->addError(new Error('Необходимо указать хотя бы один адрес'));
        }
        else {
            foreach ($data['addressDetails'] as $address) {
                if (empty($address['addressType'])) {
                    $result->addError(new Error('Адрес должен содержать тип адреса (addressType)'));
                }
                if (empty($address['location'])) {
                    $result->addError(new Error('Адрес должен содержать город (location)'));
                }
            }
        }

        // Валидация контактов
        foreach ($data['contactDetails'] ?? [] as $contact) {
            if (empty($contact['typeId'])) {
                $result->addError(new Error('Контакт должен содержать тип контактных данных (typeId)'));
            }
            if (empty($contact['valueType'])) {
                $result->addError(new Error('Контакт должен содержать вид контактных данных (valueType)'));
            }
            if (empty($contact['valueText'])) {
                $result->addError(new Error('Контакт должен содержать значение контактных данных (valueText)'));
            }
        }

        // Валидация банковских реквизитов
        foreach ($data['bankDetails'] ?? [] as $account) {
            if (empty($account['bankId'])) {
                $result->addError(new Error('Расчетный счет должен содержать БИК банка (bankId)'));
            }
            if (empty($account['bankAccountId'])) {
                $result->addError(new Error('Расчетный счет должен содержать Номер счета (bankAccountId)'));
            }
            if (empty($account['bankAccountName'])) {
                $result->addError(new Error('Расчетный счет должен содержать Название счета (bankAccountName)'));
            }
        }
        foreach ($data['bankNominalDetails'] ?? [] as $account) {
            if (empty($account['bankId'])) {
                $result->addError(new Error('Номинальный счет должен содержать БИК банка (bankId)'));
            }
            if (empty($account['bankAccountId'])) {
                $result->addError(new Error('Номинальный счет должен содержать Номер счета (bankAccountId)'));
            }
            if (empty($account['bankAccountName'])) {
                $result->addError(new Error('Номинальный счет должен содержать Название счета (bankAccountName)'));
            }
        }

        // Валидация бенефициаров
        foreach ($data['beneficialOwnersDetails'] ?? [] as $beneficialOwnersDetail) {
            if (empty($beneficialOwnersDetail['guid'])) {
                $result->addError(new Error('Идентификатор физического лица у бенефициара (guid)'));
            }
        }

        if (!$result->isSuccess()) {
            throw new InvalidArgumentException(implode("\n", $result->getErrorMessages()));
        }
    }
    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'fullName' => $this->fullName,
            'shortName' => $this->shortName,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'okpo' => $this->okpo,
            'regNum' => $this->regNum,
            'regDate' => $this->regDate,
            'regNumOrg' => $this->regNumOrg,
            'limitSum' => $this->limitSum,
            'representativeGuid' => $this->representativeGuid,
            'addressDetails' => $this->addressDetails,
            'contactDetails' => $this->contactDetails,
            'contactPersonDetails' => $this->contactPersonDetails,
            'bankDetails' => $this->bankDetails,
            'bankNominalDetails' => $this->bankNominalDetails,
            'beneficialOwnersDetails' => $this->beneficialOwnersDetails
        ];
    }
    public function toCompanyFields(string $prefix = ''): void
    {
        $this->getRepresentative($this->representativeGuid);
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()){
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => 'ORG']);
            while ($arUserFieldData = $res->fetch()) {
                $this->typeId = $arUserFieldData['ID'];
            }
        }
        if($this->guid != "") {
            $this->toCompanyFields = [
                "UF_CRM_1684145100226" => $this->typeId,
                "UF_CRM_6433D7C925893" => $this->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_COMPANY_SS_AM_ID" => $this->guid,
                "UF_CRM_1615200179" => $prefix.$this->representative,
                "UF_CRM_1697107946" => $this->limitSum."|RUB",
                "UF_CRM_1595595411835", $this->fullName
            ];
        }
        else {
            $this->toCompanyFields = [
                "UF_CRM_1684145100226" => $this->typeId,
                "UF_CRM_6433D7C925893" => $this->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_1615200179" => $prefix.$this->representative,
                "UF_CRM_1697107946" => $this->limitSum."|RUB",
                "UF_CRM_1595595411835", $this->fullName
            ];
        }
    }
    public function getRepresentative(?string $representativeGuid): int
    {
        if (!$representativeGuid) {
            throw new \InvalidArgumentException("GUID директора не передан.");
        }

        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $companies = $factoryCompany->getItems([
            'filter' => ['UF_CRM_COMPANY_SS_AM_ID' => $representativeGuid],
            'select' => ['ID'],
            'limit'  => 1,
        ]);

        foreach ($companies as $company) {
            $this->representative = $company->getId();
            return $this->representative;
        }

        throw new \RuntimeException("Директор с GUID '{$representativeGuid}' не найден в CRM Битрикс24.");
    }
}