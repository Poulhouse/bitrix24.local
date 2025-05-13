<?php namespace KPLab\CRM;

class Deal {

    public int $entityId;
    public string $entityTypeName;
    public ?\Bitrix\Crm\Item $item;
    public ?\Bitrix\Crm\Service\Factory $factory;

    public function __construct($ID)
    {
        $this->entityId = $ID;
        $this->entityTypeName = 'DEAL';
        $this->factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
        $this->item = $this->factory->getItem($ID);
    }

    public function setData(array $data)
    {
        // Сохраняем GUID в пользовательское поле сделки
        $this->item->setFromCompatibleData($data);

        $operation = $this->factory->getUpdateOperation($this->item);
        $operation->disableAllChecks();
        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $saveResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $this->entityId,
                    "ENTITY_TYPE" => $this->entityTypeName,
                    "COMMENT" => "[b] {$message} [/b]"
                ]
            ]);
            return null;
        } else {
            return $saveResult;
        }

    }
}