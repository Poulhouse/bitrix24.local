<?php namespace KPLab\ExchangeLog\Helpers;

use KPLab\API\V2\HttpClientFactory;
use KPLab\API\V2\Infrastructure\Http\Auth\AuthHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Interfaces\Http\Auth\BasicScheme;
use KPLab\API\V2\Service\CompanyService;
use KPLab\ExchangeLog\ExchangeLogTable;
use KPLab\Logs;

define("LOG_COMPANY_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/exchanges/company_exchanges.log");
define("LOG_DEAL_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/exchanges/deal_exchanges.log");
define("LOG_LEAD_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/exchanges/lead_exchanges.log");
define("LOG_ITEMS_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/exchanges/items_exchanges.log");
class Exchange {
    public static function runCompanyAdd($changes, $entityId, $userId, $entityData): void
    {
        if(!\Bitrix\Main\Loader::IncludeModule('kplab.api')) {
            Logs\File::AddMessage("Не загружен модуль `kplab.api`","Exchange::runCompany", LOG_COMPANY_EXCHANGES);
        }
        else {
            if (!empty($changes)) {
                // Можно записать отладочную информацию
                //Logs\File::AddMessage($changes, "Изменения для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);

                foreach ($changes as $fieldName => $change) {
                    $params = [
                        'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                        'ENTITY_ID' => $entityId,
                        'FIELD_NAME' => $fieldName,
                        'OLD_VALUE' => $change['old'],
                        'NEW_VALUE' => $change['new'],
                        'USER_ID' => $userId,
                        'CHANGE_DATE' => new Type\DateTime()
                    ];
                    Logs\File::AddMessage($params, "Параметры Изменения для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);

                    $result = ExchangeLogTable::add($params);

                    if (!$result->isSuccess()) {
                        Logs\File::AddMessage($result->getErrorMessages(), "Errors для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);
                    }
                }

                $base = new BitrixHttpClientAdapter();
                $authOneC = new AuthHttpClientDecorator($base, new BasicScheme('bitrix','bitrix'));
                $httpClient = HttpClientFactory::build(baseClient: $authOneC, logging: true, emulate: false, devMode: true);

                $companyService = new CompanyService($httpClient, 'POST','https://ak.seller-capital.ru/api/hs/api/update');
                $companyService->sync($entityData, 'onec');
            }
        }
    }
    public static function runCompanyUpdate($changes, $entityId, $userId, $entityData): void
    {

        if(!\Bitrix\Main\Loader::IncludeModule('kplab.api')) {
            Logs\File::AddMessage("Не загружен модуль `kplab.api`","Exchange::runCompany", LOG_COMPANY_EXCHANGES);
        }
        else {
            if (!empty($changes)) {
                // Можно записать отладочную информацию
                //Logs\File::AddMessage($changes, "Изменения для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);

                foreach ($changes as $fieldName => $change) {
                    $params = [
                        'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                        'ENTITY_ID' => $entityId,
                        'FIELD_NAME' => $fieldName,
                        'OLD_VALUE' => $change['old'],
                        'NEW_VALUE' => $change['new'],
                        'USER_ID' => $userId,
                        'CHANGE_DATE' => new Type\DateTime()
                    ];
                    Logs\File::AddMessage($params, "Параметры Изменения для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);

                    $result = ExchangeLogTable::add($params);

                    if (!$result->isSuccess()) {
                        Logs\File::AddMessage($result->getErrorMessages(), "Errors для компании ID {$entityId}", LOG_COMPANY_EXCHANGES);
                    }
                }

                $base = new BitrixHttpClientAdapter();
                $authOneC = new AuthHttpClientDecorator($base, new BasicScheme('bitrix','bitrix'));
                $httpClient = HttpClientFactory::build(baseClient: $authOneC, logging: true, emulate: false, devMode: true);

                $companyService = new CompanyService($httpClient, 'POST','https://ak.seller-capital.ru/api/hs/api/update');
                $companyService->sync($entityData, 'onec');
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
}