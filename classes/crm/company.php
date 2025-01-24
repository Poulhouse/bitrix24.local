<?php namespace KPLab\CRM;

class Company {

    public int $entityId;
    public $item;

    public function __construct($ID)
    {
        $this->entityId = $ID;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $this->item = $factory->getItem($ID);
    }

    public function setMobilePhone($phoneNumber): void
    {
        $newId = $this->entityId;
        if($newId){
            $MF = new \CCrmFieldMulti;
            $MF->Add([
                'ENTITY_ID' => 'COMPANY',
                'ELEMENT_ID' => $newId,
                'VALUE' => $phoneNumber,
                'TYPE_ID' => 'PHONE', //EMAIL - телефон
                'VALUE_TYPE' => 'MOBILE',
            ]);
        }
    }
}