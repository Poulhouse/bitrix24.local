<?php namespace KPLab\API\V2\Model\DTO\SellersRequisite\Interface;

interface SellerRequisiteDataInterface
{
    public function getEntityTypeId(): int;
    public function getEntityId(): int;
    public function getFields(): array;
    public function getBankDetails(): array;
    public function getAddressDetails(): array;

}