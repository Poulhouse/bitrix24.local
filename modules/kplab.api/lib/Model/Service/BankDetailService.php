<?php namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\BankDetailTable;

class BankDetailService
{
    private RequisiteService $requisiteService;
    public function __construct()
    {
        $this->requisiteService = new RequisiteService;
    }
    /**
     * Возвращает расчётные и номинальные счета для Person.
     *
     * @param int $entityId
     * @return array
     */
    public function load(int $entityId): array
    {
        // 1) находим ID реквизита
        $rqList = $this->requisiteService->load($entityId);
        $rqId   = $rqList[0]['ID'] ?? null;
        if (!$rqId) {
            return [];
        }

        // 2) тянем из таблицы BankDetailTable

        $res = BankDetailTable::getList([
            'filter' => [
                'ENTITY_ID'      => $rqId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
            ],
            'select' => ['*', 'UF_*'],
            'order'  => ['ID'=>'ASC']
        ]);

        return $this->extractedBankDetails($res);
    }

    /** @see CompanyService::extractedBankDetails() */
    private function extractedBankDetails($result): array
    {
        $out = [
            'bankDetails' => [],
            'bankNominalDetails' => [],
        ];
        $rows = $result->fetchAll();
        foreach ($rows as $i => $r) {
            $item = [
                'bankId'                => $r['RQ_BIK'],
                'bankAccountId'         => $r['RQ_ACC_NUM'],
                'bankAccountName'       => $r['NAME'],
                'bankAccountPrimaryMark'=> ($r['UF_CRM_PRIMARY_TXT'] === 'Да') ? 1 : 0,
            ];
            if ($r['UF_CRM_BD_ACC_TYPE'] === 'Номинальный') {
                $out['bankNominalDetails'][] = $item;
            } else {
                $out['bankDetails'][] = $item;
            }

        }
        return $out;
    }
    public function process(int $rqId, array $bankDetails): void
    {
        // удаляем старые
        $existing = BankDetailTable::getList([
            'filter' => ['ENTITY_ID' => $rqId, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite],
            'select' => ['ID']
        ])->fetchAll();

        foreach ($existing as $row) {
            BankDetailTable::delete($row['ID']);
        }

        // добавляем новые
        foreach ($bankDetails as $b) {
            $res = BankDetailTable::add(array_merge($b, [
                'ENTITY_ID'      => $rqId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
            ]));
            if ($res->isSuccess()) {
                $e = new \Bitrix\Main\Event('crm','OnAfterBankDetailAdd',['id'=>$rqId,'fields'=>$b]);
                $e->send();
            }
        }
    }
}