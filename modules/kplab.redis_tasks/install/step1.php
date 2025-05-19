<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="kplab.redis_tasks">
    <input type="hidden" name="install" value="Y">
    <input type="hidden" name="step" value="2">

    <div style="margin: 20px;">
        <h3><?= Loc::getMessage('KPLAB_REDIS_TASKS_INSTALL_TITLE') ?></h3>
        <div style="margin-top: 20px;">
            <label>
                <input type="checkbox" name="install_composer" value="Y" checked>
                <?= Loc::getMessage('KPLAB_REDIS_TASKS_INSTALL_COMPOSER') ?>
            </label>
        </div>
    </div>

    <input type="submit" name="inst" value="<?= Loc::getMessage('MOD_INSTALL') ?>">
</form>