<?php
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use KPLab\Market\Service\HighloadLocator;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('kplab.market');
Loader::includeModule('highloadblock');

$APPLICATION->SetTitle('KPLab: Установки приложений');

try {
    $hlDefinition = HighloadLocator::getInstallationsDefinition();
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

$sTableID = "tbl_kplab_installs";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

// -------------------------------------------------------------------
// Таблица данных
// -------------------------------------------------------------------
$rsData = new CDBResult();
$rsData->InitFromArray(
    $dataClass::getList(['select' => ['*'], 'order' => ['ID' => 'DESC']])->fetchAll()
);
$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint('Установки приложений KPLab'));

$lAdmin->AddHeaders([
    ['id'=>'ID', 'content'=>'ID', 'sort'=>'ID', 'default'=>true],
    ['id'=>'UF_MEMBER_ID','content'=>'Member ID','sort'=>'UF_MEMBER_ID','default'=>true],
    ['id'=>'UF_DOMAIN','content'=>'Домен','sort'=>'UF_DOMAIN','default'=>true],
    ['id'=>'UF_APP_CODE','content'=>'Код приложения','sort'=>'UF_APP_CODE','default'=>true],
    ['id'=>'UF_ACCESS_TOKEN','content'=>'Access Token','sort'=>'UF_ACCESS_TOKEN','default'=>true],
    ['id'=>'UF_REFRESH_TOKEN','content'=>'Refresh Token','sort'=>'UF_REFRESH_TOKEN','default'=>true],
    ['id'=>'UF_EXPIRES_AT','content'=>'Истекает','sort'=>'UF_EXPIRES_AT','default'=>true],
    ['id'=>'UF_STATUS','content'=>'Статус','sort'=>'UF_STATUS','default'=>true],
    ['id'=>'UF_INSTALLED_AT','content'=>'Дата установки','sort'=>'UF_INSTALLED_AT','default'=>true],
    ['id'=>'UF_UNINSTALLED_AT','content'=>'Дата удаления','sort'=>'UF_UNINSTALLED_AT','default'=>true],
]);

while ($arRes = $rsData->NavNext(true, "f_"))
{
    $row =& $lAdmin->AddRow($f_ID, $arRes);

    $row->AddViewField("ID", $f_ID);
    $row->AddViewField("UF_MEMBER_ID", $f_UF_MEMBER_ID);
    $row->AddViewField("UF_DOMAIN", $f_UF_DOMAIN);
    $row->AddViewField("UF_APP_CODE", $f_UF_APP_CODE);
    $row->AddViewField("UF_ACCESS_TOKEN", $f_UF_ACCESS_TOKEN);
    $row->AddViewField("UF_REFRESH_TOKEN", $f_UF_REFRESH_TOKEN);
    $row->AddViewField("UF_EXPIRES_AT", $f_UF_EXPIRES_AT);
    $row->AddViewField("UF_STATUS", $f_UF_STATUS);
    $row->AddViewField("UF_INSTALLED_AT", $f_UF_INSTALLED_AT);
    $row->AddViewField("UF_UNINSTALLED_AT", $f_UF_UNINSTALLED_AT);
}

$lAdmin->AddFooter([
    ["title"=>"Всего","value"=>$rsData->SelectedRowsCount()],
    ["counter"=>true,"title"=>"Выбрано","value"=>"0"],
]);

$lAdmin->CheckListMode();

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
$lAdmin->DisplayList();
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
