<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KPLab\Market\Orm\AppTable;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loc::loadMessages(__FILE__);

Loader::includeModule('kplab.market');

$APPLICATION->SetTitle('KPLab: Установки приложений');

// параметры сортировки и списка
$sTableID = "tbl_kplab_market";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

// фильтр
$FilterArr = [
    "find",
    "find_type",
    "find_status",
];
$lAdmin->InitFilter($FilterArr);

$arFilter = [];
if ($find && $find_type)
{
    $arFilter["%$find_type"] = $find;
}
if ($find_status)
{
    $arFilter["UF_STATUS"] = $find_status;
}

// запрос
$rsData = AppTable::getList([
    'filter' => $arFilter,
    'select' => ['*'],
    'order'  => [$by => $order],
]);
$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();

// вывод
$lAdmin->NavText($rsData->GetNavPrint('Установки'));

$lAdmin->AddHeaders([
    ['id'=>'ID', 'content'=>'ID', 'sort'=>'ID', 'default'=>true],
    ['id'=>'UF_DOMAIN', 'content'=>'Домен', 'default'=>true],
    ['id'=>'UF_MEMBER_ID', 'content'=>'Member ID', 'default'=>true],
    ['id'=>'UF_APP_CODE', 'content'=>'Приложение', 'default'=>true],
    ['id'=>'UF_STATUS', 'content'=>'Статус', 'default'=>true],
    ['id'=>'UF_EXPIRES_AT', 'content'=>'Истекает', 'sort'=>'UF_EXPIRES_AT', 'default'=>true],
]);

while ($arRes = $rsData->NavNext(true, "f_"))
{
    $row =& $lAdmin->AddRow($f_ID, $arRes);
    $statusColor = ($f_UF_STATUS === 'ACTIVE') ? 'green' : (($f_UF_STATUS === 'EXPIRED') ? 'red' : 'gray');
    $row->AddViewField("UF_STATUS", "<span style='color:$statusColor;'>$f_UF_STATUS</span>");
    $row->AddActions([
        [
            "ICON" => "edit",
            "TEXT" => "Продлить токен",
            "ACTION" => "if(confirm('Продлить токен для портала {$f_UF_DOMAIN}?')) window.location='kplab_market_admin.php?refresh=".$f_ID."&lang=".LANG."';",
        ],
    ]);
}

$lAdmin->AddAdminContextMenu([], false, false);
$lAdmin->CheckListMode();

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
?>

    <form name="find_form" method="GET" action="<?=$APPLICATION->GetCurPage()?>">
        <?php
        $oFilter = new CAdminFilter(
            $sTableID."_filter",
            ['Домен', 'Member ID', 'Статус']
        );
        $oFilter->Begin();
        ?>
        <tr>
            <td>Поиск:</td>
            <td>
                <input type="text" name="find" value="<?=htmlspecialcharsbx($find)?>">
                <select name="find_type">
                    <option value="UF_DOMAIN"<?=($find_type=="UF_DOMAIN"?" selected":"")?>>Домен</option>
                    <option value="UF_MEMBER_ID"<?=($find_type=="UF_MEMBER_ID"?" selected":"")?>>Member ID</option>
                    <option value="UF_APP_CODE"<?=($find_type=="UF_APP_CODE"?" selected":"")?>>Приложение</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Статус:</td>
            <td>
                <select name="find_status">
                    <option value="">(все)</option>
                    <option value="ACTIVE"<?=($find_status=="ACTIVE"?" selected":"")?>>ACTIVE</option>
                    <option value="EXPIRED"<?=($find_status=="EXPIRED"?" selected":"")?>>EXPIRED</option>
                    <option value="DELETED"<?=($find_status=="DELETED"?" selected":"")?>>DELETED</option>
                </select>
            </td>
        </tr>
        <?php
        $oFilter->Buttons(["table_id"=>$sTableID, "url"=>$APPLICATION->GetCurPage(), "form"=>"find_form"]);
        $oFilter->End();
        $oFilter->End();
        ?>
    </form>

<?php
$lAdmin->DisplayList();
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
