<?php namespace KPLab\ExchangeLog\Handlers;

use KPLab\ExchangeLog\Helpers\ChangeChecker;
use KPLab\Logs;
define("LOG_DEAL_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/deal_changes.log");
define("LOG_DEAL_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/deal_errors_changes.log");

class Deal {
    public static function OnAfterCrmDealAdd(&$arFields) {
        $ID = $arFields['ID'];
        $newData = $arFields; // Новые данные компании после обновления
        $moduleId = 'kplab.exchange_log';
        $entityTypeId = "2"; // Здесь мы используем ключ, соответствующий компаниям
        $userId = $newData['MODIFY_BY_ID'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);// Если функция вернула не пустой массив изменений, значит, отслеживаемые поля изменились
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Добавление Сделки ID {$ID}", LOG_DEAL_CHANGES);
        }

    }
    public static function OnAfterCrmDealUpdate(&$arFields) {
        $ID = $arFields['ID'];
        $newData = $arFields; // Новые данные компании после обновления
        $moduleId = 'kplab.exchange_log';
        $entityTypeId = "2"; // Здесь мы используем ключ, соответствующий компаниям
        $userId = $newData['MODIFY_BY_ID'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $ID, $newData, $userId);
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Изменение Сделки ID {$ID}", LOG_DEAL_CHANGES);
        }

    }
}