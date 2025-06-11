<?php
namespace KPLab\API\V2\Model\DTO;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use InvalidArgumentException;

class PersonDTO
{
    public string $guid;
    public string $lastName;
    public string $firstName;
    public ?string $middleName;
    public string $shortName;
    public string $fullName;
    public string $birthDate;
    public ?string $birthPlace;
    public string $inn;
    public ?string $socialNum;
    public ?int $regMark;
    public ?string $regNum;
    public ?string $regDate;
    public ?string $regNumOrg;
    public int $docType;
    public string $docSeries;
    public string $docNumber;
    public string $docIssueDate;
    public string $docIssuerText;
    public string $docDeptCode;
    public ?float $limitSum;
    public array $addressDetails;
    public array $contactDetails;
    public array $contactPersonDetails;
    public array $bankDetails;
    public array $bankNominalDetails;
    public array $toCompanyFields;

    public static function createFromArray(array $data): self
    {
        $result = new self();
        $result->validate($data);

        $result->guid = $data['guid'] ?? '';
        $result->lastName = $data['lastName'];
        $result->firstName = $data['firstName'];
        $result->middleName = $data['middleName'] ?? null;
        $result->birthDate = $data['birthDate'];
        $result->birthPlace = $data['birthPlace'] ?? null;
        $result->inn = $data['inn'];
        $result->socialNum = $data['socialNum'] ?? null;
        $result->regMark = $data['regMark'];
        $result->regNum = $data['regNum'] ?? null;
        $result->regDate = $data['regDate'] ?? null;
        $result->regNumOrg = $data['regNumOrg'] ?? null;
        $result->docType = $data['docType'];
        $result->docSeries = $data['docSeries'];
        $result->docNumber = $data['docNumber'];
        $result->docIssueDate = $data['docIssueDate'];
        $result->docIssuerText = $data['docIssuerText'];
        $result->docDeptCode = $data['docDeptCode'];
        $result->limitSum = $data['limitSum'] ?? null;
        $result->addressDetails = $data['addressDetails'];
        $result->contactDetails = $data['contactDetails'] ?? [];
        $result->contactPersonDetails = $data['contactPersonDetails'] ?? [];
        $result->bankDetails = $data['bankDetails'] ?? [];
        $result->bankNominalDetails = $data['bankNominalDetails'] ?? [];

        return $result;
    }
    private function validate(array $data): void
    {
        $result = new Result();

        // Обязательные строковые поля
        $requiredStrings = [
            'lastName' => 'Фамилия',
            'firstName' => 'Имя',
            'inn' => 'ИНН',
            'regMark' => 'Является ли ИП?',
            'docSeries' => 'Серия документа',
            'docNumber' => 'Номер документа',
            'docIssuerText' => 'Кем выдан документ',
            'docDeptCode' => 'Код подразделения'
        ];

        foreach ($requiredStrings as $field => $name) {
            if (!isset($data[$field])) {
                $result->addError(new Error("{$name} [{$field}] обязателен для заполнения"));
            }
        }

        // Валидация дат
        $dateFields = [
            'birthDate' => 'Дата рождения',
            'docIssueDate' => 'Дата выдачи документа'
        ];

        foreach ($dateFields as $field => $name) {
            if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $data[$field] ?? '')) {
                $result->addError(new Error("{$name} должна быть в формате DD.MM.YYYY"));
            }
        }

        // Валидация СНИЛС
        if (!empty($data['socialNum'])) {
            $snils = $data['socialNum'];
            if (preg_match('/[^0-9- ]/', $snils)) {
                $result->addError(new Error('СНИЛС может состоять только из цифр, дефисов и пробелов'));
            } elseif (!preg_match('/^\d{3}-\d{3}-\d{3} \d{2}$/', $snils)) {
                $result->addError(new Error('СНИЛС должен быть в формате XXX-XXX-XXX XX'));
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

        if (!$result->isSuccess()) {
            throw new InvalidArgumentException(implode("\n", $result->getErrorMessages()));
        }
    }

    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'lastName' => $this->lastName,
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'birthDate' => $this->birthDate,
            'birthPlace' => $this->birthPlace,
            'inn' => $this->inn,
            'socialNum' => $this->socialNum,
            'regMark' => $this->regMark,
            'regNum' => $this->regNum,
            'regDate' => $this->regDate,
            'regNumOrg' => $this->regNumOrg,
            'docType' => $this->docType,
            'docSeries' => $this->docSeries,
            'docNumber' => $this->docNumber,
            'docIssueDate' => $this->docIssueDate,
            'docIssuerText' => $this->docIssuerText,
            'docDeptCode' => $this->docDeptCode,
            'limitSum' => $this->limitSum,
            'addressDetails' => $this->addressDetails,
            'contactDetails' => $this->contactDetails,
            'contactPersonDetails' => $this->contactPersonDetails,
            'bankDetails' => $this->bankDetails,
            'bankNominalDetails' => $this->bankNominalDetails,
        ];
    }

    public function toCompanyFields(): void
    {
        $fio = $this->lastName . " " . $this->firstName . " " . $this->middleName;
        $shortFio = $this->lastName . " " . mb_substr($this->firstName, 0, 1) . ". " . mb_substr($this->middleName, 0, 1).".";
        $this->shortName = ($this->regMark == "0") ? $shortFio : 'ИП '. $shortFio;
        $this->fullName = ($this->regMark == "0") ? $fio : 'Индивидуальный предприниматель '. $fio;

        $TypeId = null;
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()){
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => ($this->regMark == "0") ? 'FL' : 'IP']);
            while ($arUserFieldData = $res->fetch()) {
                $TypeId = $arUserFieldData['ID'];
            }
        }

        if($this->guid != "") {
            $this->toCompanyFields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $this->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_COMPANY_SS_AM_ID" => $this->guid,
                "UF_CRM_1697107946" => $this->limitSum."|RUB",
                "UF_CRM_1595595411835" => $this->fullName
            ];
        }
        else {
            $this->toCompanyFields = [
                "UF_CRM_1684145100226" => $TypeId,
                "UF_CRM_6433D7C925893" => $this->inn,
                "UF_CRM_COMPANY_SS_ORG" => [5],
                "UF_CRM_6433DBB98DD53" => 17611,
                "UF_CRM_1697107946" => $this->limitSum."|RUB",
                "UF_CRM_1595595411835" => $this->fullName
            ];
        }
    }
}