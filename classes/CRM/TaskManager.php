<?php namespace KPLab\CRM;

use Bitrix\Main\Loader;
use Bitrix\Tasks\Internals\TaskTable;
use Bitrix\Main\Diag\Logger;

class TaskManager
{
    /**
     * Удаляет задачи, созданные до указанной даты.
     *
     * @param string $date Дата в формате Y-m-d
     * @return bool
     */
    public static function deleteTasksFromDate(string $date): bool
    {
        if (!Loader::includeModule('tasks')) {
            self::logError('Модуль "tasks" не установлен.');
            return false;
        }

        $filter = [
            '<CREATED_DATE' => $date,
        ];

        return self::deleteTasksByFilter($filter);
    }

    /**
     * Удаляет задачи по заданному фильтру.
     *
     * @param array $filter Фильтр для выборки задач
     * @return bool
     */
    private static function deleteTasksByFilter(array $filter): bool
    {
        $tasks = TaskTable::getList([
            'select' => ['ID'],
            'filter' => $filter,
            'order'  => ['ID' => 'DESC'],
        ]);

        while ($task = $tasks->fetch()) {
            self::deleteTaskById($task['ID']);
        }

        return true;
    }

    /**
     * Удаляет задачу по её ID.
     *
     * @param int $taskId ID задачи
     * @return bool
     */
    public static function deleteTaskById(int $taskId): bool
    {
        try {
            TaskTable::delete($taskId);
        } catch (\Exception $e) {
            // Логирование ошибки через Logger D7
            self::logError("Ошибка при удалении задачи ID {$taskId}: " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Логирует ошибку с использованием Logger D7.
     *
     * @param string $message Сообщение об ошибке
     */
    private static function logError(string $message): void
    {
        $logger = new Logger('task_manager_logger');
        $logger->addWriter(new \Bitrix\Main\Diag\FileHandler('/local/logs/taskmanager/'.date("Y/m-d/H-i") . '/logfile.log'));
        $logger->error($message);
    }
}