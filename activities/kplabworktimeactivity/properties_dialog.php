<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>


<tr>
	<td align="right" width="40%">Дата/Время, от:</td>
	<td width="60%">
		<span style="white-space:nowrap;">
			<input type="text" name="date_from" id="id_date_from" size="30" value="<?= htmlspecialchars($arCurrentValues["DateFrom"]) ?>">
			<?= CAdminCalendar::Calendar("date_from", "", "", true) ?>
		</span>
		<input type="button" value="..." onclick="BPAShowSelector('id_date_from', 'datetime');">
	</td>
</tr>

<tr>
	<td align="right" width="40%">Дата/Время, до:</td>
	<td width="60%">
		<span style="white-space:nowrap;">
			<input type="text" name="date_to" id="id_date_to" size="30" value="<?= htmlspecialchars($arCurrentValues["DateTo"]) ?>">
			<?= CAdminCalendar::Calendar("date_to", "", "", true) ?>
		</span>
		<input type="button" value="..." onclick="BPAShowSelector('id_date_to', 'datetime');">
	</td>
</tr>

<tr>
	<td align="right" width="40%"><span class="adm-required-field">Ответственный:</span>
	</td>
	<td width="60%">
		<?= CBPDocument::ShowParameterField(
			'user',
			'Responsible',
			$arCurrentValues['Responsible'])
		?>
	</td>
</tr>
