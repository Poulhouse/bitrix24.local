<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

    <script type="text/javascript">
        // Функция для подгрузки доступных полей PARENT_ID_* для выбранной CRM-сущности
        function loadParentFields() {
            console.log('loadParentFields вызвана');
            const formName = "<?= CUtil::JSEscape($arResult['formName']) ?>";
            const crmEntityTypeId = BX.util.trim(BX(formName + '_crmEntityTypeId').value);

            if (crmEntityTypeId === "") {
                return;
            }

            BX.ajax.loadJSON(
                "/local/activities/kplabbindingcrmactivity/get_parent_fields.php",
                { crmEntityTypeId: crmEntityTypeId},
                function(data) {
                    var select = BX(formName + '_parentField');
                    // Очистка существующих опций
                    select.options.length = 0;
                    if (data && typeof data == "object") {
                        for (var key in data) {
                            if (data.hasOwnProperty(key)) {
                                var option = new Option(data[key], key);
                                select.options.add(option);
                            }
                        }
                    }
                }
            );
        }

        BX.ready(function() {
            const formName = "<?= CUtil::JSEscape($arResult['formName']) ?>";
            const el = BX(formName + '_crmEntityTypeId');
            if(el) {
                el.addEventListener('change', loadParentFields);
            } else {
                console.log('Элемент с id ' + formName + '_crmEntityTypeId не найден');
            }
        });
    </script>

    <tr>
        <td>Тип CRM-сущности:</td>
        <td>
            <select name="crmEntityTypeId" id="<?= htmlspecialcharsbx($arResult['formName']) ?>_crmEntityTypeId">
                <option value="" <?= (empty($arResult['arCurrentValues']["crmEntityTypeId"])) ? 'selected="selected"' : '' ?>>
                    -- Выберите поле --
                </option>
                <?php foreach ($arResult['crmEntityTypes'] as $id => $name): ?>
                    <option value="<?= $id ?>" <?= ($id == $arResult['arCurrentValues']["crmEntityTypeId"]) ? 'selected="selected"' : '' ?>>
                        <?= htmlspecialcharsbx($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <td align="right" width="40%"><span class="adm-required-field"><span style="color:#FF0000;">*</span>ID CRM-сущности:</span></td>
        <td width="60%">
            <input type="text" name="crmEntityId" id="<?= htmlspecialcharsbx($arResult['formName']) ?>_crmEntityId" value="<?= htmlspecialcharsbx($arResult['arCurrentValues']["crmEntityId"]) ?>" size="50">
            <input type="button" value="..." onclick="BPAShowSelector('<?= htmlspecialcharsbx($arResult['formName']) ?>_crmEntityId', 'string');">
        </td>
    </tr>
    <tr>
        <td>Поле для привязки (PARENT_ID_*):</td>
        <td>
            <select name="parentField" id="<?= htmlspecialcharsbx($arResult['formName']) ?>_parentField">
                <?php
                // Если список уже сформирован на сервере и не пустой, выводим его
                if (!empty($arResult['parentFields'])) {
                    foreach ($arResult['parentFields'] as $field => $label) {
                        ?>
                        <option value="<?= htmlspecialcharsbx($field) ?>" <?= ($field == $arResult['arCurrentValues']["parentField"]) ? 'selected="selected"' : '' ?>>
                            <?= htmlspecialcharsbx($label) ?>
                        </option>
                        <?php
                    }
                } else {
                    // Иначе – пустой список, его подгрузит AJAX
                    ?>
                    <option value="">-- Выберите поле --</option>
                <?php } ?>
            </select>
        </td>
    </tr>
    <tr>
        <td align="right" width="40%"><span class="adm-required-field"><span style="color:#FF0000;">*</span>ID смарт-процесса:</span></td>
        <td width="60%">
            <input type="text" name="smartProcessId" id="id_smartProcessId" value="<?= htmlspecialcharsbx($arResult['arCurrentValues']["smartProcessId"]) ?>" size="50">
            <input type="button" value="..." onclick="BPAShowSelector('id_smartProcessId', 'string');">
        </td>
    </tr>
