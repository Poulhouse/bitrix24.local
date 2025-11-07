<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Highloadblock as HL;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('highloadblock');
Loader::includeModule('kplab.market');

$APPLICATION->SetTitle('KPLab: Установки приложений');

$hlId = Option::get('kplab.market', 'HL_INSTALLS_ID');
if (!$hlId) {
    echo BeginNote()."HL-блок не найден. Проверьте установку модуля.".EndNote();
    require $_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php";
    exit;
}

$hl = HL\HighloadBlockTable::getById($hlId)->fetch();
$entity = HL\HighloadBlockTable::compileEntity($hl);
$dataClass = $entity->getDataClass();

$rows = $dataClass::getList(['select'=>['*'], 'order'=>['UF_INSTALLED_AT'=>'DESC'], 'limit'=>50])->fetchAll();

require $_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/prolog_admin_after.php";
?>

<h2>🧩 Установленные порталы</h2>

<table class="adm-list-table">
    <tr class="adm-list-table-header">
        <td>Портал</td>
        <td>Приложение</td>
        <td>Member ID</td>
        <td>Статус</td>
        <td>Дата установки</td>
        <td>Истекает</td>
    </tr>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?=$row['UF_DOMAIN']?></td>
            <td><?=$row['UF_APP_CODE']?></td>
            <td><?=$row['UF_MEMBER_ID']?></td>
            <td><?=$row['UF_STATUS']?></td>
            <td><?=$row['UF_INSTALLED_AT']?></td>
            <td><?=$row['UF_EXPIRES_AT']?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require $_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php"; ?>
