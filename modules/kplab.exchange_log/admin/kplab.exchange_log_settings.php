<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Kplab\Exchange_log\Helpers\FieldGroupMap;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

$MODULE_ID = 'kplab.exchange_log';
\Bitrix\Main\Loader::IncludeModule($MODULE_ID);
$APPLICATION->SetTitle("Настройки отслеживания");

if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid()) {
    Option::set($MODULE_ID, "tracked_general_entities", json_encode($_POST['GENERAL_ENTITIES'], JSON_UNESCAPED_UNICODE));
    Option::set($MODULE_ID, "tracked_sp_entities", json_encode($_POST['SP_ENTITIES'], JSON_UNESCAPED_UNICODE));
}

// Получаем сохранённые настройки в формате JSON (настройки отслеживаемых полей)
$savedSettings_TrackedGeneral_Json = Option::get($MODULE_ID, "tracked_general_entities", '{}');
$savedSettings_TrackedSp_Json = Option::get($MODULE_ID, "tracked_sp_entities", '{}');
$savedSettings['tracked_general'] = json_decode($savedSettings_TrackedGeneral_Json, true);
$savedSettings['tracked_sp'] = json_decode($savedSettings_TrackedSp_Json, true);

if (!is_array($savedSettings))
    $savedSettings = array();

// Определяем стандартные CRM-сущности (ключ => метка)
$crmEntities = array(
    "1"    => "Лиды",
    "2"    => "Сделки",
    "3" => "Контакты",
    "4" => "Компании"
);
$fields['1'] = \CRest::call('crm.lead.fields', [])['result'];
$fields['2'] = \CRest::call('crm.deal.fields', [])['result'];
$fields['3'] = \CRest::call('crm.contact.fields', [])['result'];
$fields['4'] = \CRest::call('crm.company.fields', [])['result'];
// Массив для накопления всех типов
$itemsList = [];
$start = 0;

do {
    // Параметры запроса: если старт не 0 — передаём курсор
    $params = [];
    if ($start > 0) {
        $params['start'] = $start;
    }
    // Делаем вызов
    $response = \CRest::call('crm.type.list', $params);
    // Извлекаем блок типов (может быть пустым)
    $types = $response['result']['types'] ?? [];
    // Накатываем в результирующий массив
    $itemsList = array_merge($itemsList, $types);

    // Если API вернуло курсор next — запомним его, иначе выйдем из цикла
    if (isset($response['next']) && $response['next'] !== null) {
        $start = (int)$response['next'];
    } else {
        $start = null;
    }

// Повторяем, пока есть курсор next
}
while ($start !== null);

$SP = [];
foreach ($itemsList as $smartProcess) {
    $SP[$smartProcess['entityTypeId']] = \CRest::call('crm.item.fields', ['entityTypeId' => $smartProcess['entityTypeId'], 'useOriginalUfNames' => 'Y'])['result'];
    $SP[$smartProcess['entityTypeId']]['title'] = $smartProcess['title'];
}

// Для удобства формирования таблицы создадим массив столбцов: для каждого сущности будем иметь массив чекбоксов
$columns = array();
$columnsSP = array();

