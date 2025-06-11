<?php namespace KPLab\API\V2\Model\Service\Sellers;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\API\V2\Model\DTO\Sellers\SellerPersonDTO;
use KPLab\API\V2\Model\Service\SellerService;
use KPLab\Logs\File;

define("LOG_SELLER_DIRECTOR_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_sellerdirector.log");

class SellerDirectorService
{
    /**
     * Обновляет или создаёт запись директора в Битрикс-карточке.
     *
     * @param SellerPersonDTO $director — DTO директора
     * @param int $sellerCardId
     */
    public function handler(SellerPersonDTO $director, int $sellerCardId): void
    {
        $seller = new SellerService();
        try {
            $directorId = $seller->syncPerson($director, 'director', $sellerCardId);
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $sellerItem = $factory->getItem($sellerCardId);
            if (!$sellerItem) {
                throw new \Exception("Не найдена карточка компании по ID $sellerCardId");
            }

            $prefixedId = "CO_" . $directorId;
            $sellerItem->set("UF_CRM_1615200179", $prefixedId);
            $sellerItem->set("UF_CRM_UPDATE_INFO_LK", true);

            $operation = $factory->getUpdateOperation($sellerItem);
            $operation->disableAllChecks();
            $operation->launch();
        } catch (\Throwable $e) {
            File::AddMessage($e->getMessage(), 'Ошибка в SellerDirectorService::handler()', LOG_SELLER_DIRECTOR_SERVICE);
        }
    }
}