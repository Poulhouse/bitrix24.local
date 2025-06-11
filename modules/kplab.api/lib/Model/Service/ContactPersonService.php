<?php

namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\Loader;
use KPLab\Logs;
use KPLab\OrdLab\Entity;

define("LOG_CONTACT_PERSON_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_contactPerson.log");

class ContactPersonService
{
    public function get(int $entityId): array
    {
        $contactPersonDetails = [];

        try {
            \Bitrix\Main\Loader::includeModule('iblock');
            $IBLOCK_ID = 179;
            $prefix = "CO_";
            $arFilter = [
                "IBLOCK_ID" => $IBLOCK_ID,
                "=PROPERTY_1024_VALUE" => $prefix.$entityId,
                "ACTIVE_DATE" => "Y",
                "ACTIVE"=>"Y"
            ];
            $arSelect = ["*", "PROPERTY_*"];
            $res = \CIBlockElement::GetList(['ID' => 'ASC'], $arFilter, false, [], $arSelect);

            if (!$res) {
                Logs\File::AddMessage($entityId, "Не удалось получить контактных лиц", LOG_CONTACT_PERSON_SERVICE);
                return [];
            }

            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $arProps = $ob->GetProperties();

                $contactPersonDetails[] = [
                    'nameText' => $arFields['NAME'],
                    'birthDate' => $this->normalizeDate($arProps['DATA_ROZHDENIYA']['VALUE'] ?? ''),
                    'phone' => $arProps['TELEFON']['VALUE'],
                    'stateId' => $arProps['STATUS']['VALUE_XML_ID'] ?? '',
                    'commentText' => $arProps['KOMMENTARIY']['VALUE'] ?? '',
                ];
            }

        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в ContactPersonService->get() для компании {$entityId}", LOG_CONTACT_PERSON_SERVICE);
        }

        return $contactPersonDetails;
    }

    public static function set(int $entityId, $array, $prefix = "C_"): array|string
    {

        try {
            \Bitrix\Main\Loader::includeModule('iblock');
            $IBLOCK_ID = 179;
            $contactPersons = [];

            $arOrder = ['ID' => 'ASC'];
            $arFilter = [
                "IBLOCK_ID" => $IBLOCK_ID,
                "=PROPERTY_1024_VALUE" => $prefix . $entityId,
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y"
            ];
            $arNavStartParams = [];
            $arSelect = ["*", "PROPERTY_*"];
            $res = \CIBlockElement::GetList($arOrder, $arFilter, false, $arNavStartParams, $arSelect);

            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $ar_Result[] = array(
                    'ID' => $arFields['ID'], // id
                );
            }

            foreach ($ar_Result as $element) {
                \CIBlockElement::Delete($element["ID"]);
            }

            foreach ($array as $key => $value) {
                $name = $value['nameText'];
                $birthDate = $value['birthDate']; // 12.06.1984
                $phone = $value['phone']; //9225678329 (без 8 / +7 / 7)
                $stateId = $value['stateId']; // от 1 до 18
                $commentText = $value['commentText'];

                $el = new \CIBlockElement;

                $PROPERTY_VALUES = array();

                $STATUS_PROPERTY_ENUM_ID = Entity::getPropertyEnumIdByArray($stateId, $IBLOCK_ID);

                $PROPERTY_VALUES['STATUS'] = ["VALUE" => $STATUS_PROPERTY_ENUM_ID];
                $PROPERTY_VALUES['DATA_ROZHDENIYA'] = ['VALUE' => $birthDate];
                $PROPERTY_VALUES['TELEFON'] = $phone;
                $PROPERTY_VALUES['KOMMENTARIY'] = $commentText;
                $PROPERTY_VALUES['KLIENT'] = ['VALUE' => $prefix . $entityId];

                $arContactPersonArray = array(
                    "MODIFIED_BY" => 1, // элемент изменен текущим пользователем
                    "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
                    "IBLOCK_ID" => $IBLOCK_ID,
                    "PROPERTY_VALUES" => $PROPERTY_VALUES,
                    "NAME" => $name,
                    "ACTIVE" => "Y",            // активен
                );
                if ($contactPersonId = $el->Add($arContactPersonArray)) {
                    $contactPersons[] = $contactPersonId;
                } else {
                    $status = "Error: " . $el->LAST_ERROR;
                }
            }
            if (!empty($contactPersons)) {
                return $contactPersons;
            } else {
                return $status;
            }
        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в ContactPersonService->set() для компании {$entityId}", LOG_CONTACT_PERSON_SERVICE);
        }
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function normalizeDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        return (new \DateTime($date))->format('d.m.Y');
    }
}