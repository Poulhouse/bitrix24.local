<?php

namespace KPLab\GitBx\Diff\Providers;

interface DiffProviderInterface
{
    public function getKey(): string; // 'crm_fields', 'pipelines', etc

    public function normalize(array $data): array;

    public function diff(array $test, array $prod): array;
}