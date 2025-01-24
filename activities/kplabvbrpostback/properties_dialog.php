<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<tr>
    <td align="right" width="40%"><span class="adm-required-field"><span style="color:#FF0000;">*</span>ClickId:</span></td>
    <td width="60%">
        <input type="text" name="clickId" id="id_lead_clickId" value="<?= htmlspecialchars($arCurrentValues["clickId"]) ?>" size="50">
        <input type="button" value="..." onclick="BPAShowSelector('id_lead_clickId', 'string');">
    </td>
</tr>

