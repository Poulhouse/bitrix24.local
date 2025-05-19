<?php namespace Kplab\Exchange_log\Helpers;

use Kplab\Exchange_log\ExchangeLogTable;
use Bitrix\Main\Type;

class Check {
    /**
     * Проверяет изменения для отслеживаемых полей и логирует изменения.
     *
     * @param string $entityTypeName     Название Типа сущности (например, "COMPANY").
     * @param int $entityId        ID сущности.
     * @param array  $trackedFields   Массив отслеживаемых имен полей (например, ['TITLE', 'UF_CRM_CHANGING_THE_NOMINAL_ACCOUNT']).
     * @param array  $oldData         Ассоциативный массив со старыми значениями полей.
     * @param array  $newData         Ассоциативный массив с новыми значениями полей.
     * @param int    $userId          ID пользователя, совершившего изменение.
     *
     * @return bool Возвращает true, если хотя бы одно поле изменилось, иначе false.
     */
    public function trackedChanges(
        string $entityTypeName,
        int $entityId,
        array $trackedFields,
        string $serviceUpdateName,
        array $oldData,
        array $newData,
        int $userId
    ): bool
    {
        $changesMade = false;

        // Проходим по списку отслеживаемых полей
        foreach ($trackedFields as $field)
        {
            // Получаем старое и новое значение для данного поля
            $oldValue = $oldData[$field] ?? null;
            $newValue = $newData[$field] ?? null;

            // Если значения различаются (включая ситуацию, когда одно значение null, а другое не null)
            if ($oldValue !== $newValue)
            {
                // Добавляем запись в журнал изменений
                $result = ExchangeLogTable::add([
                    'ENTITY_TYPE' => $entityTypeName,
                    'ENTITY_ID'   => $entityId,
                    'FIELD_NAME'  => $field,
                    'OLD_VALUE'   => $oldValue,
                    'NEW_VALUE'   => $newValue,
                    'USER_ID'     => $userId,
                    'SERVICE_UPDATE_NAME' => $serviceUpdateName,
                    'CHANGE_DATE' => new Type\DateTime()
                ]);

                // Если запись успешно добавлена, то считаем, что изменения зафиксированы
                if ($result->isSuccess())
                {
                    $changesMade = true;
                }
                else
                {
                    // Можно реализовать логирование ошибок, если запись не добавилась
                    $errors = implode(", ", $result->getErrorMessages());
                    \Bitrix\Main\Diag\Debug::writeToFile("Ошибка логирования изменения поля '{$field}' для сущности {$entityTypeName} с ID {$entityId}: {$errors}", "", "exchange_log_error.log");
                }
            }
        }

        return $changesMade;
    }
}