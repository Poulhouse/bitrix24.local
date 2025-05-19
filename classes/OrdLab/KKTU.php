<?php namespace KPLab\OrdLab;

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\Logs;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Config\Option;

define("LOG_ORDLAB_KTU", $_SERVER['DOCUMENT_ROOT']."/local/classes/OrdLab/KKTU.log");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");


class KKTU {
    const URL = "https://api.ord-lab.ru/api/v4/ktu";
    const IBLOCK_ID = "230"; //todo: dev=230 prod=236
    const point = "BX_ORDLAB";

    //Функция для запуска процесса в Б24
    public static function KtuNewList() {
        // Logs\File::AddMessage(null,"Запущена Функция для запуска процесса в Б24",LOG_ORDLAB_KTU);
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');
        $result = self::GetNewList($timeData);
        // Logs\File::AddMessage($result,"Результат получения ККТУ",LOG_ORDLAB_KTU);
        if($result){
            self::ProcessKtuList($result);
            return true;
        }
        return false;
    }

    //Получение списка ККТУ (GET /api/v3/ktu)
    public static function GetNewList($timeData, $jsonData = "", $objectData = []){
        $objectData['ITEM_ID'] = self::IBLOCK_ID;
        $objectData['ITEM_TYPE_ID'] = self::IBLOCK_ID;
        $objectData['ITEM_TITLE'] = "Обновление списка ККТУ";
        $objectData['METHOD'] = "GET";
        $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/services/lists/236/view/0/";

        if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
            $jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, self::URL, $jsonData, $objectData, $timeData, self::point);
            if(!empty($jsonResponse['success'])) {
                $responseStr = "{".$jsonResponse['success']."}";
                $responseArray = json_decode($responseStr, true);
                return $responseArray;
            }
            return null;
        }
    }

	 //Рекурсивная функция для обработки данных ККТУ, создания разделов и добавления/обновления конечных элементов
     public static function ProcessKtuList($result, $parentSectionId = false) {
        
        $flag = false;
        
        if (isset($result['data']) && is_array($result['data'])) {
            $data = $result['data'];
        } else {
            $data = $result;
        }
        foreach ($data as $item) {
            $value = $item['value']; 
            $title = $item['title']; 
            $hasChildren = isset($item['children']) && is_array($item['children']);
            if ($hasChildren) {
                $sectionId = self::GetSectionIdByValue($value, $title, $parentSectionId); 
                if ($sectionId) {
                    self::ProcessKtuList($item['children'], $sectionId);
                    // Logs\File::AddMessage($sectionId,"Создан раздел",LOG_ORDLAB_KTU);
                }
            } else {
                self::AddOrUpdateIBlockElement($title, $value, $parentSectionId);
                $flag = true;
            }
        }

        return $flag;
    }
    
   //Получение ID раздела, создание или обновление
    private static function GetSectionIdByValue($value, $title, $parentSectionId = false) {
        static $sectionCache = [];

        $cacheKey = $value . ($parentSectionId ? '_' . $parentSectionId : '');
        if (isset($sectionCache[$cacheKey])) {
            return $sectionCache[$cacheKey];
        }
        $filter = [
            "IBLOCK_ID" => self::IBLOCK_ID,
            "CODE" => $value,
        ];
        if ($parentSectionId) {
            $filter["SECTION_ID"] = $parentSectionId;
            $filter["INCLUDE_SUBSECTIONS"] = "N";
        }
        $res = \CIBlockSection::GetList(
            [],
            $filter,
            false,
            ['ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID'],
            false
        );
        if ($arSection = $res->Fetch()) {
            if ($arSection['NAME'] != $title) {
                $bs = new \CIBlockSection;
                $arFields = [
                    "NAME" => $title,
                ];
                $bs->Update($arSection['ID'], $arFields);
                print_r("Updated Section. NAME: ".$title." CODE: ".$value.PHP_EOL);
            }
            $sectionCache[$cacheKey] = $arSection['ID'];
            return $arSection['ID'];
        } else {
            $bs = new \CIBlockSection;
            $arFields = [
                "IBLOCK_ID" => self::IBLOCK_ID,
                "NAME" => $title,
                "CODE" => $value,
                "ACTIVE" => "Y",
            ];
            if ($parentSectionId) {
                $arFields["IBLOCK_SECTION_ID"] = $parentSectionId;
            }
            $sectionId = $bs->Add($arFields);
            if ($sectionId) {
                $sectionCache[$cacheKey] = $sectionId;
                return $sectionId;
            } else {
                print_r("Ошибка при создании раздела для значения " . $value . ": " . $bs->LAST_ERROR);
                return false;
            }
        }
    }
    //Добавление или обновление элемента в инфоблоке
    private static function AddOrUpdateIBlockElement($name, $code, $sectionId) {
        $filter = [
            "IBLOCK_ID" => self::IBLOCK_ID,
            "PROPERTY_1482" => $code,
        ];
        if ($sectionId) {
            $filter["IBLOCK_SECTION_ID"] = $sectionId;
            $filter["INCLUDE_SUBSECTIONS"] = "N";
        }
        $res = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID', 'NAME', 'IBLOCK_SECTION_ID']
        );
        if ($arElement = $res->Fetch()) {
            if ($arElement['NAME'] != $name) {
                $arUpdateFields = [
                    "NAME" => $name,
                ];
                $el = new \CIBlockElement;
                $resUpdate = $el->Update($arElement['ID'], $arUpdateFields);
                if (!$resUpdate) {
                    Logs\File::AddMessage(null,"Ошибка при обновлении поля (FIELDS) NAME: " . $el->LAST_ERROR,LOG_ORDLAB_KTU);
                }else{
                    //  print_r("Updated Element. NAME: ".$name." CODE: ".$code.PHP_EOL);
                }
            }
            if($sectionId != $arElement["IBLOCK_SECTION_ID"]){
                $arUpdateFields = [
                    "IBLOCK_SECTION" => array($sectionId),
                ];
                $el = new \CIBlockElement;
                $resUpdate = $el->Update($arElement['ID'], $arUpdateFields);
                if (!$resUpdate) {
                    Logs\File::AddMessage(null,"Ошибка при обновлении свойства IBLOCK_SECTION элемента: " . $el->LAST_ERROR,LOG_ORDLAB_KTU);
                }
            }
        } else {
            $arAddElement = [
                "IBLOCK_ID" => self::IBLOCK_ID,
                "NAME" => $name,
                "PROPERTY_VALUES" => [
                    1482 => $code,
                ],
                "ACTIVE" => "Y",
                "IBLOCK_SECTION" => array($sectionId),
            ];
            $el = new \CIBlockElement;
            $newElementId = $el->Add($arAddElement);
            if (!$newElementId) {
                Logs\File::AddMessage(null,"Ошибка при добавлении элемента: " . $el->LAST_ERROR,LOG_ORDLAB_KTU);
            } else {
                //  print_r("Created Element. NAME: ".$name." CODE: ".$code.PHP_EOL);
            }
        }
    }

    //Удаление всех элементов и разделов

    /**
     * @throws SqlQueryException
     * @throws ObjectPropertyException
     * @throws LoaderException
     * @throws ArgumentException
     * @throws SystemException
     */
    public static function DeleteAllElementsAndSections($batchSizeElements = 50, $batchSizeSections = 10, $loop = true): array
    {
        $batchSize = 50; // Размер пачки для удаления
        $totalDeleted = 0;
        $connection = \Bitrix\Main\Application::getConnection();
        $arTotalDeleted = [];

        Loader::includeModule('iblock');

        if (\CIBlock::GetPermission(self::IBLOCK_ID) >= 'W') {
            $query = ElementTable::query()
                ->setSelect(['ID'])
                ->setFilter(['IBLOCK_ID' => self::IBLOCK_ID])
                ->setLimit($batchSizeElements);

            while ($elements = $query->exec()->fetchAll()) {
                $ids = array_column($elements, 'ID');
                if (empty($ids)) {
                    break;
                }

                $connection->startTransaction();
                $success = true;

                // Удаляем элементы
                try {
                    foreach ($ids as $id) {
                        if (!\CIBlockElement::Delete($id)) {
                            throw new \Exception("Ошибка удаления элемента #{$id}");
                        }
                    }
                } catch (\Exception $e) {
                    $success = false;
                    $connection->rollbackTransaction();
                    echo "Ошибка: " . $e->getMessage() . "\n";
                    Logs\File::AddMessage("Ошибка: " . $e->getMessage(),"KKTU IblockElements",LOG_ORDLAB_KTU);
                    break; // Прерываем цикл при ошибке
                }

                if ($success) {
                    $connection->commitTransaction();
                    $totalDeleted += count($ids);
                    Logs\File::AddMessage("Удалено: {$totalDeleted} элементов","KKTU IblockElements",LOG_ORDLAB_KTU);
                    $arTotalDeleted += ['IblockElements' => $totalDeleted];
                }

                // Небольшая пауза для снижения нагрузки (опционально)
                sleep(1);
                if (!$loop) {break;}

            }


            $query = SectionTable::query()
                ->setSelect(['ID'])
                ->setFilter(['IBLOCK_ID' => self::IBLOCK_ID])
                ->setLimit($batchSizeSections);

            while ($sections = $query->exec()->fetchAll()) {
                $ids = array_column($sections, 'ID');

                if (empty($ids)) {
                    break;
                }

                // Старт транзакции для текущей пачки
                //$connection->startTransaction();
                $success = true;

                try {
                    foreach ($ids as $id) {
                        if (!\CIBlockSection::Delete($id)) {
                            throw new \Exception("Ошибка удаления раздела #{$id}");
                        }
                    }
                } catch (\Exception $e) {
                    $success = false;
                    //$connection->rollbackTransaction();
                    Logs\File::AddMessage("Ошибка: " . $e->getMessage(),"KKTU IblockSection",LOG_ORDLAB_KTU);
                    break; // Прерываем цикл при ошибке
                }

                if ($success) {
                    //$connection->commitTransaction();
                    $totalDeleted += count($ids);
                    $arTotalDeleted += ['IblockSection' => $totalDeleted];
                    Logs\File::AddMessage("Удалено {$totalDeleted} разделов","KKTU IblockSection",LOG_ORDLAB_KTU);
                }

                sleep(1); // Пауза между пачками (опционально)
                if (!$loop) {break;}

            }
        }

        return $arTotalDeleted;
    }
}
