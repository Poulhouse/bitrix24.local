<?php namespace KPLab\API\V2\Model\Service\Sellers;

use Bitrix\Crm\RequisiteTable;
use KPLab\API\V2\Model\DTO\SellersRequisite\Interface\SellerRequisiteDataInterface;
use KPLab\API\V2\Model\Service\AddressService;
use KPLab\API\V2\Model\Service\BankDetailService;

class SellerRequisiteService
{
    /**
     * Сохраняет (create|update) реквизит селлера.
     *
     * @param SellerRequisiteDataInterface $data
     * @return int ID реквизита
     */
    public function save(SellerRequisiteDataInterface $data): int
    {
        $entityTypeId = $data->getEntityTypeId();
        $entityId     = $data->getEntityId();

        // 1) Ищем или создаём реквизит
        $rq = RequisiteTable::getList([
            'filter' => [
                'ENTITY_TYPE_ID' => $entityTypeId,
                'ENTITY_ID'      => $entityId
            ],
            'select' => ['ID']
        ])->fetch();

        $fields = $data->getFields();
        if ($rq)
        {
            RequisiteTable::update($rq['ID'], $fields);
            $rqId = $rq['ID'];
            $this->emitEvent('OnAfterRequisiteUpdate', $rqId, $fields);
        }
        else
        {
            $res = RequisiteTable::add($fields);
            $rqId = $res->getId();
            $this->emitEvent('OnAfterRequisiteAdd', $rqId, $fields);
        }

        // 2) Банковские реквизиты
        (new BankDetailService())->process($rqId, $data->getBankDetails());

        // 3) Адреса реквизита
        (new AddressService())->process($rqId, $entityId, $data->getAddressDetails());

        return $rqId;
    }

    /**
     * Возвращает все реквизиты для селлера.
     *
     * @param int $entityId
     * @param int $entityTypeId
     * @return array
     */
    public function load(int $entityId, int $entityTypeId): array
    {
        $result = RequisiteTable::getList([
            'filter' => [
                'ENTITY_TYPE_ID' => $entityTypeId,
                'ENTITY_ID'      => $entityId
            ],
            'select' => ['*', 'UF_*']
        ]);

        return $result->fetchAll();
    }

    /**
     * Отправка кастомного события (Bitrix Event).
     */
    protected function emitEvent(string $name, int $id, array $fields): void
    {
        $event = new \Bitrix\Main\Event('crm', $name, ['id' => $id, 'fields' => $fields]);
        $event->send();
    }
}