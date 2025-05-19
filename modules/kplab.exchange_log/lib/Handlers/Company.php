<?php namespace Kplab\Exchange_log\Handlers;

use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
use Kplab\Exchange_log\ExchangeLogTable;
use Kplab\Exchange_log\Helpers\ChangeChecker;
use Kplab\Exchange_log\Helpers\Exchange;
use KPLab\Logs;
use Bitrix\Main\Type;
use KPLab\API\V2\Helpers\Locker;
use KPLab\API\V2\Model\Service\ChangeContext;

define("LOG_COMPANY_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/company_changes.log");
define("LOG_COMPANY_BACKGROUND_ERRORS", $_SERVER['DOCUMENT_ROOT']."/local/logs/company_changes_background_errors.log");
define("LOG_COMPANY_EXCHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/company_exchanges.log");
define("LOG_COMPANY_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/company_errors_changes.log");

class Company {

    private const MODULE_ID = "kplab.exchange_log";
    private const EntityTypeId = \CCrmOwnerType::Company;
    public static function OnAfterCrmCompanyAdd(&$arFields)
    {
        try {
            // Определяем источник создания компании
            $source = ChangeContext::getSource() ?? "Битрикс24";

            // Подготовка данных для фоновой задачи
            $taskData = [
                'company_id' => $arFields['ID'],
                'inn' => $arFields['UF_CRM_6433D7C925893'],
                'userId' => $arFields['CREATED_BY_ID'],
                'source' => $source,
                'newData' => $arFields
            ];

            //Logs\File::AddMessage([$taskData, "Add"], "OnAfterCrmCompanyAdd", LOG_COMPANY_CHANGES);

            // Добавляем агент с задержкой 5 секунд
            $agentFunction = "\\Kplab\\Exchange_log\\Handlers\\Company::processCompanyAgent";
            $params = "'".json_encode($taskData) . "', 'Add'";
            self::initAgent($agentFunction, $params);

        } catch (\Exception $e) {
            Logs\File::AddMessage("Error: ".$e->getMessage(), "OnAfterCrmCompanyAdd", LOG_COMPANY_ERRORS_CHANGES);
        }

    }

    public static function OnAfterCrmCompanyUpdate(&$arFields)
    {
        //Logs\File::AddMessage($arFields, "OnAfterCrmCompanyUpdate", LOG_COMPANY_CHANGES);
        try {
            // Определяем источник создания компании
            $source = ChangeContext::getSource() ?? "Битрикс24";

            $taskData = [
                'company_id' => $arFields['ID'],
                'inn' => $arFields['UF_CRM_6433D7C925893'],
                'userId' => $arFields['MODIFY_BY_ID'],
                'source' => $source,
                'newData' => $arFields
            ];

            // Добавляем агент с задержкой 5 секунд
            $agentFunction = "\\Kplab\\Exchange_log\\Handlers\\Company::processCompanyAgent";
            $params = "'".json_encode($taskData) . "', 'Update'";
            self::initAgent($agentFunction, $params);

        } catch (\Exception $e) {
            Logs\File::AddMessage("Error: ".$e->getMessage(), "OnAfterCrmCompanyUpdate", LOG_COMPANY_ERRORS_CHANGES);
        } finally {
            return true;
        }
    }




    // Обработчик агента
    public static function processCompanyAgent($encodedTaskData, $event): string
    {
        $taskData = json_decode($encodedTaskData, true);
        //Logs\File::AddMessage($taskData, "processCompanyAgent {$event}", LOG_COMPANY_CHANGES);
        try {
            // Вызов универсальной функции проверки и логирования изменений
            $changes = ChangeChecker::checkTrackedChanges(
                self::MODULE_ID,
                \CCrmOwnerType::Company,
                $taskData['company_id'],
                $taskData['newData'],
                $taskData['userId']
            );
            $serviceUpdateName = $taskData['source'];

            Exchange::runCompany($changes, $taskData['company_id'], $taskData['userId'], $taskData['newData'], $serviceUpdateName);

        } catch (\Exception $e) {
            // Логируем с полным контекстом ошибки
            $errorContext = [
                'error' => $e->getMessage(),
                'task_data' => $taskData,
                'trace' => $e->getTraceAsString()
            ];
            Logs\File::AddMessage(json_encode($errorContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "processCompanyInBackground", LOG_COMPANY_BACKGROUND_ERRORS);
        }
        return ''; // Удаляем агент после выполнения
    }
    private static function initAgent($agentFunction, $params): void
    {
        if (!self::agentExists($agentFunction, $params)) {
            \CAgent::AddAgent(
                $agentFunction . "(" . $params . ");",
                self::MODULE_ID,
                "N",
                5,
                "",
                "Y",
                ConvertTimeStamp(time() + 5, "FULL")
            );
        }
    }
    private static function agentExists($functionName, $params = ''): bool
    {
        $res = \CAgent::GetList(
            [],
            [
                "MODULE_ID" => self::MODULE_ID,
                "NAME" => $functionName . "(" . $params . ")%"
            ]
        );
        return (bool)$res->Fetch();
    }
}