$columns[3][] = [
    "fieldKey" => 'REQUISITES',
    "label" => 'REQUISITES',
    "title" => "Общие реквизиты"
];
$columns[4][] = [
    "fieldKey" => 'REQUISITES',
    "label" => 'REQUISITES',
    "title" => "Общие реквизиты"
];
foreach($crmEntities as $entityKey => $entityLabel)
{
    $addedGroups = []; // Чтобы не дублировать группы
    $map = [
        'contactDetails' => ['PHONE', 'EMAIL', 'IM', 'WEB', "LINK"],
        'addressDetails' => [
            'ADDRESS', 'ADDRESS_2', 'ADDRESS_REGION', 'ADDRESS_PROVINCE',
            'ADDRESS_CITY', 'ADDRESS_COUNTRY', 'ADDRESS_COUNTRY_CODE',
            'ADDRESS_POSTAL_CODE', 'ADDRESS_LOC_ADDR_ID', 'ADDRESS_LEGAL',
            'REG_ADDRESS', 'REG_ADDRESS_2', 'REG_ADDRESS_CITY', 'REG_ADDRESS_POSTAL_CODE',
            'REG_ADDRESS_REGION', 'REG_ADDRESS_PROVINCE', 'REG_ADDRESS_COUNTRY', 'REG_ADDRESS_COUNTRY_CODE',
            'REG_ADDRESS_LOC_ADDR_ID'
        ],
        'bankDetails' => ['BANKING_DETAILS'],
    ];
    // Обратная карта: field => groupName
    $fieldToGroupMap = [];
    foreach ($map as $groupName => $fieldsInGroup) {
        foreach ($fieldsInGroup as $f) {
            $fieldToGroupMap[$f] = $groupName;
        }
    }
    if(isset($fields[$entityKey]) && is_array($fields[$entityKey])) {
        // Преобразуем каждый элемент (ключ поля и его данные)
        foreach ($fields[$entityKey] as $fieldKey => $fieldData) {
            // Если поле принадлежит группе — добавим группу 1 раз
            if (isset($fieldToGroupMap[$fieldKey])) {
                $groupName = $fieldToGroupMap[$fieldKey];

                // Если ещё не добавляли группу — добавим её в список
                if (!in_array($groupName, $addedGroups)) {
                    $columns[$entityKey][] = [
                        "fieldKey" => $groupName,
                        "label" => $groupName,
                        "title" => FieldGroupMap::getGroupTitle($groupName), // красивое название
                    ];
                    $addedGroups[] = $groupName;
                }

                // Само поле не добавляем — переходим к следующему
                continue;
            }


            // Обычные (не сгруппированные) поля
            $columns[$entityKey][] = array(
                "fieldKey" => $fieldKey,
                "label" => $fieldKey,
                "title" => $fieldData['formLabel'] ?? $fieldData['title']
            );
        }
    }
}

foreach($SP as $entityKey => $entityData)
{
    $columnsSP[$entityKey] = array();
    if(isset($entityData['fields']) && is_array($entityData['fields'])) {
        // Преобразуем каждый элемент (ключ поля и его данные)
        foreach ($entityData['fields'] as $fieldKey => $fieldData) {
            $columnsSP[$entityKey][] = array(
                "fieldKey" => $fieldKey,
                "label" => $fieldData['upperName'] ?? $fieldKey,
                "title" => $fieldData['title']
            );
        }
    }
}

// Определим максимальное число строк (наибольшее количество полей в колонке)
$maxRows = 0;
foreach($columns as $col)
{
    $maxRows = max($maxRows, count($col));
}

$maxRowsSP = 0;
foreach($columnsSP as $col)
{
    $maxRowsSP = max($maxRowsSP, count($col));
}

