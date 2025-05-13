<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$moduleId = "kplab.pep";
include $_SERVER["DOCUMENT_ROOT"] . "/local/modules/{$moduleId}/options.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid()) {
    if ($_POST['RestoreDefaults']) {
        Option::delete($moduleId);
    } else {
        foreach ($arAllOptions as $option) {
            $name = $option[0];
            $value = $_POST[$name] ?? '';
            Option::set($moduleId, $name, $value);
        }
    }
    LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . urlencode($moduleId) . "&lang=" . LANGUAGE_ID);
}
?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <table width="100%" class="adm-detail-content-table edit-table">
        <tbody>
            <?php
            foreach ($arAllOptions as $option) {
                [$name, $label, $default, $input] = $option;
                $val = Option::get($moduleId, $name, $default);
                ?>
                <tr>
                    <td width="40%"><?= $label ?>:</td>
                    <td width="60%">
                        <input type="<?= $input[0] ?>" size="<?= $input[1] ?>" name="<?= $name ?>" value="<?= htmlspecialcharsbx($val) ?>">
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?= bitrix_sessid_post() ?>
    <br>
    <input type="submit" name="Update" value="Сохранить" class="adm-btn-save">
    <input type="submit" name="RestoreDefaults" value="Сбросить">
</form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
