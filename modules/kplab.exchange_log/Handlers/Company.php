<?php namespace KPLab\ExchangeLog\Handlers;

use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
use KPLab\ExchangeLog\ExchangeLogTable;
use KPLab\ExchangeLog\Helpers\ChangeChecker;
use KPLab\ExchangeLog\Helpers\Exchange;
use KPLab\Logs;
use Bitrix\Main\Type;
use KPLab\API\V2\Helpers\Locker;

define("LOG_COMPANY_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/company_changes.log");
define("LOG_COMPANY_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/company_exchanges.log");
define("LOG_COMPANY_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/company_errors_changes.log");

class Company {
    /**
     * @throws \Exception
     */
    public static function OnAfterCrmCompanyUpdate(&$arFields)
    {
        $ID = $arFields['ID'];
        $INN = $arFields['UF_CRM_6433D7C925893'];
        $locker = new Locker();
        $identifier = 'company_inn_' . $INN;

        // Ждём максимум 10 секунд
        if ($locker->waitForRelease($identifier, 10)) {
            $locker->log("Блокировка освобождена: {$identifier}");
            $newData = $arFields; // Новые данные компании после обновления
            $moduleId = 'kplab.exchange_log';
            $entityTypeId = \CCrmOwnerType::Company;
            $userId = $newData['CREATED_BY_ID'];

            // Вызов универсальной функции проверки и логирования изменений
            $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);
            Exchange::runCompanyUpdate($changes, $ID, $userId, $newData);
        }
        else {
            $locker->errorLog("Не удалось дождаться разблокировки для компании {$ID}");
        }
    }

    /**
     * @throws \Exception
     */
    public static function OnAfterCrmCompanyAdd(&$arFields)
    {
        $ID = $arFields['ID'];
        $INN = $arFields['UF_CRM_6433D7C925893'];
        $locker = new Locker();
        $identifier = 'company_inn_' . $INN;

        // Ждём максимум 5 секунд
        if ($locker->waitForRelease($identifier)) {
            $locker->log("Блокировка освобождена: {$identifier}");
            $newData = $arFields; // Новые данные компании после обновления
            $moduleId = 'kplab.exchange_log';
            $entityTypeId = \CCrmOwnerType::Company;
            $userId = $newData['CREATED_BY_ID'];

            // Вызов универсальной функции проверки и логирования изменений
            $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);
            Exchange::runCompanyAdd($changes, $ID, $userId, $newData);
        }
        else {
            $locker->errorLog("Не удалось дождаться разблокировки для компании {$ID}");
        }


    }

    /**
     * @param array $changes
     * @param mixed $ID
     * @param string $entityTypeId
     * @param mixed $userId
     * @param mixed $newData
     * @return void
     * @throws \Exception
     */
    private static function extracted(array $changes, mixed $ID, string $entityTypeId, mixed $userId, mixed $newData): void
    {
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Изменения для компании ID {$ID}", LOG_COMPANY_CHANGES);

            foreach ($changes as $fieldName => $change) {
                $params = [
                    'ENTITY_TYPE_ID' => $entityTypeId,
                    'ENTITY_ID' => $ID,
                    'FIELD_NAME' => $fieldName,
                    'OLD_VALUE' => $change['old'],
                    'NEW_VALUE' => $change['new'],
                    'USER_ID' => $userId,
                    'CHANGE_DATE' => new Type\DateTime()
                ];
                Logs\File::AddMessage($params, "Параметры Изменения для компании ID {$ID}", LOG_COMPANY_CHANGES);

                $result = ExchangeLogTable::add($params);

                if (!$result->isSuccess()) {
                    Logs\File::AddMessage($result->getErrorMessages(), "Errors для компании ID {$ID}", LOG_COMPANY_ERRORS_CHANGES);
                }
            }

        }
    }
}

