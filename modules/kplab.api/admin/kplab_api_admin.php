<?php
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Iblock\PropertyTable;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

Loader::includeModule('iblock');

// ID инфоблока для ключей
$iblockId = 183;

$APPLICATION->SetTitle("Список API ключей");

// Создание таблицы с использованием CAdminList
$sTableID = "api_keys_list";
$lAdmin = new CAdminList($sTableID);

// Добавление заголовков колонок
$lAdmin->AddHeaders([
    ["id" => "ID", "content" => "ID", "sort" => "ID", "default" => true],
    ["id" => "NAME", "content" => "Название", "sort" => "NAME", "default" => true],
    ["id" => "EMAIL", "content" => "E-mail", "sort" => "PROPERTY_EMAIL", "default" => true],
    ["id" => "IS_TEST", "content" => "Используется для тестирования?", "sort" => "PROPERTY_IS_TEST", "default" => true],
    ["id" => "JWT_TOKEN", "content" => "JWT-Token", "sort" => "PROPERTY_JWT_TOKEN", "default" => true],
]);

// Фильтр по инфоблоку
$arFilter = ["IBLOCK_ID" => $iblockId];
$res = CIBlockElement::GetList([], $arFilter, false, false, ["ID", "NAME", "PROPERTY_EMAIL", "PROPERTY_IS_TEST", "PROPERTY_JWT_TOKEN"]);

// Заполнение строк таблицы
while ($arRes = $res->Fetch()) {
    $row =& $lAdmin->AddRow($arRes['ID'], $arRes);
    $row->AddViewField("ID", $arRes['ID']);
    $row->AddViewField("NAME", htmlspecialcharsbx($arRes['NAME']));
    $row->AddViewField("EMAIL", htmlspecialcharsbx($arRes['PROPERTY_EMAIL_VALUE']));
    $row->AddViewField("IS_TEST", htmlspecialcharsbx($arRes['PROPERTY_IS_TEST_VALUE']));
    $row->AddViewField("JWT_TOKEN", htmlspecialcharsbx($arRes['PROPERTY_JWT_TOKEN_VALUE']));

    // Добавление действий для редактирования и удаления
    $actions = [];
    $actions[] = [
        "ICON" => "edit",
        "TEXT" => "Редактировать",
        "ACTION" => $lAdmin->ActionRedirect("kplab_api_edit.php?ID=".$arRes['ID']."&lang=".LANG),
        "DEFAULT" => true
    ];
    $actions[] = [
        "ICON" => "delete",
        "TEXT" => "Удалить",
        "ACTION" => "if(confirm('Удалить ключ?')) ".$lAdmin->ActionDoGroup($arRes['ID'], "delete")
    ];
    $row->AddActions($actions);
}

// Добавление подвала с количеством элементов
$lAdmin->AddFooter([
    ["title" => "Всего", "value" => $res->SelectedRowsCount()],
    ["counter" => true, "title" => "Выбрано", "value" => "0"]
]);

// Добавление действий для групповой обработки
$lAdmin->AddGroupActionTable([
    "delete" => "Удалить"
]);

// Кнопка "Добавить элемент"
$aContext = [
    [
        "TEXT" => "Добавить API ключ",
        "LINK" => "kplab_api_edit.php?lang=".LANG,
        "TITLE" => "Добавить новый API ключ",
        "ICON" => "btn_new"
    ]
];
$lAdmin->AddAdminContextMenu($aContext);

// Отображение таблицы
$lAdmin->CheckListMode();
$lAdmin->DisplayList();

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
