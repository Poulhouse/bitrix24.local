<?php namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\BankDetailTable;
use Bitrix\Crm\RequisiteTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\API\V2\Model\DTO\Requisite\RequisiteDataInterface;

class RequisiteService
{
    /**
     * Возвращает все реквизиты, привязанные к контактному лицу (Person).
     *
     * @param int $personId
     * @return array
     */
    public function load(int $personId): array
    {
        $result = RequisiteTable::getList([
            'filter' => [
                'ENTITY_ID'      => $personId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
            ],
            'select' => ['*', 'UF_*'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Возвращает все реквизиты, привязанные к ключу и значению.
     *
     * @param string $key
     * @param string $value
     * @return ?array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function loadByKey(string $key, string $value): ?array
    {
        return RequisiteTable::getRow([
            'filter' => [
                $key      => $value,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
            ],
            'select' => ['*', 'UF_*'],
        ]);
    }

    /**
     * @throws \Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function save(RequisiteDataInterface $data): int
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

    protected function emitEvent(string $name, int $rqId, array $fields): void
    {
        $event = new \Bitrix\Main\Event('crm', $name, ['id' => $rqId, 'fields' => $fields]);
        $event->send();
    }
}