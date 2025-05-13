<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;

$APPLICATION->SetTitle("Настройки Плана обмена");

if ($_SERVER["REQUEST_METHOD"] === "POST" && check_bitrix_sessid()) {
    $selected = $_POST['TRACK_FIELDS'] ?? [];
    \COption::SetOptionString("exchange_plan", "track_fields", serialize($selected));
    CAdminMessage::ShowMessage(["MESSAGE" => "Настройки сохранены", "TYPE" => "OK"]);
}

$savedFields = unserialize(\COption::GetOptionString("exchange_plan", "track_fields", 'a:0:{}'));

$fields = \CCrmCompany::GetFields();
$ufFields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields('CRM_COMPANY');

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<form method="POST">
    <?=bitrix_sessid_post()?>
    <h2>Выберите поля для отслеживания изменений</h2>
    <table class="adm-list-table" style="width: 100%">
        <thead>
        <tr class="adm-list-table-header">
            <td class="adm-list-table-cell">Поле</td>
            <td class="adm-list-table-cell">Код</td>
            <td class="adm-list-table-cell">Отслеживать</td>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fields as $code => $info): ?>
            <tr>
                <td><?=$info['title'] ?: $code?></td>
                <td><?=$code?></td>
                <td><input type="checkbox" name="TRACK_FIELDS[]" value="<?=$code?>" <?=in_array($code, $savedFields) ? 'checked' : ''?>></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($ufFields as $code => $info): ?>
            <tr>
                <td><?=$info['EDIT_FORM_LABEL'] ?: $code?></td>
                <td><?=$code?></td>
                <td><input type="checkbox" name="TRACK_FIELDS[]" value="<?=$code?>" <?=in_array($code, $savedFields) ? 'checked' : ''?>></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <br><input type="submit" value="Сохранить" class="adm-btn-save">
</form>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>