$aTabs = array(
    array("DIV" => "crm", "TAB" => "Общие типы CRM", "ICON" => "", "TITLE" => "Настройки для стандартных CRM‑сущностей"),
    array("DIV" => "smart", "TAB" => "Смарт‑процессы", "ICON" => "", "TITLE" => "Настройки для смарт‑процессов")
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
?>
<? if ($_GET["mess"] == "ok"): ?>
    <div class="adm-message success">
        <div class="adm-message-content">Настройки успешно сохранены</div>
    </div>
<? endif; ?>
<style>
    tr.heading td {
        border-right: 1px solid #F5F9F9;
    }
    tr.crm_entity > td {
        border-right: 1px solid #CCCCCC;
    }
</style>
    <form method="POST" action="<?=$APPLICATION->GetCurPage()?>?lang=<?=LANG?>">
        <?=bitrix_sessid_post();?>

        <div id="selected-fields-summary" class="adm-info-message" style="display:none; margin-bottom:15px; padding: 10px;">
            <div style="margin-bottom: 5px;"><strong>Выбранные поля:</strong></div>
            <div id="selected-fields-list" style="
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                max-height: 200px;
                overflow-y: auto;
            "></div>
        </div>


        <?$tabControl->Begin();?>

        <?$tabControl->BeginNextTab();?>
            <tr class="heading">
                <? foreach ($crmEntities as $entityKey => $entityLabel): ?>

                    <td><b><?=$entityLabel?></b></td>

                <? endforeach; ?>
            </tr>

            <? for($row = 0; $row < $maxRows; $row++): ?>

                <tr class="crm_entity">

                <? foreach ($crmEntities as $entityKey => $entityLabel): ?>
                    <td width="100%" style="text-align:left !important;">
                        <table>
                            <tr>

                                <?php if (isset($columns[$entityKey][$row])): ?>
                                    <?php
                                    $fieldKeyG = $columns[$entityKey][$row]["fieldKey"];
                                    $labelG    = $columns[$entityKey][$row]["label"];
                                    $titleG    = $columns[$entityKey][$row]["title"];
                                    $checked   = (isset($savedSettings['tracked_general'][$entityKey]) && in_array($fieldKeyG, array_keys($savedSettings['tracked_general'][$entityKey]))) ? "checked" : "";
                                    ?>
                                    <td class="adm-detail-content-cell-l" style="text-align:left !important;">
                                        <div style="padding:4px 0;">
                                            <strong><?=htmlspecialchars($titleG)?></strong><br>
                                            <small style="color:#888;">Код: <?=htmlspecialchars($fieldKeyG)?></small>
                                        </div>
                                    </td>
                                    <td class="adm-detail-content-cell-r">
                                        <input type="checkbox" id="use_field_<?=$entityKey?>_<?=$fieldKeyG?>"
                                               name="GENERAL_ENTITIES[<?=$entityKey?>][<?=$fieldKeyG?>]"
                                               value="<?=htmlspecialchars($titleG)?>" <?=$checked?> class="adm-designed-checkbox">
                                        <label class="adm-designed-checkbox-label" for="use_field_<?=$entityKey?>_<?=$fieldKeyG?>"></label>
                                    </td>

                                <?php endif; ?>

                            </tr>
                        </table>
                    </td>

                <?php endforeach; ?>

                </tr>

        <?php endfor; ?>

        <?$tabControl->BeginNextTab();?>
        <tr class="heading">
            <? foreach ($SP as $entityKey => $entityData): ?>

                <td><b><?=$entityData['title']?></b></td>

            <? endforeach; ?>
        </tr>

        <? for($row = 0; $row < $maxRowsSP; $row++): ?>

            <tr class="crm_entity">

                <? foreach ($SP as $entityKey => $entityData): ?>
                    <td width="100%" style="text-align:left !important;" >
                        <table>
                            <tr>

                                <?php
                                if(isset($columnsSP[$entityKey][$row])):
                                    $fieldKey = $columnsSP[$entityKey][$row]["fieldKey"];
                                    $label = $columnsSP[$entityKey][$row]["label"];
                                    $title = $columnsSP[$entityKey][$row]["title"];
                                    // Определяем, отмечен ли чекбокс (если сохранённые настройки для этой сущности содержат поле)
                                    $checked = (isset($savedSettings['tracked_sp'][$entityKey]) && in_array($label, $savedSettings['tracked_sp'][$entityKey])) ? "checked" : "";
                                    ?>
                                    <td style="text-align:left !important;" class="adm-detail-content-cell-l">
                                        <label for="use_field_<?=$entityKey?>_<?=$fieldKey?>"><?=$title?><br>(<?=$label?>):</label>
                                        <a name="opt_use_field_<?=$entityKey?>_<?=$fieldKey?>"></a>
                                    </td>
                                    <td style="text-align:left !important;" class="adm-detail-content-cell-r">
                                        <input type="checkbox" id="use_field_<?=$entityKey?>_<?=$fieldKey?>"
                                               name="SP_ENTITIES[<?=$entityKey?>][<?=$fieldKey?>]"
                                               value="<?=htmlspecialchars($title)?>" <?=$checked?> class="adm-designed-checkbox">
                                        <label class="adm-designed-checkbox-label" for="use_field_<?=$entityKey?>_<?=$fieldKey?>" title=""></label>
                                    </td>
                                <?php else: ?>
                                    <!-- Если в данной колонке нет поля для этой строки, оставляем пустую ячейку -->
                                <?php endif; ?>

                            </tr>
                        </table>
                    </td>

                <?php endforeach; ?>

            </tr>

        <?php endfor; ?>


        <?$tabControl->EndTab();?>
        <?$tabControl->Buttons();?>
            <input type="submit" value="Сохранить настройки" class="adm-btn-save"/>
        <?$tabControl->End();?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const summaryBlock = document.getElementById('selected-fields-summary');
                const fieldList = document.getElementById('selected-fields-list');
                const checkboxes = document.querySelectorAll('input[type=checkbox][name^="GENERAL_ENTITIES"], input[type=checkbox][name^="SP_ENTITIES"]');

                const entityNames = <?=json_encode($crmEntities + array_map(fn($sp) => $sp['title'], $SP), JSON_UNESCAPED_UNICODE)?>;

                const renderSelected = () => {
                    const selected = Array.from(checkboxes).filter(cb => cb.checked);
                    fieldList.innerHTML = '';

                    if (selected.length === 0) {
                        summaryBlock.style.display = 'none';
                        return;
                    }

                    summaryBlock.style.display = 'block';

                    // Группировка по entityId
                    const groups = {};

                    selected.forEach(cb => {
                        const match = cb.name.match(/\[([^\]]+)\]\[([^\]]+)\]/);
                        if (!match) return;

                        const entityId = match[1];
                        const fieldCode = match[2];
                        const fieldTitle = cb.value;

                        if (!groups[entityId]) groups[entityId] = [];
                        groups[entityId].push({ fieldCode, fieldTitle });
                    });

                    // Отрисовка групп
                    Object.entries(groups).forEach(([entityId, fields]) => {
                        const entityTitle = entityNames[entityId] ?? `Сущность ${entityId}`;
                        const wrapper = document.createElement('div');
                        wrapper.style.marginBottom = '10px';

                        const heading = document.createElement('div');
                        heading.innerHTML = `<strong style="font-size:13px;">${entityTitle}</strong>`;
                        heading.style.marginBottom = '5px';
                        wrapper.appendChild(heading);

                        const grid = document.createElement('div');
                        grid.style.cssText = 'display: flex; flex-wrap: wrap; gap: 5px;';

                        fields.forEach(({ fieldCode, fieldTitle }) => {
                            const fieldBlock = document.createElement('div');
                            fieldBlock.style.cssText = `
                        background: #eef3f6;
                        border: 1px solid #cdd9e1;
                        border-radius: 3px;
                        padding: 4px 8px;
                        font-size: 12px;
                        line-height: 1.2;
                        display: inline-block;
                    `;
                            fieldBlock.innerHTML = `
                        ${fieldTitle}
                        <small style="color:#888;">(${fieldCode})</small>
                        <a href="#" style="color: red; margin-left: 6px;" data-entity="${entityId}" data-code="${fieldCode}">[×]</a>
                    `;
                            grid.appendChild(fieldBlock);
                        });

                        wrapper.appendChild(grid);
                        fieldList.appendChild(wrapper);
                    });
                };

                // Удаление поля по [×]
                fieldList.addEventListener('click', function (e) {
                    if (e.target.tagName === 'A') {
                        e.preventDefault();
                        const entity = e.target.getAttribute('data-entity');
                        const code = e.target.getAttribute('data-code');
                        const cb = document.querySelector(`input[name="GENERAL_ENTITIES[${entity}][${code}]"], input[name="SP_ENTITIES[${entity}][${code}]"]`);
                        if (cb) {
                            cb.checked = false;
                            cb.dispatchEvent(new Event('change'));
                        }
                    }
                });

                checkboxes.forEach(cb => cb.addEventListener('change', renderSelected));
                renderSelected();
            });
        </script>


    </form>

<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
