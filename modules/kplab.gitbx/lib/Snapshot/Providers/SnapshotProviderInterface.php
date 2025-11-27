<?php

namespace KPLab\GitBx\Snapshot\Providers;

interface SnapshotProviderInterface
{
    /**
     * Собрать данные для snapshot.
     *
     * @return array
     */
    public function collect(): array;
}