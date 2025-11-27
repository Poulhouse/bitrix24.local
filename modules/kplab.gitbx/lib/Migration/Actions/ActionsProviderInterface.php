<?php

namespace KPLab\GitBx\Migration\Actions;

interface ActionsProviderInterface
{
    /**
     * Выполнить действие из операции (create/update/delete)
     */
    public function execute(string $action, array $operation): mixed;
}
