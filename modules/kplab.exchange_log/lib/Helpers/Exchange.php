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
    public static function runCompany($changes, $entityId, $userId, $entityData, $serviceUpdateName): void
    {
        if(!\Bitrix\Main\Loader::IncludeModule('kplab.api')) {
            Logs\File::AddMessage("Не загружен модуль `kplab.api`","Exchange::runCompany", LOG_COMPANY_EXCHANGES);
        }
        else {
            if (!empty($changes)) {

                self::logCompanyChanges($changes, $entityId, $userId, $serviceUpdateName);

                Logs\File::AddMessage($serviceUpdateName,"serviceUpdateName", LOG_COMPANY_EXCHANGES);

                if($serviceUpdateName !== "1С:АК-Кредит") {
                    $base = new BitrixHttpClientAdapter();
                    $authOneC = new AuthHttpClientDecorator($base, new BasicScheme('bitrix','bitrix'));
                    $httpClient = HttpClientFactory::build(baseClient: $authOneC, logging: true, emulate: false, devMode: true);

                    $companyService = new CompanyService($httpClient, 'POST','https://ak.seller-capital.ru/api/hs/api/update');
                    $companyService->sync($entityData, 'onec');
                }
            }
        }
    }

    public static function runLead($entityId, $entityData) {

        Logs\File::AddMessage($entityData,"Запуск обмена для компании {$entityId}", LOG_LEAD_EXCHANGES);
    }
    public static function runDeal($entityId, $entityData) {

        Logs\File::AddMessage($entityData,"Запуск обмена для компании {$entityId}", LOG_DEAL_EXCHANGES);
    }
    public static function runItem($entityId, $entityTypeId, $entityData) {

        Logs\File::AddMessage($entityData,"Запуск обмена для компании {$entityId}", LOG_ITEMS_EXCHANGES);
    }

    public static function logCompanyChanges(array $changes, int $entityId, int $userId, string $serviceUpdateName): void {
        foreach ($changes as $fieldName => $change) {
            $params = [
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => $fieldName,
                'OLD_VALUE' => $change['old'],
                'NEW_VALUE' => $change['new'],
                'USER_ID' => $userId,
                'SERVICE_UPDATE_NAME' => $serviceUpdateName,
                'CHANGE_DATE' => new Type\DateTime()
            ];

            $result = ExchangeLogTable::add($params);
            if (!$result->isSuccess()) {
                Logs\File::AddMessage(
                    $result->getErrorMessages(),
                    "Errors для компании ID {$entityId}",
                    LOG_COMPANY_EXCHANGES
                );
            }
        }
    }
    public static function syncCompanyWith1C(array $entityData): void {
        $base = new BitrixHttpClientAdapter();
        $authOneC = new AuthHttpClientDecorator($base, new BasicScheme('bitrix','bitrix'));
        $httpClient = HttpClientFactory::build(baseClient: $authOneC, logging: true, emulate: false, devMode: true);

        $companyService = new CompanyService($httpClient, 'POST','https://ak.seller-capital.ru/api/hs/api/update');
        $companyService->sync($entityData, 'onec');
    }
}