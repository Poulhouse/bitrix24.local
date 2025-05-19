<?php
namespace KPLab\CRM;

use Bitrix\Main\Loader;
use Bitrix\Crm\Service\Factory;

/**
 * Класс для объединения смарт-процессов. !!!!!НЕ РАБОТАЕТ!!!!
 */
class SmartProcessMerger extends EntityMerger
{
    public function __construct()
    {
        parent::__construct('SMART_PROCESS');
        if (!Loader::includeModule('crm')) {
            throw new \Exception('Модуль CRM не установлен.');
        }
    }

    /**
     * Объединение двух смарт-процессов.
     *
     * @param int $primaryItemId ID основного элемента смарт-процесса.
     * @param int $secondaryItemId ID вторичного элемента смарт-процесса.
     * @return bool|array
     */
    public function merge(int $primaryItemId, int $secondaryItemId): bool|array
    {
        // Получаем фабрику для работы со смарт-процессами
        $factory = Factory();
        $smartProcessFactory = $factory->getSmartProcessFactory();

        if (!$smartProcessFactory) {
            return ['error' => 'Не удалось получить фабрику для смарт-процессов.'];
        }

        // Получаем данные вторичного элемента
        $secondaryItem = $smartProcessFactory->getItemById($secondaryItemId);
        if (!$secondaryItem) {
            return ['error' => 'Вторичный элемент смарт-процесса не найден.'];
        }

        // Получаем данные основного элемента
        $primaryItem = $smartProcessFactory->getItemById($primaryItemId);
        if (!$primaryItem) {
            return ['error' => 'Основной элемент смарт-процесса не найден.'];
        }

        // Объединяем данные
        $mergedFields = array_merge($primaryItem->getFields(), $secondaryItem->getFields());

        // Обновляем основной элемент
        $primaryItem->setFields($mergedFields);
        $saveResult = $primaryItem->save();
        if (!$saveResult->isSuccess()) {
            return ['error' => implode(', ', $saveResult->getErrorMessages())];
        }

        // Удаляем вторичный элемент
        $secondaryItem->delete();

        return true;
    }
}