<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

$MODULE_ID = 'kplab.exchange_log';
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
} while ($start !== null);
$SP = [];
foreach ($itemsList as $smartProcess) {
    $SP[$smartProcess['entityTypeId']] = \CRest::call('crm.item.fields', ['entityTypeId' => $smartProcess['entityTypeId'], 'useOriginalUfNames' => 'Y'])['result'];
    $SP[$smartProcess['entityTypeId']]['title'] = $smartProcess['title'];
}

// Для удобства формирования таблицы создадим массив столбцов: для каждого сущности будем иметь массив чекбоксов
$columns = array(); $columnsSP = array();
foreach($crmEntities as $entityKey => $entityLabel)
{
    $columns[$entityKey] = array();
    if(isset($fields[$entityKey]) && is_array($fields[$entityKey])) {
        // Преобразуем каждый элемент (ключ поля и его данные)
        foreach ($fields[$entityKey] as $fieldKey => $fieldData) {
            //print_r($fieldData);
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
/*
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid()) {
    Option::set($MODULE_ID, "server_name_prod", $_POST['server_name_prod']);
    Option::set($MODULE_ID, "server_name_test", $_POST['server_name_test']);
    Option::set($MODULE_ID, "iblock_id", $_POST['iblock_id']);
    Option::set($MODULE_ID, "iblock_id_test", $_POST['iblock_id_test']);
    Option::set($MODULE_ID, "property_id_token", $_POST['property_id_token']);

    echo '<div class="adm-info-message">Настройки сохранены</div>';
}*/

// Чтение сохранённых настроек
/*$serverNameProd = Option::get($MODULE_ID, "server_name_prod", "");
$serverNameTest = Option::get($MODULE_ID, "server_name_test", "");
$iblockId = Option::get($MODULE_ID, "iblock_id", "");
$iblockIdTest = Option::get($MODULE_ID, "iblock_id_test", "");
$propertyIDToken = Option::get($MODULE_ID, "property_id_token", "");
*/
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

                                <?php
                                if(isset($columns[$entityKey][$row])):
                                    $fieldKeyG = $columns[$entityKey][$row]["fieldKey"];
                                    $labelG = $columns[$entityKey][$row]["label"];
                                    $titleG = $columns[$entityKey][$row]["title"];
                                    // Определяем, отмечен ли чекбокс (если сохранённые настройки для этой сущности содержат поле)
                                    $checked = (isset($savedSettings['tracked_general'][$entityKey]) && in_array($fieldKeyG, $savedSettings['tracked_general'][$entityKey])) ? "checked" : "";
                                    ?>
                                        <td style="text-align:left !important;" class="adm-detail-content-cell-l">
                                            <label for="use_field_<?=$entityKey?>_<?=$fieldKeyG?>"><?=$titleG?><br>(<?=$labelG?>):</label>
                                            <a name="opt_use_field_<?=$entityKey?>_<?=$fieldKeyG?>"></a>
                                        </td>
                                        <td style="text-align:left !important;" class="adm-detail-content-cell-r">
                                            <input type="checkbox" id="use_field_<?=$entityKey?>_<?=$fieldKeyG?>" name="GENERAL_ENTITIES[<?=$entityKey?>][]" value="<?=$fieldKeyG ?>" <?=$checked?> class="adm-designed-checkbox">
                                            <label class="adm-designed-checkbox-label" for="use_field_<?=$entityKey?>_<?=$fieldKeyG?>" title=""></label>
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

               <!-- <td width="30%" class="adm-detail-content-cell-l">
                    <label for="use_field_<?=$fieldKey?>"><?=$fieldKey?>:</label>
                    <a name="opt_use_field_<?=$fieldKey?>"></a>
                </td>
                <td width="70%" class="adm-detail-content-cell-r">
                    <input type="checkbox" id="use_field_<?=$fieldKey?>" name="use_field_<?=$fieldKey?>" value="N" class="adm-designed-checkbox">
                    <label class="adm-designed-checkbox-label" for="use_field_<?=$fieldKey?>" title=""></label>
                </td>
            </tr>-->

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
                                        <input type="checkbox" id="use_field_<?=$entityKey?>_<?=$fieldKey?>" name="SP_ENTITIES[<?=$entityKey?>][]" value="<?=$label?>" <?=$checked?> class="adm-designed-checkbox">
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
    </form>

<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
