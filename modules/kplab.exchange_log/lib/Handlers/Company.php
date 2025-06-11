<?php namespace Kplab\Exchange_log\Handlers;

use Kplab\Exchange_log\Helpers;
use KPLab\Logs;
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
        try {
            $serviceUpdateName = $taskData['source'];
            $userId = $taskData['userId'];
            $newData = $taskData['newData'];
            $company_id = $taskData['company_id'];

            if (Helpers\ChangeChecker::check(
                $newData,                  // массив с актуальными данными компании
                \CCrmOwnerType::Company,
                $company_id,
                [
                    'SERVICE_UPDATE_NAME' => $serviceUpdateName,
                    'USER_ID' => $userId, // при необходимости
                ]
            )) {
                Helpers\Exchange::runCompany($newData, $serviceUpdateName);
            }

        } catch (\Exception $e) {
            $errorContext = [
                'error' => $e->getMessage(),
                'task_data' => $taskData,
                'trace' => $e->getTraceAsString()
            ];
            Logs\File::AddMessage(json_encode($errorContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "processCompanyInBackground", LOG_COMPANY_BACKGROUND_ERRORS);
        } catch (\Throwable $e) {
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

