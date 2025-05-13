<?php
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\UserField\TypeBase;
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();
$eventManager->addEventHandlerCompatible('main', 'OnUserTypeBuildList', ['CUserTypeArray', 'GetUserTypeDescription']);

class CUserTypeArray extends TypeBase
{
    const USER_TYPE_ID = 'customarray';

    public static function GetUserTypeDescription()
    {
        return [
            'USER_TYPE_ID'  => static::USER_TYPE_ID,
            'CLASS_NAME'    => __CLASS__,
            'DESCRIPTION'   => 'Ассоциативный массив',
            'BASE_TYPE'     => \CUserTypeManager::BASE_TYPE_STRING,
            'EDIT_CALLBACK' => [__CLASS__, 'GetPublicEdit'],
            'VIEW_CALLBACK' => [__CLASS__, 'GetPublicView'],
        ];
    }

    public static function GetDBColumnType($arUserField)
    {
        global $DB;
        return $DB->type === 'mysql' ? 'text' : 'varchar(500)';
    }

    public static function OnBeforeSave($arUserField, $value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    public function onAfterFetch($userfield, $fetched)
    {
        return json_decode($fetched['VALUE'], true);
    }

    public static function GetPublicView($arUserField, $arAdditionalParameters = [])
    {
        return '<pre>' . print_r(json_decode($arUserField['VALUE'], true), true) . '</pre>';
    }

    public static function GetPublicEdit($arUserField, $arAdditionalParameters = array())
    {
        $fieldName = static::getFieldName($arUserField, $arAdditionalParameters);
        $fieldValue = static::getFieldValue($arUserField, $arAdditionalParameters);

        // Определяем массив данных
        $arrayData = [];
        if (is_array($fieldValue)) {
            $arrayData = $fieldValue; // Уже массив
        }

        // Поле ввода JSON и кнопка выбора переменной
        $jsonValue = htmlspecialcharsbx(json_encode($arrayData, JSON_UNESCAPED_UNICODE));
        $html = "<div>
        <input type='text' name='".htmlspecialcharsbx($fieldName)."' id='bp_json_field' value='{$jsonValue}'>
        <input type='button' value='...' onclick='BPAShowSelector(\"bp_json_field\", \"array\");'>
    </div>";

        // Блок для отображения структуры JSON
        $html .= "<div id='bp_json_structure'></div>";

        // JavaScript для обработки структуры JSON
        $html .= "<script>
        function renderJsonFields(data, prefix = '') {
            let html = '<ul>';
            for (let key in data) {
                if (typeof data[key] === 'object' && data[key] !== null) {
                    html += `<li><strong>\${prefix + key}:</strong>`;
                    html += renderJsonFields(data[key], prefix + key + '.');
                    html += '</li>';
                } else {
                    html += `<li>\${prefix + key}: <input type='text' value='\${data[key]}'></li>`;
                }
            }
            html += '</ul>';
            return html;
        }

        document.addEventListener('DOMContentLoaded', function() {
            let jsonField = document.getElementById('bp_json_field');
            let structureDiv = document.getElementById('bp_json_structure');

            function updateJsonStructure() {
                let jsonText = jsonField.value;
                try {
                    let parsedData = JSON.parse(jsonText);
                    structureDiv.innerHTML = renderJsonFields(parsedData);
                } catch (e) {
                    structureDiv.innerHTML = '<div style=\"color: red;\">Некорректный JSON</div>';
                }
            }

            jsonField.addEventListener('input', updateJsonStructure);
            updateJsonStructure();
        });
    </script>";

        return $html;
    }


}

