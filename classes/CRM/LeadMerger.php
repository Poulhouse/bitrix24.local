<?php

namespace KPLab\CRM;

use Bitrix\Main\Loader;

/**
 * Класс для объединения лидов.
 */
class LeadMerger extends EntityMerger
{
    private $leadInstance;

    public function __construct()
    {
        parent::__construct('LEAD');
        if (!Loader::includeModule('crm')) {
            throw new \Exception('Модуль CRM не установлен.');
        }
        // Создаем экземпляр класса CCrmLead
        $this->leadInstance = new \CCrmLead(false);
    }

    /**
     * Объединение двух лидов.
     *
     * @param int $primaryLeadId ID основного лида.
     * @param int $secondaryLeadId ID вторичного лида.
     * @return bool|array
     */
    public function merge(int $primaryLeadId, int $secondaryLeadId): bool|array
    {
        // Получаем данные вторичного лида
        $secondaryLead = \CCrmLead::GetByID($secondaryLeadId, false);
        if (!$secondaryLead) {
            return ['error' => 'Вторичный лид не найден.'];
        }

        // Получаем данные основного лида
        $primaryLead = \CCrmLead::GetByID($primaryLeadId, false);
        if (!$primaryLead) {
            return ['error' => 'Основной лид не найден.'];
        }

        // Объединяем данные
        $mergedFields = array_merge($primaryLead, $secondaryLead);

        // Обновляем основной лид через экземпляр класса
        $result = $this->leadInstance->Update($primaryLeadId, $mergedFields);
        if (!$result) {
            return ['error' => 'Не удалось обновить основной лид.'];
        }

        // Удаляем вторичный лид через экземпляр класса
        $deleteResult = $this->leadInstance->Delete($secondaryLeadId);
        if (!$deleteResult) {
            return ['error' => 'Не удалось удалить вторичный лид.'];
        }

        return true;
    }
}