<?php namespace KPLab\API\V2\Model\DTO\Requisite;

interface RequisiteDataInterface
{
    public function getEntityTypeId(): int;
    public function getEntityId(): int;
    public function getFields(): array;
    public function getBankDetails(): array;
    public function getAddressDetails(): array;
}