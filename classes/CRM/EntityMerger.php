<?php

namespace KPLab\CRM;

/**
 * Базовый класс для объединения сущностей.
 */
abstract class EntityMerger
{
    /**
     * Тип сущности (например, 'LEAD', 'SMART_PROCESS').
     *
     * @var string
     */
    protected string $entityType;

    public function __construct(string $entityType)
    {
        $this->entityType = $entityType;
    }

    /**
     * Абстрактный метод для объединения двух сущностей.
     *
     * @param int $primaryEntityId ID основной сущности.
     * @param int $secondaryEntityId ID вторичной сущности.
     * @return bool|array
     */
    abstract public function merge(int $primaryEntityId, int $secondaryEntityId): bool|array;
}