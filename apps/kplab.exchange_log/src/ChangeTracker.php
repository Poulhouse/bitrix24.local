<?php
// src/ChangeTracker.php
namespace Kplab\ExchangeLog;

class ChangeTracker {
    public function __construct() {
        // Инициализация: загрузка настроек, подключение к БД и т.д.
    }

    /**
     * Пример метода для отслеживания изменений в определённом поле сущности.
     *
     * @param mixed  $entityId      Идентификатор сущности
     * @param string $entityType    Тип сущности (например, crm.deal, crm.lead)
     * @param string $field         Название поля, которое отслеживается
     * @param mixed  $oldValue      Старое значение поля
     * @param mixed  $newValue      Новое значение поля
     * @param int    $userId        ID пользователя, внесшего изменения
     *
     * @return bool Результат записи истории изменения (true, если успешно)
     */
    public function trackChange($entityId, $entityType, $field, $oldValue, $newValue, $userId) {
        // Здесь реализуйте проверку: если выбранное поле изменилось, то формируйте запись в универсальный список или HL-блок.
        // Например, вызов REST API для универсальных списков (в облаке) или запись через D7 API для HL-блоков (в коробке).

        // Пример логирования в файл (для демонстрации)
        $log = sprintf(
            "[%s] Сущность: %s (%s). Поле %s изменилось с '%s' на '%s' пользователем ID %s.\n",
            date("Y-m-d H:i:s"),
            $entityType,
            $entityId,
            $field,
            $oldValue,
            $newValue,
            $userId
        );
        file_put_contents(__DIR__ . '/../logs/changes.log', $log, FILE_APPEND);
        return true;
    }
}
