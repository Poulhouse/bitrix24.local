<?php

namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\Multifield\Collection;
use Bitrix\Crm\Multifield\Type\Email;
use Bitrix\Crm\Multifield\Type\Phone;
use Bitrix\Crm\Multifield\Value;
use KPLab\API\V2\Model\DTO\LegalDTO;

use KPLab\API\V2\Model\DTO\PersonDTO;
use KPLab\Logs;
define("LOG_TOOL_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_tool.log");
class Tool
{
    public function __construct(){}

    //region Обработка контактов компании
    public function processFM(LegalDTO|PersonDTO $DTO, Collection $existingCollection = null): Collection
    {

        //Logs\File::AddMessage("","processFM 1", LOG_TOOL_SERVICE);
        if(is_null($existingCollection)) {
            $collection = new Collection();
        } else {
            $collection = $existingCollection;
        }

        foreach ($DTO->contactDetails as $contact) {
            // Определяем тип поля
            switch ($contact['typeId']) {
                case 1:
                    $typeId = Phone::ID;
                    $valueType = $this->mapPhoneValueType($contact['valueType']);
                    $valueText = "+7".$contact['valueText'];
                    break;
                case 2:
                    $typeId = Email::ID;
                    $valueType = $this->mapEmailValueType($contact['valueType']);
                    $valueText = $contact['valueText'];
                    break;
                default:
                    continue 2; // Пропускаем неизвестные типы
            }

            // Создаем временный Value для проверки
            $tempValue = (new Value())
                ->setTypeId($typeId)
                ->setValueType($valueType)
                ->setValue($valueText);

            // 1. Проверяем полное совпадение
            if ($collection->has($tempValue)) {
                continue;
            }

            // 2. Проверяем дубли по значению (без учета типа)
            $existingValues = $this->findSimilarValues($collection, $typeId, $tempValue->getValue());

            if (!empty($existingValues)) {
                // Обновляем существующую запись
                $this->updateExistingValue($existingValues[0], $valueType);
                continue;
            }

            // 3. Добавляем как новое значение
            $collection->add($tempValue);


        }

        return $collection;
    }
    private function findSimilarValues(Collection $collection, string $typeId, string $value): array
    {
        $similar = [];
        foreach ($collection->filterByType($typeId) as $existingValue) {
            if ($typeId === Phone::ID) {
                // Для телефонов нормализуем перед сравнением
                if (normalizePhone($existingValue->getValue()) === normalizePhone($value)) {
                    $similar[] = $existingValue;
                }
            } else {
                // Для email и других типов сравниваем как есть (с точным соответствием)
                if ($existingValue->getValue() === $value) {
                    $similar[] = $existingValue;
                }
            }
        }
        return $similar;
    }
    private function updateExistingValue(Value $value, string $newValueType): void
    {
        // Обновляем только если тип изменился
        if ($value->getValueType() !== $newValueType) {
            $value->setValueType($newValueType);
        }
    }
    private function mapPhoneValueType(int $inputType): string
    {
        $map = [
            1 => Phone::VALUE_TYPE_MOBILE,
            2 => Phone::VALUE_TYPE_WORK,
            3 => Phone::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Phone::VALUE_TYPE_WORK;
    }
    private function mapEmailValueType(int $inputType): string
    {
        $map = [
            1 => Email::VALUE_TYPE_HOME,
            2 => Email::VALUE_TYPE_WORK,
            3 => Email::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Email::VALUE_TYPE_WORK;
    }
    //endregion
}