<?php
namespace KPLab\CRM;

/**
 * Фабрика для создания экземпляров классов объединения.
 */
class MergerFactory
{
    /**
     * Создает экземпляр класса для объединения сущностей.
     *
     * @param string $entityType Тип сущности ('LEAD' или 'SMART_PROCESS').
     * @return EntityMerger
     * @throws \Exception Если тип сущности не поддерживается.
     */
    public static function createMerger(string $entityType): EntityMerger
    {
        switch ($entityType) {
            case 'LEAD':
                return new LeadMerger();
            case 'SMART_PROCESS':
                return new SmartProcessMerger();
            default:
                throw new \Exception('Неподдерживаемый тип сущности.');
        }
    }
}