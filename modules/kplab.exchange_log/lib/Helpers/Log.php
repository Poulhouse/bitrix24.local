<?php namespace Kplab\Exchange_log\Helpers;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Kplab\Exchange_log\ExchangeLogTable;
use Bitrix\Main\Type\DateTime;
use KPLab\Logs\File;

class Log
{
    /**
     * @throws \Throwable
     */
    public static function init(
        int $entityTypeId,
        int $entityId,
        string $fieldCode,
        string $fieldName,
        string $newValue,
        array $context = [],
        string $logPath = LOG_ChangeChecker
    ): bool {
        try {
            $existing = ExchangeLogTable::getRow([
                'filter' => [
                    '=ENTITY_TYPE_ID' => $entityTypeId,
                    '=ENTITY_ID' => $entityId,
                    '=FIELD_CODE' => $fieldCode,
                ],
                'order' => ['ID' => 'DESC']
            ]);

            if ($existing && self::normalizeJsonString($existing['NEW_VALUE']) === self::normalizeJsonString($newValue)) {
                return false;
            }

            $oldValue = $existing['NEW_VALUE'] ?? '';

            $data = [
                'ENTITY_TYPE_ID' => $entityTypeId,
                'ENTITY_ID'      => $entityId,
                'FIELD_CODE'     => $fieldCode,
                'FIELD_NAME'     => $fieldName,
                'OLD_VALUE'      => $oldValue,
                'NEW_VALUE'      => $newValue,
                'USER_ID'        => $context['USER_ID'],
                'CHANGE_DATE'    => new DateTime(),
            ];

            $data['SERVICE_UPDATE_NAME'] = $context['SERVICE_UPDATE_NAME'] ?? 'Битрикс24';
            File::AddMessage($data, "🚨 Попытка записи в ExchangeLogTable", $logPath);

            try {
                $result = ExchangeLogTable::add($data);

                if (!$result->isSuccess()) {
                    File::AddMessage("Ошибка при добавлении лога изменения поля", [
                        'errors' => $result->getErrorMessages(),
                        'data' => $data,
                    ], $logPath);
                } else {
                    File::AddMessage($result->getId(), "✅ Успешно добавлена запись ID", $logPath);
                    return true;
                }
            } catch (\Throwable $e) {
                File::AddMessage([
                    'exception' => $e,
                    'data' => $data,
                ], "Фатальная ошибка при добавлении в ExchangeLogTable", $logPath);
            }

        } catch (\Throwable $e) {
            File::AddMessage([
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'fieldCode' => $fieldCode,
                'newValue' => $newValue,
                'context' => $context,
            ],"Ошибка в Log::init", $logPath);
            throw $e;
        }
        return false;
    }

    private static function normalizeJson(string $json): string {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return $json;
        ksort($decoded);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
    private static function normalizeJsonString(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        $sorted = self::recursiveKeySort($data);
        return json_encode($sorted, JSON_UNESCAPED_UNICODE);
    }
    private static function recursiveKeySort(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::recursiveKeySort($value);
            }
        }
        unset($value);
        ksort($array);
        return $array;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function hasRecentChanges(int $entityTypeId, int $entityId): bool
    {
        $recent = ExchangeLogTable::getRow([
            'filter' => [
                '=ENTITY_TYPE_ID' => $entityTypeId,
                '=ENTITY_ID' => $entityId,
                '>=CHANGE_DATE' => (new \Bitrix\Main\Type\DateTime())->add('-1 minute')
            ],
            'order' => ['ID' => 'DESC']
        ]);

        return (bool)$recent;
    }
}