<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
header('Content-Type: application/json');

$crmEntityTypeId = intval($_REQUEST['crmEntityTypeId']);
$crmEntityId = intval($_REQUEST['crmEntityId']);

$parentFields = array();

switch ($crmEntityTypeId) {
    case 2: // Сделка
        $fieldsInfo = CCrmDeal::GetFieldsInfo();
        foreach ($fieldsInfo as $fieldName => $fieldData) {
            if (str_starts_with($fieldName, "PARENT_ID_")) {
                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
            }
        }
        break;
    case 3: // Контакт
        $fieldsInfo = CCrmContact::GetFieldsInfo();
        foreach ($fieldsInfo as $fieldName => $fieldData) {
            if (str_starts_with($fieldName, "PARENT_ID_")) {
                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
            }
        }
        break;
    case 4: // Компания
        $fieldsInfo = CCrmCompany::GetFieldsInfo();
        foreach ($fieldsInfo as $fieldName => $fieldData) {
            if (str_starts_with($fieldName, "PARENT_ID_")) {
                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
            }
        }
        break;
    case 1: // Лид
        $fieldsInfo = CCrmLead::GetFieldsInfo();
        foreach ($fieldsInfo as $fieldName => $fieldData) {
            if (str_starts_with($fieldName, "PARENT_ID_")) {
                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
            }
        }
        break;
}

echo json_encode($parentFields);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
