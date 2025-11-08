<?php
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use KPLab\Market\Service\HighloadLocator;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('highloadblock');

if (!Loader::includeModule('kplab.market')) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => 'Модуль kplab.market не установлен.',
        'TYPE' => 'ERROR',
    ]);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

if (!class_exists(HighloadLocator::class)) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => 'Класс HighloadLocator недоступен. Очистите кеш автозагрузки.',
        'TYPE' => 'ERROR',
    ]);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$APPLICATION->SetTitle('KPLab: Приложения Marketplace');

try {
    $hlDefinition = HighloadLocator::getApplicationsDefinition();
} catch (\RuntimeException $exception) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => $exception->getMessage(),
        'TYPE' => 'ERROR',
    ]);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$entity = HighloadBlockTable::compileEntity($hlDefinition);
$dataClass = $entity->getDataClass();

$sTableID = "tbl_kplab_apps";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

// -------------------------------------------------------------------
// Обработка сохранений / удалений
// -------------------------------------------------------------------
if ($lAdmin->EditAction())
{
    foreach ($_POST['FIELDS'] as $ID => $fields)
    {
        if (!$lAdmin->IsUpdated($ID))
            continue;

        $res = $dataClass::update($ID, $fields);
        if (!$res->isSuccess())
            $lAdmin->AddGroupError(implode(', ', $res->getErrorMessages()), $ID);
    }
}

if ($arID = $lAdmin->GroupAction())
{
    if ($_REQUEST['action_target'] == 'selected')
    {
        $rsData = $dataClass::getList(['select' => ['ID']]);
        while ($ar = $rsData->fetch())
            $arID[] = $ar['ID'];
    }

    foreach ($arID as $ID)
    {
        $ID = (int)$ID;
        if ($ID <= 0)
            continue;

        switch ($_REQUEST['action'])
        {
            case "delete":
                $dataClass::delete($ID);
                break;
        }
    }
}

// -------------------------------------------------------------------
// Таблица данных
// -------------------------------------------------------------------
$rsData = new CDBResult();
$rsData->InitFromArray(
    $dataClass::getList(['select' => ['*'], 'order' => ['ID' => 'DESC']])->fetchAll()
);
$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint('Приложения KPLab'));

$lAdmin->AddHeaders([
    ['id'=>'ID', 'content'=>'ID', 'sort'=>'ID', 'default'=>true],
    ['id'=>'UF_CODE','content'=>'Код','sort'=>'UF_CODE','default'=>true],
    ['id'=>'UF_NAME','content'=>'Название','sort'=>'UF_NAME','default'=>true],
    ['id'=>'UF_CLIENT_ID','content'=>'Client ID','sort'=>'UF_CLIENT_ID','default'=>true],
    ['id'=>'UF_CLIENT_SECRET','content'=>'Client Secret','sort'=>'UF_CLIENT_SECRET','default'=>true],
    ['id'=>'UF_SCOPE','content'=>'Scope','sort'=>'UF_SCOPE','default'=>true],
    ['id'=>'UF_DESCRIPTION','content'=>'Описание','sort'=>'UF_DESCRIPTION','default'=>true],
    ['id'=>'UF_STATUS','content'=>'Статус','sort'=>'UF_STATUS','default'=>true],
]);

while ($arRes = $rsData->NavNext(true, "f_"))
{
    $row =& $lAdmin->AddRow($f_ID, $arRes);

    $row->AddViewField("ID", $f_ID);
    $row->AddInputField("UF_CODE", ['size'=>15]);
    $row->AddInputField("UF_NAME", ['size'=>25]);
    $row->AddInputField("UF_CLIENT_ID", ['size'=>40]);
    $row->AddInputField("UF_CLIENT_SECRET", ['size'=>40]);
    $row->AddInputField("UF_SCOPE", ['size'=>25]);
    $row->AddInputField("UF_DESCRIPTION", ['size'=>40]);
    $row->AddInputField("UF_STATUS", ['size'=>10]);

    $actions = [
        [
            "ICON" => "edit",
            "TEXT" => "Редактировать",
            "ACTION" => $lAdmin->ActionRedirect("kplab_market_apps_edit.php?ID=".$f_ID)
        ],
        [
            "ICON" => "delete",
            "TEXT" => "Удалить",
            "ACTION" => "if(confirm('Удалить приложение «{$f_NAME}»?')) ".$lAdmin->ActionDoGroup($f_ID, "delete")
        ],
    ];

    $row->AddActions($actions);
}

// -------------------------------------------------------------------
// Нижняя панель и кнопки
// -------------------------------------------------------------------
$lAdmin->AddFooter([
    ["title"=>"Всего","value"=>$rsData->SelectedRowsCount()],
    ["counter"=>true,"title"=>"Выбрано","value"=>"0"],
]);
$lAdmin->AddGroupActionTable(["delete"=>"Удалить"]);

$aContext = [
    [
        "TEXT"  => "Добавить приложение",
        "LINK"  => "kplab_market_apps_edit.php?lang=".LANGUAGE_ID,
        "ICON"  => "btn_new"
    ],
];
$lAdmin->AddAdminContextMenu($aContext, true);

$lAdmin->CheckListMode();

// -------------------------------------------------------------------
// Вывод страницы
// -------------------------------------------------------------------
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
$lAdmin->DisplayList();
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
