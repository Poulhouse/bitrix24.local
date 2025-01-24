<?php
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

Loader::includeModule('iblock');

$iblockId = 183;
$propertyCode = "PROPERTY_1112"; // Поле для хранения токена

$ID = intval($_GET['ID']); // ID элемента для редактирования
$bVarsFromForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $apiKey = trim($_POST['api_key']);
    $partnerName = trim($_POST['partner_name']);

    if ($apiKey && $partnerName) {
        if ($ID > 0) {
            // Обновление элемента
            $result = ElementTable::update($ID, [
                'NAME' => $partnerName,
                'PROPERTY_VALUES' => [
                    $propertyCode => $apiKey
                ]
            ]);
        } else {
            // Добавление нового элемента
            $result = ElementTable::add([
                'IBLOCK_ID' => $iblockId,
                'NAME' => $partnerName,
                'PROPERTY_VALUES' => [
                    $propertyCode => $apiKey
                ]
            ]);
        }

        if ($result->isSuccess()) {
            LocalRedirect("kplab_api_admin.php?lang=".LANG);
        } else {
            $bVarsFromForm = true;
        }
    }
}

if ($ID > 0) {
    // Получение данных для редактирования
    $arRes = CIBlockElement::GetByID($ID)->Fetch();
    if (!$arRes) {
        $ID = 0;
    }
}

$APPLICATION->SetTitle($ID > 0 ? "Редактирование API ключа" : "Добавление API ключа");

// Форма для добавления/редактирования
$aTabs = [
    ["DIV" => "edit1", "TAB" => $ID > 0 ? "Редактировать" : "Добавить", "TITLE" => "Редактирование или добавление API ключа"],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

?>
    <form method="POST" action="<?= $APPLICATION->GetCurPage()?>?ID=<?= $ID ?>&lang=<?= LANG ?>">
        <?= bitrix_sessid_post(); ?>
        <?php
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        ?>
        <tr>
            <td width="40%">Название партнера:</td>
            <td width="60%"><input type="text" name="partner_name" size="40" value="<?= htmlspecialcharsbx($bVarsFromForm ? $_POST['partner_name'] : $arRes['NAME']) ?>"></td>
        </tr>
        <tr>
            <td width="40%">API ключ:</td>
            <td width="60%"><input type="text" name="api_key" size="40" value="<?= htmlspecialcharsbx($bVarsFromForm ? $_POST['api_key'] : $arRes['PROPERTY_'.$propertyCode.'_VALUE']) ?>"></td>
        </tr>
        <?php
        $tabControl->Buttons(["btnSave" => true, "btnCancel" => true, "back_url" => "kplab_api_admin.php?lang=".LANG]);
        $tabControl->End();
        ?>
    </form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
