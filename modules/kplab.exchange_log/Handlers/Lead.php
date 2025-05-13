<?php namespace KPLab\ExchangeLog\Handlers;

use KPLab\ExchangeLog\Helpers\ChangeChecker;
use KPLab\Logs;
define("LOG_LEAD_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/lead_changes.log");
define("LOG_LEAD_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/lead_errors_changes.log");


class Lead {
    public static function OnAfterCrmLeadAdd(&$arFields) {

        $ID = $arFields['ID'];
        $newData = $arFields; // Новые данные компании после обновления
        $moduleId = 'kplab.exchange_log';
        $entityTypeId = "1"; // Здесь мы используем ключ, соответствующий компаниям
        $userId = $newData['MODIFY_BY_ID'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Создание лида ID {$ID}", LOG_LEAD_CHANGES);
        }
    }
    public static function OnAfterCrmLeadUpdate(&$arFields) {

        $ID = $arFields['ID'];
        $newData = $arFields; // Новые данные компании после обновления
        $moduleId = 'kplab.exchange_log';
        $entityTypeId = "1"; // Здесь мы используем ключ, соответствующий компаниям
        $userId = $newData['MODIFY_BY_ID'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Изменение лида ID {$ID}", LOG_LEAD_CHANGES);
        }
    }
}