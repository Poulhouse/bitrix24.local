<?php namespace Kplab\Exchange_log\Helpers;

use Bitrix\Main\LoaderException;
use KPLab\API\V2\HttpClientFactory;
use KPLab\API\V2\Infrastructure\Http\Auth\AuthHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Interfaces\Http\Auth\BasicScheme;
use KPLab\API\V2\Model\Service\CompanyService;
use Kplab\Exchange_log\ExchangeLogTable;
use KPLab\Logs;
use Bitrix\Main\Type;

define("LOG_COMPANY_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/company_exchanges.log");
define("LOG_DEAL_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/deal_exchanges.log");
define("LOG_LEAD_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/lead_exchanges.log");
define("LOG_ITEMS_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/items_exchanges.log");
class Exchange {
    /**
     * @throws \DateMalformedStringException
     * @throws LoaderException
     */
    public static function runCompany($entityData, $serviceUpdateName): void
    {
        if(!\Bitrix\Main\Loader::IncludeModule('kplab.api')) {
            Logs\File::AddMessage("Не загружен модуль `kplab.api`","Exchange::runCompany", LOG_COMPANY_EXCHANGES);
        }
        else {
            if($serviceUpdateName !== "1С:АК-Кредит") {
                self::exchange_1c($entityData);
            }
        }
    }
    public static function exchange_1c(array $entityData): void {
        try {
            $base = new BitrixHttpClientAdapter();
            $authOneC = new AuthHttpClientDecorator($base, new BasicScheme('bitrix','bitrix'));
            $httpClient = HttpClientFactory::build(baseClient: $authOneC, logging: true, emulate: false, devMode: true);

            $companyService = new CompanyService($httpClient, 'POST','https://ak.seller-capital.ru/api/hs/api/update');
            $companyService->sync($entityData, 'onec');
        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(),"Exchange::exchange_1c()", LOG_COMPANY_EXCHANGES);
        }

    }
}