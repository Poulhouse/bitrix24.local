<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\IO\Path;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Web\Json;

\Bitrix\Main\UI\Extension::load(
    ['ui.buttons', 'ui.hint', 'ui.notification', 'ui.alerts', 'ui.dialogs.messagebox', 'ui.entity-selector']
);

$messages = array_merge(
    Loc::loadLanguageFile(
        \Bitrix\Main\Application::getDocumentRoot()
        . Path::normalize('/bitrix/components/bitrix/bizproc.automation/templates/.default/template.php')
    ),
    Loc::loadLanguageFile(
        \Bitrix\Main\Application::getDocumentRoot()
        . Path::normalize('/bitrix/components/bitrix/bizproc.workflow.edit/templates/.default/template.php')
    )
);
Asset::getInstance()->addJs(Path::normalize('/bitrix/activities/bitrix/crmgetdynamicinfoactivity/script.js'));

/** @var \Bitrix\Bizproc\Activity\PropertiesDialog $dialog */
global $APPLICATION;
$APPLICATION->IncludeComponent(
    'bitrix:bizproc.automation',
    '',
    [
        'API_MODE' => 'Y',
        'DOCUMENT_TYPE' => $dialog->getDocumentType(),
    ]
);

//$elementId = $dialog->getMap()['ElementId'];
$docType = $dialog->getMap()['DocumentType'];
$entityType = $dialog->getMap()['EntityType'];
$filterFields = $dialog->getMap()['FilterFields'];
$filterFieldsKey = $dialog->getMap()['FilterFieldsKey'];
$filterFieldsCondition = $dialog->getMap()['FilterFieldsCondition'];
$filterFieldsValue = $dialog->getMap()['FilterFieldsValue'];
$filterFieldsList = $dialog->getMap()['FilterFieldsList'];
?>





<style>
    [data-role="bca-cuda-filter-fields-container"] {
        display: flex;
    }
    div#filterFieldsValue {
        display: flex;
    }
</style>
<tr>
    <td align="right" width="40%" valign="top">
        <span class="adm-required-field"><?=htmlspecialcharsbx($entityType['Name'])?>:</span>
    </td>
    <td width="60%" id="doctype_container">
        <?=$dialog->renderFieldControl($entityType, null, false, \Bitrix\Bizproc\FieldType::RENDER_MODE_DESIGNER)?>
    </td>
</tr>

<tr id="filter_fields_container" data-role="bca-cuda-entity-type-id-dependent">
    <td align="right" width="40%"><?=htmlspecialcharsbx($filterFields['Name'])?>:</td>

    <td width="60%">
        <div data-role="bca-cuda-filter-fields-container">
            <div id="filterFieldsKey">
                <?=$dialog->renderFieldControl($filterFieldsKey, null, false, \Bitrix\Bizproc\FieldType::RENDER_MODE_DESIGNER)?>
            </div>
            <div id="filterFieldsCondition">
                <?=$dialog->renderFieldControl($filterFieldsCondition, null, false, \Bitrix\Bizproc\FieldType::RENDER_MODE_DESIGNER)?>
            </div>
            <div id="filterFieldsValue">
                <?=$dialog->renderFieldControl($filterFieldsValue, null, false, \Bitrix\Bizproc\FieldType::RENDER_MODE_DESIGNER)?>
                <input type="button" value="..." onclick="BPAShowSelector('id_filterFieldsValue', 'string');">
            </div>

            <span>При фильтрации Компании/Контакта рекомендуется выбрать условие "Содержит/Не содержит"</span>
        </div>
    </td>
</tr>

<tr>
    <td align="right" width="40%"><span class="adm-required-field"><?=htmlspecialcharsbx($filterFieldsList['Name'])?>:</span></td>
    <td width="60%">
        <div id="filterFieldsList">
            <?=$dialog->renderFieldControl($filterFieldsList, null, false, \Bitrix\Bizproc\FieldType::RENDER_MODE_DESIGNER)?>
        </div>
    </td>
</tr>
<script>
    BX.ready(function()
    {
        var container = BX('doctype_container');
        var select = container ? container.querySelector('[name="entity_type"]') : null;
        // var fieldsSelect = BX('fields_container').querySelector('select');
        var filterFieldsKey = BX('filterFieldsKey').querySelector('select');

        BX.bind(select, 'change', function()
            {
                var entityType = this.value;
                //BX.cleanNode(fieldsSelect);
                BX.cleanNode(filterFieldsKey);

                if (!entityType)
                {
                    return;
                }

                BX.ajax({
                    method: 'POST',
                    dataType: 'json',
                    url: '/bitrix/tools/bizproc_activity_ajax.php',
                    data:  {
                        'site_id': BX.message('SITE_ID'),
                        'sessid' : BX.bitrix_sessid(),
                        'document_type' : <?=Cutil::PhpToJSObject($dialog->getDocumentType())?>,
                        'activity': 'CrmFilterActivity',
                        'entity_type': entityType,  // Передаем тип сущности CRM
                        'form_name': <?=Cutil::PhpToJSObject($formName)?>
                    },
                    onsuccess: function(response)
                    {
                        if (response)
                        {
                            response.options.forEach(function(opt)
                            {
                                filterFieldsKey.appendChild(BX.create('option', {
                                    props: {value: opt.value},
                                    text: opt.text
                                }))
                            });
                        }
                    }
                });
            }
        );
    });
</script>
