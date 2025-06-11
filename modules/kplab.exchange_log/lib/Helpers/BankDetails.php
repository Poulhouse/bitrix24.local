<?php
namespace Kplab\Exchange_log\Helpers;

use Bitrix\Crm\RequisiteTable;
use Bitrix\Crm\BankDetailTable;

class BankDetails
{
    public static function loadBankDetails(int $companyId): array
    {
        $requisite = RequisiteTable::getList([
            'filter' => ['ENTITY_ID' => $companyId, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Company],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();

        if (!$requisite || !isset($requisite['ID'])) {
            return [];
        }

        $rqId = $requisite['ID'];

        $bank = BankDetailTable::getList([
            'filter' => [
                'ENTITY_ID' => $rqId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                'UF_CRM_BD_ACC_TYPE' => 'Расчетный'
            ],
            'select' => ['*', 'UF_*'],
            'order' => ['ID' => 'DESC']
        ])->fetchAll();

        return $bank;
    }

    public static function loadBankNominalDetails(int $companyId): array
    {
        $requisite = RequisiteTable::getList([
            'filter' => ['ENTITY_ID' => $companyId, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Company],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();

        if (!$requisite || !isset($requisite['ID'])) {
            return [];
        }

        $rqId =$requisite['ID'];

        $bank = BankDetailTable::getList([
            'filter' => [
                'ENTITY_ID' => $rqId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                'UF_CRM_BD_ACC_TYPE' => 'Номинальный'
            ],
            'select' => ['*', 'UF_*']
        ])->fetchAll();

        return $bank;
    }
}
