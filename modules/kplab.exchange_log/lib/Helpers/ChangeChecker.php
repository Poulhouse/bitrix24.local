<?php
namespace Kplab\Exchange_log\Helpers;

use Bitrix\Main\Config\Option;
use Kplab\Exchange_log\ExchangeLogTable;
use KPLab\Logs;

define("LOG_ChangeChecker", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/ChangeChecker.log");

class ChangeChecker
{
    /**
     * Проверяет изменения для отслеживаемых полей заданной сущности.
     *
     * @param string $moduleId     Идентификатор модуля (например, "kplab.exchange_log")
     * @param mixed        $entityTypeId Тип сущности, согласно сохранённым настройкам (например, "4" для компаний)
     * @param int $entityId     ID сущности (например, ID компании)
     * @param array        $newData      Ассоциативный массив новых данных, ключи – имена полей
     * @return array Массив изменений, где ключ — имя поля, а значение — массив с ключами 'old' и 'new'.
     *
     * Пример возвращаемого массива:
     * [
     *    "TITLE" => ["old" => "Старая компания", "new" => "Новая компания"],
     *    "UF_CRM_CHANGING_THE_NOMINAL_ACCOUNT" => ["old" => "1000", "new" => "1100"]
     * ]
     */
    public static function checkTrackedChanges(string $moduleId, mixed $entityTypeId, int $entityId, array $newData): array
    {
        Logs\File::AddMessage($newData,"newData", LOG_ChangeChecker);

        // Получаем сохранённые настройки для общих сущностей из опций модуля (в формате JSON)
        $trackedSettingsJson = Option::get($moduleId, "tracked_general_entities", '{}');
        $savedSettings_TrackedSp_Json = Option::get($moduleId, "tracked_sp_entities", '{}');
        $trackedSettings = json_decode($trackedSettingsJson, true);
        $trackedSPSettings = json_decode($savedSettings_TrackedSp_Json, true);
        //Logs\File::AddMessage($trackedSettings,"tracked_general_entities", LOG_ChangeChecker);
        //Logs\File::AddMessage($trackedSPSettings,"tracked_sp_entities", LOG_ChangeChecker);
        $tracked = $trackedSettings + $trackedSPSettings;

        Logs\File::AddMessage($tracked,"tracked", LOG_ChangeChecker);

        \Bitrix\Main\Loader::includeModule('crm');

        // Для заданного entityTypeId (например, "4" для Компаний) получаем список отслеживаемых полей
        $trackedFields = $tracked[$entityTypeId] ?? [];

        Logs\File::AddMessage($trackedFields,"trackedFields", LOG_ChangeChecker);

        $changes = [];

        // Проходим по каждому отслеживаемому полю
        foreach ($trackedFields as $fieldName)
        {
            $fieldPhone = null;
            $fieldEmail = null;

            if($fieldName == "PHONE") {

                $resFieldMultiPHONE = \CCrmFieldMulti::GetListEx([],[
                        'ENTITY_ID' => \CCrmOwnerType::ResolveName($entityTypeId),
                        'ELEMENT_ID' => $entityId,
                        'TYPE_ID' => \CCrmFieldMulti::PHONE
                    ]
                );
                while( $multifieldPhone = $resFieldMultiPHONE->fetch() )
                {
                    $fieldPhone[$multifieldPhone["TYPE_ID"]][] = $multifieldPhone["VALUE"];
                }

                Logs\File::AddMessage($fieldPhone,"fieldPhone", LOG_ChangeChecker);
            }

            if($fieldName == "EMAIL") {

                $resFieldMultiEMAIL = \CCrmFieldMulti::GetListEx(
                    [],
                    [
                        'ENTITY_ID' => \CCrmOwnerType::ResolveName($entityTypeId),
                        'ELEMENT_ID' => $entityId,
                        'TYPE_ID' => \CCrmFieldMulti::EMAIL
                    ]
                );
                while( $multifieldEMAIL = $resFieldMultiEMAIL->fetch() )
                {
                    $fieldEmail[$multifieldEMAIL["TYPE_ID"]][] = $multifieldEMAIL["VALUE"];
                }
                Logs\File::AddMessage($fieldEmail,"fieldEmail", LOG_ChangeChecker);
            }

            // Получаем последнюю запись в журнале для этого поля, по ENTITY_ID и FIELD_NAME
            $logRecord = ExchangeLogTable::getList([
                'filter' => [
                    'ENTITY_TYPE_ID'   => $entityTypeId,
                    'ENTITY_ID'   => $entityId,
                    'FIELD_NAME'  => $fieldName,
                ],
                'order'  => ['CHANGE_DATE' => 'DESC'],
                'limit'  => 1,
            ])->fetch();

            $lastLoggedValue = $logRecord ? $logRecord['NEW_VALUE'] : null;

            if($fieldName == "PHONE") {
                $newData[$fieldName] = json_encode($fieldPhone[$fieldName], JSON_UNESCAPED_UNICODE);
            }
            if($fieldName == "EMAIL") {
                $newData[$fieldName] = json_encode($fieldEmail[$fieldName], JSON_UNESCAPED_UNICODE);
            }

            // Если новое значение для этого поля определено и отличается от зафиксированного
            if (isset($newData[$fieldName]) && $newData[$fieldName] != $lastLoggedValue)
            {

                $changes[$fieldName] = [
                    'old' => $lastLoggedValue,
                    'new' => $newData[$fieldName],
                ];
            }
        }
        return $changes;
    }
}
