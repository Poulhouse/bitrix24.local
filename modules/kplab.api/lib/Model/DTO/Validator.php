<?php

namespace KPLab\API\V2\Model\DTO;

class Validator
{
    /**
     * Основная рекурсивная проверка
     */
    public static function collectErrors(object $dto): array
    {
        $errors = [];

        // 1. Required
        $requiredFields = method_exists($dto, 'required') ? $dto::required() : [];
        foreach ($requiredFields as $field) {
            if (!property_exists($dto, $field)) {
                $errors[$field] = "Поле '$field' отсутствует в DTO";
                continue;
            }

            $value = $dto->$field;
            if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
                $errors[$field] = "Поле '$field' обязательно для заполнения";
            }
        }

        // 2. Рекурсивная проверка вложенных DTO
        foreach (get_object_vars($dto) as $prop => $value) {
            if ($value instanceof \KPLab\API\V2\Interfaces\AbstractSeller) {
                $subErrors = self::collectErrors($value);
                if (!empty($subErrors)) {
                    $errors[$prop] = $subErrors;
                }
            }

            if (is_array($value) && !empty($value) && $value[0] instanceof Sellers\SellerPersonDTO) {
                foreach ($value as $i => $subDto) {
                    $subErrors = self::collectErrors($subDto);
                    if (!empty($subErrors)) {
                        $errors[$prop][$i] = $subErrors;
                    }
                }
            }
        }

        return self::filterNonEmptyErrors($errors);
    }

    private static function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }
    public static function filterNonEmptyErrors(array $errors): array
    {
        // Рекурсивно удаляет пустые массивы из дерева ошибок
        return array_filter($errors, function ($item) {
            if (is_array($item)) {
                return !empty(self::filterNonEmptyErrors($item));
            }
            return !empty($item);
        });
    }


}