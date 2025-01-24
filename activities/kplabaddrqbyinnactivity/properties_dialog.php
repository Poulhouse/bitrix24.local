<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<tr>
    <td>ИНН компании:</td>
    <td>
        <input type="text" name="inn" value="<?= htmlspecialcharsbx($arCurrentValues['INN']) ?>" size="50">
    </td>
</tr>
<tr>
    <td>ID компании:</td>
    <td>
        <input type="text" name="company_id" value="<?= htmlspecialcharsbx($arCurrentValues['CompanyId']) ?>" size="50">
    </td>
</tr>
