<?php namespace KPLab\API\V2\DTO;

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

        // Валидация банковских реквизитов
        foreach ($data['beneficialOwnersDetails'] ?? [] as $beneficialOwnersDetail) {
            if (empty($beneficialOwnersDetail['id'])) {
                $result->addError(new Error('Идентификатор физического лица (id)'));
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
}