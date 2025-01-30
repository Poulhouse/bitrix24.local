<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

$MODULE_ID = 'kplab.api.v2';
$APPLICATION->SetTitle("Настройки API");

if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid()) {
    Option::set($MODULE_ID, "server_name_prod", $_POST['server_name_prod']);
    Option::set($MODULE_ID, "server_name_test", $_POST['server_name_test']);
    Option::set($MODULE_ID, "iblock_id", $_POST['iblock_id']);
    Option::set($MODULE_ID, "iblock_id_test", $_POST['iblock_id_test']);
    Option::set($MODULE_ID, "property_id_token", $_POST['property_id_token']);

    echo '<div class="adm-info-message">Настройки сохранены</div>';
}

// Чтение сохранённых настроек
$serverNameProd = Option::get($MODULE_ID, "server_name_prod", "");
$serverNameTest = Option::get($MODULE_ID, "server_name_test", "");
$iblockId = Option::get($MODULE_ID, "iblock_id", "");
$iblockIdTest = Option::get($MODULE_ID, "iblock_id_test", "");
$propertyIDToken = Option::get($MODULE_ID, "property_id_token", "");

?>
    <form method="POST">
        <?= bitrix_sessid_post() ?>
        <label>Боевое доменное имя сервера:</label><br />
        <input type="text" name="server_name_prod" value="<?= htmlspecialcharsbx($serverNameProd) ?>" /><br /><br />

        <label>Тестовое доменное имя сервера:</label><br />
        <input type="text" name="server_name_test" value="<?= htmlspecialcharsbx($serverNameTest) ?>" /><br /><br />

        <label>IBLOCK_ID для API ключей:</label><br />
        <input type="number" name="iblock_id" value="<?= htmlspecialcharsbx($iblockId) ?>" /><br /><br />

        <label>Тестовый IBLOCK_ID для API ключей:</label><br />
        <input type="number" name="iblock_id_test" value="<?= htmlspecialcharsbx($iblockIdTest) ?>" /><br /><br />

        <label>Свойство для хранения токена:</label><br />
        <input type="text" name="property_id_token" value="<?= htmlspecialcharsbx($propertyIDToken) ?>" /><br /><br />

        <input type="submit" value="Сохранить настройки" />
    </form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
