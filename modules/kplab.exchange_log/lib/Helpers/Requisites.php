<?php

namespace Kplab\Exchange_log\Helpers;

use Bitrix\Crm\RequisiteTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

class Requisites
{
    /**
     * @throws \DateMalformedStringException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getRequisites(int $companyId): array
    {
        $req = [];
        $requisite = RequisiteTable::getList([
            'filter' => ['ENTITY_ID' => $companyId, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Company],
            'select' => ['*', 'UF_*'],
            'limit' => 1
        ])->fetch();

        if (!$requisite || !isset($requisite['ID'])) {
            return $req;
        }

        //ИП / ФЛ / СЗ
        if($requisite['PRESET_ID'] == 2) {
            $req = [
                'Фамилия'   => $requisite['RQ_LAST_NAME'] ?? '',
                'Имя'  => $requisite['RQ_FIRST_NAME'] ?? '',
                'Отчество' => $requisite['RQ_SECOND_NAME'] ?? '',
                'Дата рождения'  => self::normalizeDate($requisite['UF_CRM_1684493639'] ?? ''),
                'Место рождения' => $requisite['UF_CRM_1647929611'] ?? '',
                'СНИЛС'  => $requisite['UF_CRM_1684476607'] ?? '',
                'Документ УЛ'    => self::mapDocType((int)$requisite['UF_CRM_RQ_TYPE_OF_DOCUMENT']),
                'Серия'  => $requisite['RQ_IDENT_DOC_SER'] ?? '',
                'Номер'  => $requisite['RQ_IDENT_DOC_NUM'] ?? '',
                'Дата выдачи'  => self::normalizeDate($requisite['RQ_IDENT_DOC_DATE'] ?? ''),
                'Кем выдано' => $requisite['RQ_IDENT_DOC_ISSUED_BY'] ?? '',
                'Код подразделения'   => $requisite['RQ_IDENT_DOC_DEP_CODE'] ?? '',
                'ИНН' => $requisite['RQ_INN'] ?? '',
                'ОГРНИП' => $requisite['RQ_OGRNIP'] ?? '',
                'Дата регистрации' => self::normalizeDate($requisite['RQ_COMPANY_REG_DATE'] ?? ''),
                'Наименование регистрирующего органа (ФНС)' => $requisite['UF_CRM_1688964741'] ?? '',
            ];
        }

        //ООО
        if($requisite['PRESET_ID'] == 1) {
            $req = [
                'Название компании' => $requisite['RQ_COMPANY_NAME'],
                'Полное наименование организации' => $requisite['RQ_COMPANY_FULL_NAME'],
                'ИНН' => $requisite['RQ_INN'] ?? '',
                'КПП' => $requisite['RQ_KPP'] ?? '',
                'ОГРН' => $requisite['RQ_OGRN'],
                'Дата регистрации' => self::normalizeDate($requisite['RQ_COMPANY_REG_DATE'] ?? ''),
                'Наименование регистрирующего органа (ФНС)' => $requisite['UF_CRM_1688964741'] ?? '',
                'ОКПО' => $requisite['RQ_OKPO'] ?? ''
            ];
        }

        return $req;

    }

    /**
     * @throws \DateMalformedStringException
     */
    private static function normalizeDate(?string $value): ?string
    {
        return $value ? (new \DateTime($value))->format('d.m.Y') : null;
    }
    private static function mapDocType(int $identDoc): string
    {
        return match ($identDoc) {
            21907 => 'Паспорт гражданина Российской Федерации',
            21908 => 'Свидетельство о рождении гражданина Российской Федерации',
            21909 => 'Иностранный паспорт',
            21944 => 'Вид на жительство',
            default => 'Иной документ'
        };
    }
}