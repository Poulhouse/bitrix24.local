<?php

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php';
$APPLICATION->SetTitle('Интернет-заявки');

CUtil::InitJSCore(['jquery']);

Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/components/bitrix/crm.interface.filter/templates/title/style.css');
Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/components/bitrix/intranet.contact_center.list/templates/.default/style.css');

?>
<div id="markup-pagetitle-below">
    <div class="crm-view-switcher pagetitle-align-right-container">
        <div class="crm-view-switcher-list">
            <a href="/crm/lead/automation/0/" class="ui-btn ui-btn-light-border ui-btn-no-caps ui-btn-themes ui-btn-round crm-robot-btn">Дневные</a>
            <div class="crm-view-switcher-list-item">Ночные</div>
            <div class="crm-view-switcher-list-item">Общие</div>
        </div>
    </div>
</div>

<div class="intranet-contact-block" style="margin-left: -13.8px; margin-top: -13.8px;">
    <div class="intranet-contact-wrap">
        <div class="intranet-contact-list" style="align-items: normal;">
            <div class="intranet-contact-center-item-block">
			    <div
                    class="intranet-contact-item intranet-contact-item-selected"
                    style="background-color: #669966">
                    <div class="intranet-contact-name">
                        <span class="intranet-contact-name-text">Ипотека</span>
                    </div>
                </div>
			    <div
                    class="intranet-contact-item intranet-contact-item-selected"
                    style="background-color: #999933">
                    <div class="intranet-contact-name">
                        <span class="intranet-contact-name-text">Сбережения</span>
                    </div>
                </div>
			    <div
                    class="intranet-contact-item intranet-contact-item-selected"
                    style="background-color: #3399CC">
                    <div class="intranet-contact-name">
                        <span class="intranet-contact-name-text">Займы Предпринимателям</span>
                    </div>
                </div>
			    <div
                    class="intranet-contact-item"
                    style="background-color: #6666CC">
                    <div class="intranet-contact-name">
                        <span class="intranet-contact-name-text">Автокредитование</span>
                    </div>
                </div>
			    <div
                    class="intranet-contact-item"
                    style="background-color: #996699">
                    <div class="intranet-contact-name">
                        <span class="intranet-contact-name-text">МКК</span>
                    </div>
                </div>
        </div>
        <div style="margin-left: 20px; width: calc(100% - 200px)">
        <?$APPLICATION->IncludeComponent(
            'bitrix:crm.lead',
            '.default',
            array(
                'SEF_MODE' => 'Y',
                'PATH_TO_CONTACT_SHOW' => '/crm/contact/show/#contact_id#/',
                'PATH_TO_CONTACT_EDIT' => '/crm/contact/edit/#contact_id#/',
                'PATH_TO_COMPANY_SHOW' => '/crm/company/show/#company_id#/',
                'PATH_TO_COMPANY_EDIT' => '/crm/company/edit/#company_id#/',
                'PATH_TO_DEAL_SHOW' => '/crm/deal/show/#deal_id#/',
                'PATH_TO_DEAL_EDIT' => '/crm/deal/edit/#deal_id#/',
                'PATH_TO_USER_PROFILE' => '/company/personal/user/#user_id#/',
                'PATH_TO_PRODUCT_EDIT' => '/crm/product/edit/#product_id#/',
                'PATH_TO_PRODUCT_SHOW' => '/crm/product/show/#product_id#/',
                'ELEMENT_ID' => $_REQUEST['lead_id'],
                'SEF_FOLDER' => '/local/pages/call_center/queue/',
                'SEF_URL_TEMPLATES' => array(
                    'index' => 'index.php',
                    'list' => 'list/',
                    'edit' => 'edit/#lead_id#/',
                    'show' => 'show/#lead_id#/',
                    'convert' => 'convert/#lead_id#/',
                    'import' => 'import/',
                    'service' => 'service/',
                    'dedupe' => 'dedupe/',
                ),
                'VARIABLE_ALIASES' => array(
                    'index' => array(),
                    'list' => array(),
                    'edit' => array(),
                    'show' => array(),
                    'convert' => array(),
                    'import' => array(),
                    'service' => array(),
                    'dedupe' => array(),
                ),
            )
        ); ?>
        </div>
    </div>
</div>

<style>
    .intranet-contact-name-text {
        color: #fff;
    }
    .intranet-contact-item {
        opacity: 0.5;
        min-height: 48px;
        margin-bottom: 10px;
    }
    .intranet-contact-item-selected {
        opacity: 1.0;
    }
</style>

<script type="text/javascript">
BX.ready(()=>{
    $('#uiToolbarContainer').append($('#markup-pagetitle-below'));
    $('#content-table > table').addClass('no-background');
    $('.intranet-contact-item').on('click', function() {
        $(this).toggleClass('intranet-contact-item-selected');
    });
});
</script>
<?php
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php';
