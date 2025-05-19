<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<td align="right" width="40%"><span class="adm-required-field"><span style="color:#FF0000;">*</span>JSON строка:</span></td>
<td width="60%">
    <input type="text" id="json_data" name="json_data" style="width: 90%" value="<?= htmlspecialcharsbx($arCurrentValues['json_data']) ?>"/>
    <input type="button" value="..." onclick="BPAShowSelector('json_data', 'string');">
</td>


