<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loc::loadMessages(__FILE__);

$module_id = 'kplab.market';
$RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($RIGHT < "R") {
    $APPLICATION->AuthForm('Доступ запрещён');
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid() && $RIGHT == "W")
{
    Option::set($module_id, 'CLIENT_ID', $_POST['CLIENT_ID']);
    Option::set($module_id, 'CLIENT_SECRET', $_POST['CLIENT_SECRET']);
    Option::set($module_id, 'TOKEN_REFRESH_INTERVAL', (int)$_POST['TOKEN_REFRESH_INTERVAL']);
    Option::set($module_id, 'DEFAULT_APP_SCOPE', $_POST['DEFAULT_APP_SCOPE']);
    CAdminMessage::ShowMessage(['MESSAGE'=>'Сохранено','TYPE'=>'OK']);
}

$APPLICATION->SetTitle('KPLab: Настройки Marketplace Core');

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
?>

<form method="POST" action="<?=$APPLICATION->GetCurPage()?>?lang=<?=LANG?>">
    <?=bitrix_sessid_post()?>
    <table class="adm-detail-content-table edit-table">
        <tr class="heading"><td colspan="2">OAuth-настройки</td></tr>
        <tr>
            <td width="40%">Client ID:</td>
            <td><input type="text" name="CLIENT_ID" size="50" value="<?=htmlspecialcharsbx(Option::get($module_id,'CLIENT_ID'))?>"></td>
        </tr>
        <tr>
            <td>Client Secret:</td>
            <td><input type="text" name="CLIENT_SECRET" size="50" value="<?=htmlspecialcharsbx(Option::get($module_id,'CLIENT_SECRET'))?>"></td>
        </tr>

        <tr class="heading"><td colspan="2">Обновление токенов</td></tr>
        <tr>
            <td>Интервал обновления (сек):</td>
            <td><input type="number" name="TOKEN_REFRESH_INTERVAL" value="<?=htmlspecialcharsbx(Option::get($module_id,'TOKEN_REFRESH_INTERVAL',600))?>"></td>
        </tr>

        <tr class="heading"><td colspan="2">Приложения по умолчанию</td></tr>
        <tr>
            <td>Scope по умолчанию (через запятую):</td>
            <td><input type="text" name="DEFAULT_APP_SCOPE" size="60" value="<?=htmlspecialcharsbx(Option::get($module_id,'DEFAULT_APP_SCOPE','crm,bizproc,user'))?>"></td>
        </tr>
    </table>

    <br>
    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
</form>

<?php require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php'; ?>
