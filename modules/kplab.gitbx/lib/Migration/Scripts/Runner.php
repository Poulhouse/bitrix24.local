<?php

namespace KPLab\GitBx\Migration\Scripts;


use KPLab\GitBx\Util\Logger;
use KPLab\GitBx\Migration\Actions\Registry;

use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\Diag\Debug;
use CUserTypeEntity;

use KPLab\GitBx\Migration\Actions\CrmFieldActions;
use KPLab\GitBx\Migration\Actions\PipelineActions;
use KPLab\GitBx\Migration\Actions\RobotActions;
use KPLab\GitBx\Migration\Actions\SmartProcessActions;
use KPLab\GitBx\Migration\Actions\BpActions;
use KPLab\GitBx\Migration\Actions\UIViewActions;

/**
 * Основной исполнитель миграций GitBx.
 *
 * Вход: массив migration plan (version + actions[])
 * Вывод: ['status' => ..., 'log' => [...]]
 */
class Runner
{
    /** @var string[] */
    protected array $log = [];

    /** Маппинг action types → классы */
    protected array $actionMap = [];

    public function __construct()
    {
        Loader::includeModule('crm');

        // Регистрируем доступные операции
        $this->actionMap = [
            // CRM поля
            'crm_field_add'     => CrmFieldActions::class,
            'crm_field_modify'  => CrmFieldActions::class,
            'crm_field_delete'  => CrmFieldActions::class,

            // Воронки / стадии
            'pipeline_add'      => PipelineActions::class,
            'pipeline_modify'   => PipelineActions::class,
            'pipeline_delete'   => PipelineActions::class,

            // Роботы
            'robot_add'         => RobotActions::class,
            'robot_modify'      => RobotActions::class,
            'robot_delete'      => RobotActions::class,

            // Смарт-процессы
            'sp_add'            => SmartProcessActions::class,
            'sp_modify'         => SmartProcessActions::class,
            'sp_delete'         => SmartProcessActions::class,

            // Бизнес-процессы
            'bp_add'            => BpActions::class,
            'bp_modify'         => BpActions::class,
            'bp_delete'         => BpActions::class,

            // Разделы интерфейса
            'ui_view_add'       => UIViewActions::class,
            'ui_view_modify'    => UIViewActions::class,
            'ui_view_delete'    => UIViewActions::class,
        ];
    }

    /**
     * Главный метод запуска
     */
    /**
     * Выполнить миграцию из пакета.
     */
    public function run(array $package): array
    {
        $operations = $package['operations'] ?? [];

        if (!is_array($operations)) {
            throw new \RuntimeException("Invalid migration package: operations missing");
        }

        Logger::info("Runner started", [
            'operations' => count($operations)
        ]);

        $results = [];

        foreach ($operations as $index => $operation) {
            $provider = $operation['provider'] ?? null;
            $action   = $operation['action'] ?? null;

            if (!$provider || !$action) {
                Logger::error("Invalid operation structure", ['operation' => $operation]);
                continue;
            }

            $handler = Registry::get($provider);

            if (!$handler) {
                Logger::error("No action handler for provider: {$provider}");
                continue;
            }

            try {
                $result = $handler->execute($action, $operation);

                $results[] = [
                    'operation' => $operation,
                    'result'    => $result
                ];

                Logger::info("Operation executed", [
                    'provider' => $provider,
                    'action' => $action
                ]);

            } catch (\Throwable $e) {
                Logger::error("Operation failed", [
                    'provider' => $provider,
                    'action' => $action,
                    'exception' => $e->getMessage()
                ]);

                $results[] = [
                    'operation' => $operation,
                    'error' => $e->getMessage()
                ];
            }
        }

        Logger::info("Runner finished", [
            'executed' => count($results)
        ]);

        return $results;
    }
}