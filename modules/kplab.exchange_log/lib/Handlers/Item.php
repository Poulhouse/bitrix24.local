<?php namespace Kplab\Exchange_log\Handlers;

use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
use Kplab\Exchange_log\ExchangeLogTable;
use Kplab\Exchange_log\Helpers\ChangeChecker;
use Kplab\Exchange_log\Helpers\Exchange;
use KPLab\Logs;
use Bitrix\Main\Type;
define("LOG_ITEMS", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/items.log");
define("LOG_ITEMS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/items_changes.log");
define("LOG_ITEMS_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/items_errors_changes.log");

class Item {
    public static function OnCrmDynamicItemAdd(\Bitrix\Main\Event $event)
    {
        $paramsItem = $event->getParameter("item");
        $entityData = $paramsItem->getData();
        $entityTypeId = $paramsItem->getEntityTypeId();
        $entityId = $event->getParameter("id");
        $moduleId = 'kplab.exchange_log';
        $userId = $entityData['UPDATED_BY'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $entityId, $entityData, $userId);

        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Добавление элемента СП #{$entityTypeId} - ID {$entityId}", LOG_ITEMS_CHANGES);
        }
    }
    public static function OnCrmDynamicItemUpdate(\Bitrix\Main\Event $event)
    {
        $paramsItem = $event->getParameter("item");
        $entityData = $paramsItem->getData();
        $entityTypeId = $paramsItem->getEntityTypeId();
        $entityId = $event->getParameter("id");
        $moduleId = 'kplab.exchange_log';
        $userId = $entityData['UPDATED_BY'];
        // Вызов универсальной функции проверки и логирования изменений
        $changes = ChangeChecker::checkTrackedChanges($moduleId, $entityTypeId, $entityId, $entityData, $userId);
        if (!empty($changes)) {
            // Можно записать отладочную информацию
            Logs\File::AddMessage($changes, "Изменение элемента СП #{$entityTypeId} - ID {$entityId}", LOG_ITEMS_CHANGES);

            foreach ($changes as $fieldName => $change) {
                $params = [
                    'ENTITY_TYPE_ID' => $entityTypeId,
                    'ENTITY_ID'   => $entityId,
                    'FIELD_NAME'  => $fieldName,
                    'OLD_VALUE'   => $change['old'],
                    'NEW_VALUE'   => $change['new'],
                    'USER_ID'     => $userId,
                    'CHANGE_DATE' => new Type\DateTime()
                ];
                Logs\File::AddMessage($params,"Параметры Изменения элемента СП #{$entityTypeId} - ID {$entityId}", LOG_ITEMS_CHANGES);

                $result = ExchangeLogTable::add($params);

                if ($result->isSuccess()) {
                    // Здесь можно запускать обмен с внешней системой
                    Exchange::runCompany($entityId, $entityData);
                } else {
                    Logs\File::AddMessage($result->getErrorMessages(),"Errors элемента СП #{$entityTypeId} - ID {$entityId}", LOG_ITEMS_ERRORS_CHANGES);
                }
            }
        }
    }
}

