<?php

use Bitrix\Main\Localization\Loc;

?>
<form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="kplab.pep">
    <input type="hidden" name="step" value="2">

    <p><input type="checkbox" name="delete_db" value="Y" checked> Удалить таблицы (`kplab_pep_sgn_log`, `...`)</p>
    <p><input type="checkbox" name="delete_options" value="Y" checked> Удалить все настройки модуля</p>

    <input type="submit" value="Удалить" class="adm-btn-save">
</form>
