<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
Loc::loadMessages(__FILE__);
Loader::IncludeModule('crm');

/** @var \Bitrix\Bizproc\Activity\PropertiesDialog $dialog */

$map = $dialog->getMap();

?>
<?php foreach ($map as $field): ?>
	<tr>
		<td align="right" width="40%"><?= htmlspecialcharsbx($field['Name']) ?>:</td>
		<td width="60%">
			<?=
			$dialog->getFieldTypeObject($field)->renderControl(
				[
					'Form' => $dialog->getFormName(),
					'Field' => $field['FieldName']
				],
				$dialog->getCurrentValue($field['FieldName']),
				true,
				0
			)
			?>
		</td>
	</tr>
<?php endforeach; ?>
