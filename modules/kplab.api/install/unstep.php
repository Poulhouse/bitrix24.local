<?php
if (!check_bitrix_sessid()) {
    return;
}
?>

<form action="<?= $APPLICATION->GetCurPage() . '?' . $_SERVER['QUERY_STRING'] ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="<?= htmlspecialcharsbx($GLOBALS["id"]) ?>">
    <input type="hidden" name="step" value="2">
    <input type="radio" name="save_data[]" value="Y" required> Сохранить данные в таблицах модуля<br><br>
    <input type="radio" name="save_data[]" value="N" required> НЕ Сохранить данные в таблицах модуля<br><br>
    <input type="submit" name="inst" value="Удалить модуль">
</form>
