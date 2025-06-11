<?php namespace Kplab\Exchange_log\Handlers;

use KPLab\API\V2\Model\Service\ChangeContext;
use KPLab\Logs;
use Kplab\Exchange_log\Helpers;

define("LOG_REQUISITE_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/requisite_changes.log");
define("LOG_REQUISITE_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/logs/requisite_errors_changes.log");
define("LOG_REQUISITES_BACKGROUND_ERRORS", $_SERVER['DOCUMENT_ROOT']."/local/logs/requisite_changes_background_errors.log");
class Requisite
{
    private const MODULE_ID = "kplab.exchange_log";
    public static function handleSliderAjax(): void
    {
        if ($_SERVER['REQUEST_URI'] !== '/bitrix/components/bitrix/crm.requisite.details/slider.ajax.php') {
            return;
        }

        if ($_REQUEST['ACTION'] !== 'SAVE' || empty($_REQUEST['requisite_id'])) {
            return;
        }

        $requisiteId = (int)$_REQUEST['requisite_id'];
        $fields = \Bitrix\Crm\EntityRequisite::getSingleInstance()->getById($requisiteId);
        $source = ChangeContext::getSource() ?? 'Битрикс24';

        $taskData = [
            'requisite_id' => $requisiteId,
            'entity_type_id' => $fields['ENTITY_TYPE_ID'] ?? null,
            'entity_id' => $fields['ENTITY_ID'] ?? null,
            'userId' => $fields['MODIFY_BY_ID'] ?? null,
            'source' => $source,
        ];

        $agentFunction = "\\Kplab\\Exchange_log\\Handlers\\Requisite::processRequisiteAgent";
        $params = "'".json_encode($taskData) . "', 'Update'";
        self::initAgent($agentFunction, $params);
    }
    public static function processRequisiteAgent(string $jsonData, string $mode): string
    {
        $taskData = json_decode($jsonData, true);
        try {
            $serviceUpdateName = $taskData['source'];
            $userId = $taskData['userId'];
            $entity_id = $taskData['entity_id'];
            $entity_type_id = $taskData['entity_type_id'];
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entity_type_id);
            $newData = $factory->getItem($entity_id)->getData();

            Helpers\ChangeChecker::check(
                $newData,                  // массив с актуальными данными компании
                $entity_type_id,
                $entity_id,
                [
                    'SERVICE_UPDATE_NAME' => $serviceUpdateName,
                    'USER_ID' => $userId, // при необходимости
                ]
            );

            if($entity_type_id == \CCrmOwnerType::Company) {
                Helpers\Exchange::runCompany($newData, $serviceUpdateName);
            }

        } catch (\Exception $e) {
            // Логируем с полным контекстом ошибки
            $errorContext = [
                'error' => $e->getMessage(),
                'task_data' => $taskData,
                'trace' => $e->getTraceAsString()
            ];
            Logs\File::AddMessage(json_encode($errorContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "processCompanyInBackground", LOG_REQUISITES_BACKGROUND_ERRORS);
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