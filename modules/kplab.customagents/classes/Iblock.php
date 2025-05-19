<?php

namespace KPLab\CustomAgents;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\Logs;

define("LOG_AGENTS_IBLOCK", $_SERVER['DOCUMENT_ROOT']."/local/logs/CustomAgents_IBLOCK.log");
class Iblock
{
/*
    public static function Delete($iblockId, $batchElements, $batchSections)
    {
        $elementTotalDeleted = 0;
        $sectionTotalDeleted = 0;
        $arTotalDeleted = [];

        Loader::includeModule('iblock');

        if ($batchElements > 0)
        {
            $query = ElementTable::query()
                ->setSelect(['ID'])
                ->setOrder(['ID' => 'DESC'])
                ->setFilter(['IBLOCK_ID' => $iblockId])
                ->setLimit($batchElements);

            $elements = $query->exec()->fetchAll();
            $idsElement = array_column($elements, 'ID');

            if (empty($idsElement)) {
                if ($batchSections == 0) {
                    return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
                }
                elseif ($batchSections > 0) {
                    $query = SectionTable::query()
                        ->setSelect(['ID'])
                        ->setOrder(['ID' => 'DESC'])
                        ->setFilter(['IBLOCK_ID' => $iblockId, 'IBLOCK_SECTION_ID' => null])
                        ->setLimit($batchSections);

                    $sections = $query->exec()->fetchAll();
                    $idsSection = array_column($sections, 'ID');

                    if (empty($idsSection)) {
                        return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
                    }

                    $successSection = true;

                    try {
                        foreach ($idsSection as $id) {
                            if (!\CIBlockSection::Delete($id)) {
                                throw new \Exception("Ошибка удаления раздела #{$id}");
                            }
                        }
                    }
                    catch (\Exception $e) {
                        $successSection = false;
                        Logs\File::AddMessage("Ошибка: " . $e->getMessage(), "KKTU IblockSection", LOG_AGENTS_IBLOCK);
                        return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
                    }

                    if ($successSection) {
                        $sectionTotalDeleted += count($idsSection);
                        Logs\File::AddMessage("Удалено {$sectionTotalDeleted} разделов", "KKTU IblockSection", LOG_AGENTS_IBLOCK);
                    }

                    return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
                }
            }

            $successElements = true;

            // Удаляем элементы
            try {
                foreach ($idsElement as $id) {
                    if (!\CIBlockElement::Delete($id)) {
                        throw new \Exception("Ошибка удаления элемента #{$id}");
                    }
                }
            } catch (\Exception $e) {
                $successElements = false;
                //$connection->rollbackTransaction();
                Logs\File::AddMessage("Ошибка: " . $e->getMessage(), "IblockElements", LOG_AGENTS_IBLOCK);
                return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
            }

            if ($successElements) {
                //$connection->commitTransaction();
                $elementTotalDeleted += count($idsElement);
                Logs\File::AddMessage("Удалено: {$elementTotalDeleted} элементов", "IblockElements", LOG_AGENTS_IBLOCK);
            }

            return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
        }

        if ($batchSections > 0 && $batchElements == 0) {
            $query = SectionTable::query()
                ->setSelect(['ID'])
                ->setOrder(['ID' => 'DESC'])
                ->setFilter(['IBLOCK_ID' => $iblockId, 'IBLOCK_SECTION_ID' => null])
                ->setLimit($batchSections);

            $sections = $query->exec()->fetchAll();
            $idsSection = array_column($sections, 'ID');

            if (empty($idsSection)) {
                return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
            }

            $successSection = true;

            try {
                foreach ($idsSection as $id) {
                    if (!\CIBlockSection::Delete($id)) {
                        throw new \Exception("Ошибка удаления раздела #{$id}");
                    }
                }
            }
            catch (\Exception $e) {
                $successSection = false;
                Logs\File::AddMessage("Ошибка: " . $e->getMessage(), "KKTU IblockSection", LOG_AGENTS_IBLOCK);
                return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
            }

            if ($successSection) {
                $sectionTotalDeleted += count($idsSection);
                Logs\File::AddMessage("Удалено {$sectionTotalDeleted} разделов", "KKTU IblockSection", LOG_AGENTS_IBLOCK);
            }

            return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
        }

        return "\KPLab\CustomAgents\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections});";
    }
*/
    public static function Delete($iblockId, $batchElements, $batchSections, $strOrderBy = '{"ID": "DESC"}'): string
    {
        Loader::includeModule('iblock');

        $orderBy = json_decode($strOrderBy, true);

        //$orderBy =  self::stringToArray($strOrderBy);

        // Проверка прав
        /*if (!self::checkPermissions($iblockId)) {
            Logs\File::AddMessage("Ошибка: Недостаточно прав для удаления", "Iblock", LOG_AGENTS_IBLOCK);
            return ""; // Останавливаем агент
        }*/

        // Удаляем элементы, если задано
        if ($batchElements > 0 && self::deleteElements($iblockId, $batchElements, $orderBy) === 0) {
            // Если элементы кончились, удаляем разделы
            if ($batchSections > 0) {
                self::deleteSections($iblockId, $batchSections, $orderBy);
            }
        }

        // Проверяем, нужно ли продолжать
        if (self::isIblockEmpty($iblockId)) {
            Logs\File::AddMessage("Инфоблок {$iblockId} полностью очищен", "Iblock", LOG_AGENTS_IBLOCK);
            return ""; // Останавливаем агент
        }

        // Продолжаем вызывать агент
        return "\\KPLab\\CustomAgents\\Iblock::Delete({$iblockId}, {$batchElements}, {$batchSections}, '{$strOrderBy}');";
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private static function deleteElements(int $iblockId, int $batchSize, array $orderBy): int
    {

        $query = ElementTable::query()
            ->setSelect(['ID'])
            ->setOrder($orderBy)
            ->setFilter(['IBLOCK_ID' => $iblockId])
            ->setLimit($batchSize);

        $elements = $query->exec()->fetchAll();
        if (empty($elements)) {
            return 0;
        }

        /*$connection = Application::getConnection();
        $connection->startTransaction();*/
        $deleted = 0;

        try {
            foreach ($elements as $element) {
                if (\CIBlockElement::Delete($element['ID'])) {
                    $deleted++;
                } else {
                    throw new \Exception("Ошибка удаления элемента #{$element['ID']}");
                }
            }
            //$connection->commitTransaction();
        } catch (\Exception $e) {
            //$connection->rollbackTransaction();
            Logs\File::AddMessage("Ошибка: " . $e->getMessage(), "IblockElements", LOG_AGENTS_IBLOCK);
            return 0;
        }

        Logs\File::AddMessage("Удалено элементов: {$deleted}", "IblockElements", LOG_AGENTS_IBLOCK);
        return $deleted;
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private static function deleteSections(int $iblockId, int $batchSize, array $orderBy): int
    {
        $query = SectionTable::query()
            ->setSelect(['ID'])
            ->setOrder($orderBy)
            ->setFilter(['IBLOCK_ID' => $iblockId, 'IBLOCK_SECTION_ID' => null])
            ->setLimit($batchSize);

        $sections = $query->exec()->fetchAll();
        $sections = array_column($sections, 'ID');
        if (empty($sections)) {
            return 0;
        }

        //$connection = Application::getConnection();
        //$connection->startTransaction();
        $deleted = 0;

        try {
            foreach ($sections as $section) {
                if (\CIBlockSection::Delete($section)) {
                    $deleted++;
                } else {
                    throw new \Exception("Ошибка удаления раздела #{$section}");
                }
            }
            //$connection->commitTransaction();
        } catch (\Exception $e) {
            //$connection->rollbackTransaction();
            Logs\File::AddMessage("Ошибка: " . $e->getMessage(), "IblockSections", LOG_AGENTS_IBLOCK);
            return 0;
        }

        Logs\File::AddMessage("Удалено разделов: {$deleted}", "IblockSections", LOG_AGENTS_IBLOCK);
        return $deleted;
    }

    private static function isIblockEmpty(int $iblockId): bool
    {
        $elementCount = ElementTable::getCount(['IBLOCK_ID' => $iblockId]);
        $sectionCount = SectionTable::getCount(['IBLOCK_ID' => $iblockId]);
        return ($elementCount === 0 && $sectionCount === 0);
    }
    private static function stringToArray($string): array {
        $jsonString = str_replace(["'", "=>"], ['"', ":"], $string);
        return json_decode($jsonString, true);
    }
}