<?php
namespace Kplab\Exchange_log\Helpers;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Config\Option;
use Kplab\Exchange_log\ExchangeLogTable;
use KPLab\Logs;
use Kplab\Exchange_log\Service\FieldMeta;
use Throwable;

define("LOG_ChangeChecker", $_SERVER['DOCUMENT_ROOT']."/local/logs/ChangeChecker.log");

class ChangeChecker
{
    /**
     * @throws ObjectPropertyException
     * @throws \DateMalformedStringException
     * @throws Throwable
     * @throws ArgumentException
     * @throws SystemException
     */
    public static function check(array $newData, int $entityTypeId, int $entityId, array $context = []): bool
    {

        try {
            $hasChanges = false;

            $moduleId = 'kplab.exchange_log';

            // Получаем сохранённые настройки для стандартных и смарт-процессов
            $trackedSettingsJson = Option::get($moduleId, "tracked_general_entities", '{}');
            $trackedSpSettingsJson = Option::get($moduleId, "tracked_sp_entities", '{}');

            $trackedSettings = json_decode($trackedSettingsJson, true) ?: [];
            $trackedSpSettings = json_decode($trackedSpSettingsJson, true) ?: [];

            // Объединяем
            $tracked = $trackedSettings + $trackedSpSettings;

            // Извлекаем список отслеживаемых полей для данной сущности
            $fieldsMap = $tracked[$entityTypeId] ?? [];

            // Проверка групповых полей
            foreach (['REQUISITES', 'contactDetails', 'addressDetails', 'bankDetails'] as $groupCode) {
                if (!array_key_exists($groupCode, $fieldsMap)) {
                    continue;
                }

                try {
                    $groupChanged = match ($groupCode) {
                        'REQUISITES'      => self::checkRequisitesChanges($entityTypeId, $entityId, $context),
                        'contactDetails'  => self::checkContactDetailsChanges($newData, $entityTypeId, $entityId, $context),
                        'addressDetails'  => self::checkAddressChanges($entityTypeId, $entityId, $context),
                        'bankDetails'     => self::checkBankDetailsChanges($entityTypeId, $entityId, $context),
                    };
                    $hasChanges = $hasChanges || $groupChanged;
                } catch (\Throwable $e) {
                    Logs\File::AddMessage([
                        'groupCode'     => $groupCode,
                        'entityTypeId'  => $entityTypeId,
                        'entityId'      => $entityId,
                        'context'       => $context,
                        'exception'     => $e->getMessage(),
                        'trace'         => $e->getTraceAsString()
                    ], "Ошибка в блоке groupCheck ({$groupCode})", LOG_ChangeChecker);
                    throw $e;
                }
            }

            // Исключаем логические группы
            $skipGroups = ['contactDetails', 'addressDetails', 'bankDetails', 'REQUISITES'];
            $simpleFieldsMap = array_diff_key($fieldsMap, array_flip($skipGroups));

            try {
                FieldMeta::get($entityTypeId);
            } catch (\Throwable $e) {
                Logs\File::AddMessage([
                    'entityTypeId' => $entityTypeId,
                    'exception'    => $e->getMessage(),
                    'trace'        => $e->getTraceAsString()
                ], "Ошибка в FieldMeta::get", LOG_ChangeChecker);
                throw $e;
            }
            try {
                $wasChanged = self::checkSimpleFields($newData, $entityTypeId, $entityId, array_keys($simpleFieldsMap), $context);
                $hasChanges = $hasChanges || $wasChanged;
            } catch (\Throwable $e) {
                Logs\File::AddMessage([
                    'entityTypeId' => $entityTypeId,
                    'entityId'     => $entityId,
                    'fields'       => array_keys($simpleFieldsMap),
                    'exception'    => $e->getMessage(),
                    'trace'        => $e->getTraceAsString()
                ], "Ошибка в checkSimpleFields", LOG_ChangeChecker);
                throw $e;
            }
            return $hasChanges;
        } catch (\Throwable $e) {
            Logs\File::AddMessage([
                'entityTypeId' => $entityTypeId,
                'entityId'     => $entityId,
                'context'      => $context,
                'exception'    => $e->getMessage(),
                'trace'        => $e->getTraceAsString()
            ], "Ошибка в ChangeChecker::check (глобальная)", LOG_ChangeChecker);
            throw $e;
        }
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function checkRequisitesChanges(int $entityTypeId, int $entityId, array $context = []): bool
    {
        try {
            $wasChanged = false;
            $details = Requisites::getRequisites($entityId);

            $newFormatted = json_encode($details, JSON_UNESCAPED_UNICODE);

            $fieldCode = 'REQUISITES';
            $fieldName = 'Основные реквизиты (REQUISITES)';

            if(Log::init(
                $entityTypeId,
                $entityId,
                $fieldCode,
                $fieldName,
                $newFormatted,
                $context,
                LOG_ChangeChecker
            ) === true) {
                $wasChanged = true;
            }
            return $wasChanged;

        } catch (Throwable $e) {
            Logs\File::AddMessage([
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'context' => $context,
            ],"Ошибка в checkRequisitesChanges: " . $e->getMessage(), LOG_ChangeChecker);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public static function checkBankDetailsChanges(int $entityTypeId, int $entityId, array $context = []): bool
    {
        try {
            $wasChanged = false;
            $banks = BankDetails::loadBankDetails($entityId);
            $banksNominal = BankDetails::loadBankNominalDetails($entityId);

            $details = [];
            if (!empty($banks)) {
                $details[] = self::formatBankDetail($banks, 'Расчетный счет');
            }
            if (!empty($banksNominal)) {
                $details[] = self::formatBankDetail($banksNominal, 'Номинальный счет');
            }

            $newFormatted = json_encode($details, JSON_UNESCAPED_UNICODE);

            $fieldCode = 'BANKING_DETAILS';
            $fieldName = 'Банковские реквизиты (bankDetails)';

            $isSuccess = Log::init(
                $entityTypeId,
                $entityId,
                $fieldCode,
                $fieldName,
                $newFormatted,
                $context,
                LOG_ChangeChecker
            );
            if ($isSuccess === true) {
                $wasChanged = true;
            }
            return $wasChanged;

        } catch (Throwable $e) {
            Logs\File::AddMessage("Ошибка в checkBankDetailsChanges: " . $e->getMessage(), [
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'context' => $context,
            ], LOG_ChangeChecker);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function checkAddressChanges(int $entityTypeId, int $entityId, array $context = []): bool
    {
        try {
            $wasChanged = false;
            $newAddress = AddressHelper::getNormalizedAddressArray($entityId);

            if (empty($newAddress)) {
                return false;
            }

            foreach ($newAddress as $type => $newFormatted) {
                $label = ($type === 'FACT') ? 'Фактический адрес' : 'Адрес регистрации';
                $fieldCode = "ADDRESS_" . strtoupper($type); // e.g., ADDRESS_FACT
                $fieldName = "Адреса ({$label})(addressDetails)";

                $isSuccess = Log::init(
                    $entityTypeId,
                    $entityId,
                    $fieldCode,
                    $fieldName,
                    $newFormatted,
                    $context,
                    LOG_ChangeChecker
                );
                if (!$isSuccess) {
                    continue;
                } else {
                    $wasChanged = true;
                }
            }
            return $wasChanged;
        } catch (Throwable $e) {
            Logs\File::AddMessage([
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'context' => $context,
            ], "Ошибка в checkAddressChanges: " . $e->getMessage(),LOG_ChangeChecker);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public static function checkContactDetailsChanges(array $newData, int $entityTypeId, int $entityId, array $context = []): bool
    {
        try {
            $wasChanged = false;
            $contactDetails = ContactHelper::extractAllContactDetails($newData);

            foreach ($contactDetails as $fieldCode => $entries) {
                if (empty($entries)) {
                    continue;
                }

                $labels = [
                    'PHONE' => 'Телефоны',
                    'EMAIL' => 'Email',
                    'IM'    => 'Мессенджеры',
                    'WEB'   => 'Веб-сайты',
                ];
                $label = $labels[$fieldCode] ?? $fieldCode;

                // Если только один тип — включим его в имя поля
                $types = array_unique(array_column($entries, 'VALUE_TYPE'));
                $typeLabel = (count($types) === 1 && $types[0] !== '')
                    ? ' — ' . ContactHelper::getValueTypeLabel($types[0], $fieldCode)
                    : '';

                $fieldName = "Контактные данные ({$label}{$typeLabel})(contactDetails)";
                $newJson = json_encode($entries, JSON_UNESCAPED_UNICODE);

                $isSuccess = Log::init(
                    $entityTypeId,
                    $entityId,
                    $fieldCode,
                    $fieldName,
                    $newJson,
                    $context
                );
                if (!$isSuccess) {
                    continue;
                } else {
                    $wasChanged = true;
                }
            }

            return $wasChanged;

        } catch (Throwable $e) {
            \KPLab\Logs\File::AddMessage([
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'context' => $context,
                'newData' => $newData,
            ], "Ошибка в checkContactDetailsChanges: " . $e->getMessage(),LOG_ChangeChecker);
            throw $e; // 🔥 обязательно выбрасывай ошибку
        }
    }

    /**
     * @throws Throwable
     */
    public static function checkSimpleFields( array $newData, int $entityTypeId, int $entityId, array $fieldsToCheck, array $context = []): bool
    {
        try {
            $wasChanged = false;
            foreach ($fieldsToCheck as $fieldCode) {
                if (!isset($newData[$fieldCode])) {
                    continue;
                }

                $fieldTitle = FieldMeta::getTitle($entityTypeId, $fieldCode);
                $fieldName = "{$fieldTitle}";

                $newValue = is_array($newData[$fieldCode])
                    ? json_encode($newData[$fieldCode], JSON_UNESCAPED_UNICODE)
                    : $newData[$fieldCode];

                $isSuccess = Log::init(
                    $entityTypeId,
                    $entityId,
                    $fieldCode,
                    $fieldName,
                    $newValue,
                    $context
                );
                if (!$isSuccess) {
                    continue;
                } else {
                    $wasChanged = true;
                }
            }
            return $wasChanged;
        }
        catch (Throwable $e) {
            \KPLab\Logs\File::AddMessage([
                'exception' => $e,
                'entityTypeId' => $entityTypeId,
                'entityId' => $entityId,
                'fieldsToCheck' => $fieldsToCheck,
                'context' => $context,
                'newData' => $newData,
            ], "Ошибка в checkSimpleFields: " . $e->getMessage(), LOG_ChangeChecker);
            throw $e;
        }
    }


    private static function formatBankDetail(array $bankDetails, string $label): array
    {
        $return = [];
        foreach ($bankDetails as $bankDetail) {
            $return[] = [
                'Основной счет' => $bankDetail['UF_CRM_PRIMARY_TXT'] ?? 'Нет',
                'Тип счета' => $bankDetail['UF_CRM_BD_ACC_TYPE'],
                'Наименование счета' => $bankDetail['NAME'] ?? $label,
                'Наименование банка' => $bankDetail['RQ_BANK_NAME'] ?? '',
                'Адрес банка'        => $bankDetail['RQ_BANK_ADDR'] ?? '',
                'БИК'                => $bankDetail['RQ_BIK'] ?? '',
                'Расчетный счёт'     => $bankDetail['RQ_ACC_NUM'] ?? '',
                'Валюта счёта'       => $bankDetail['RQ_ACC_CURRENCY'] ?? '',
                'Кор. счёт'          => $bankDetail['RQ_COR_ACC_NUM'] ?? '',
                'Комментарий'        => $bankDetail['COMMENTS'] ?? '',
            ];
        }
        return $return;
    }
}
