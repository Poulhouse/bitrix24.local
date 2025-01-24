<?php

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php';

CUtil::InitJSCore(['jquery']);

Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/components/bitrix/crm.interface.filter/templates/title/style.css');
?>

<?$APPLICATION->IncludeComponent(
    'bitrix:crm.kanban',
    '',
    array(
            'ENTITY_TYPE' => 'LEAD',
            'SHOW_ACTIVITY' => 'N',
            'PATH_TO_IMPORT' => '',
    ),
    $component
);
?>

<div id="markup-pagetitle-below">
    <div class="crm-view-switcher pagetitle-align-right-container">
    <div class="crm-view-switcher-list">
        <div class="crm-view-switcher-list-item"><a href="queue/">Дневные</a></div>
        <div class="crm-view-switcher-list-item"><a href="queue/night.php">Ночные</a></div>
        <div class="crm-view-switcher-list-item"><a href="queue/">Общие</a></div>
    </div>
</div>

<style>
    .crm-view-switcher-list-item a {
        color: #fff;
    }
</style>

<script type="text/javascript">
BX.ready(()=>{
    $('#uiToolbarContainer').append($('#markup-pagetitle-below'));
    $('#content-table > table').addClass('no-background');
});
</script>
<?php
$APPLICATION->SetTitle('Интернет-заявки');
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php';
