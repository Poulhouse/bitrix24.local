<?php namespace KPLab\Lib;

class Generate
{
    public string $guid;
    public ?\Bitrix\Crm\Service\Factory $factoryDeal;
    public function __construct() {
        $this->factoryDeal = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
    }
    public function getGUID(): static
    {
        if (function_exists('com_create_guid')) {
            $this->guid = strtolower(trim(com_create_guid(), '{}'));
        } else {
            $this->guid = strtolower(sprintf(
                '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(16384, 20479), // 4XXX
                mt_rand(32768, 49151), // 8XXX
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535)
            ));
        }

        return $this;
    }

    public function getDevLinkForNonameForm(): string
    {
        $guid = $this->guid;

        if($guid == '') {
            $this->getGUID();
            $guid = $this->guid;
        }

        // Формируем ссылку с параметром GUID
        return "https://stage-umber.vercel.app/doc-loader?id=" . urlencode($guid);

    }

    public function getProdLinkForNonameForm(): string
    {
        $guid = $this->guid;

        if($guid == '') {
            $this->getGUID();
            $guid = $this->guid;
        }


        // Формируем ссылку с параметром GUID
        return "https://seller-capital.ru/doc-loader?id=" . urlencode($guid);
    }
}