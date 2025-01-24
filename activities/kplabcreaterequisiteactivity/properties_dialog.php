<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
	die();
}
// Подключение основных библиотек Bitrix
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/core/core.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/core/core_ajax.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/core/core_window.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/core/core_popup.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/crm/crm.js');

\Bitrix\Main\Page\Asset::getInstance()->addJs(getLocalPath('activities/kplabcreaterequisiteactivity/script_2.js', '/local'));
/** @var \Bitrix\Bizproc\Activity\PropertiesDialog $dialog */

$entityTypeIdField = $dialog->getMap()['EntityTypeId'];
$entityIdField = $dialog->getMap()['EntityId'];
$presetIdField = $dialog->getMap()['PresetId'];

$chosenEntityTypeId = (int)$dialog->getCurrentValue('entity_type_id',0);
$chosenEntityId = htmlspecialcharsbx($dialog->getCurrentValue('entity_id',''));
$chosenPresetId = (int)$dialog->getCurrentValue('preset_id', 0);
$chosenRequisiteFields = $dialog->getCurrentValue('requisite_fields',[]);

$presetFields = [];
foreach ($dialog->getMap()['RequisiteFields']['Map'] as $presetId => $fieldsMap)
{
	$presetFields[$presetId] = [
		'documentType' => CCrmBizProcHelper::ResolveDocumentType($presetId),
		'fieldsMap' => $fieldsMap,
	];
}
// Логирование данных для отладки
file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/presetFields.log', "presetFields: " . print_r($presetFields, true), FILE_APPEND);
file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/currentValues.log', "currentValues: " . print_r($chosenRequisiteFields, true), FILE_APPEND);
?>
<tr>
	<td align="right" width="40%"><?=htmlspecialcharsbx($entityTypeIdField['Name'])?>:</td>
	<td width="60%">
		<?=
		$dialog->getFieldTypeObject($entityTypeIdField)->renderControl(
			[
				'Form' => $dialog->getFormName(),
				'Field' => $entityTypeIdField['FieldName']
			],
			$dialog->getCurrentValue($entityTypeIdField['FieldName']),
			true,
			0
		)
		?>
	</td>
</tr>
<tr>
	<td align="right" width="40%"><?=htmlspecialcharsbx($entityIdField['Name'])?>:</td>
	<td width="60%">
		<input type="text" name="entity_id" id="entity_id" value="<?= htmlspecialcharsbx($chosenEntityId) ?>" />
		<input type="button" value="..." onclick="BPAShowSelector('entity_id', 'string');" />
	</td>
</tr>
<tr>
	<td align="right" width="40%"><?=htmlspecialcharsbx($presetIdField['Name'])?>:</td>
	<td width="60%">
		<?=
		$dialog->getFieldTypeObject($presetIdField)->renderControl(
			[
				'Form' => $dialog->getFormName(),
				'Field' => $presetIdField['FieldName']
			],
			$dialog->getCurrentValue($presetIdField['FieldName']),
			true,
			0
		)
		?>
	</td>
</tr>

<!-- Секция для добавления условия -->
<tr>
	<td colspan="2">
		<table id="fields-map-container" class="adm-detail-content-table edit-table bizprocdesigner-properties-dialog-table"></table>
		<a href="#" id="id_bca_ccra_add_condition">Добавить условие</a>
	</td>
</tr>

<script>
    BX.ready(function()
    {
        console.log('BX.Crm.Activity:', BX.Crm.Activity);
        var script = new BX.Crm.Activity.KplabCreateRequisiteActivity({
            formName: '<?=CUtil::JSEscape($dialog->getFormName())?>',
            presetFieldsMap: <?= \Bitrix\Main\Web\Json::encode($presetFields) ?>,
            currentValues: <?= \Bitrix\Main\Web\Json::encode($chosenRequisiteFields) ?>,
        });
        script.init();
    });
</script>
