<?php
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use KPLab\Market\Service\HighloadLocator;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('highloadblock');

if (!Loader::includeModule('kplab.market')) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => 'Модуль kplab.market не установлен.',
        'TYPE' => 'ERROR',
    ]);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

if (!class_exists(HighloadLocator::class)) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => 'Класс HighloadLocator недоступен. Очистите кеш автозагрузки.',
        'TYPE' => 'ERROR',
    ]);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$APPLICATION->SetTitle('KPLab: Редактирование приложения');

try {
    $hlDefinition = HighloadLocator::getApplicationsDefinition();
} catch (\RuntimeException $exception) {
    CAdminMessage::ShowMessage(['MESSAGE' => $exception->getMessage(), 'TYPE' => 'ERROR']);
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$entity = HighloadBlockTable::compileEntity($hlDefinition);
$dataClass = $entity->getDataClass();

$ID = (int)($_REQUEST['ID'] ?? 0);
$isNew = ($ID <= 0);
$bApply = isset($_REQUEST['apply']);

// -------------------------------------------------------------------
// Сохранение формы
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid())
{
    $fields = [
        'UF_CODE'          => $_POST['UF_CODE'] ?? '',
        'UF_NAME'          => $_POST['UF_NAME'] ?? '',
        'UF_CLIENT_ID'     => $_POST['UF_CLIENT_ID'] ?? '',
        'UF_CLIENT_SECRET' => $_POST['UF_CLIENT_SECRET'] ?? '',
        'UF_SCOPE'         => $_POST['UF_SCOPE'] ?? '',
        'UF_DESCRIPTION'   => $_POST['UF_DESCRIPTION'] ?? '',
        'UF_STATUS'        => $_POST['UF_STATUS'] ?? '',
    ];

    if ($isNew) {
        $res = $dataClass::add($fields);
        if ($res->isSuccess()) {
            $ID = $res->getId();
            CAdminMessage::ShowMessage(['MESSAGE' => 'Приложение успешно добавлено', 'TYPE' => 'OK']);
            if (!$bApply) {
                LocalRedirect('kplab_market_apps.php?lang='.LANGUAGE_ID);
            } else {
                LocalRedirect('kplab_market_apps_edit.php?ID='.$ID.'&lang='.LANGUAGE_ID.'&mess=ok');
            }
        } else {
            CAdminMessage::ShowMessage(['MESSAGE' => implode('; ', $res->getErrorMessages()), 'TYPE' => 'ERROR']);
        }
    } else {
        $res = $dataClass::update($ID, $fields);
        if ($res->isSuccess()) {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Изменения сохранены', 'TYPE' => 'OK']);
            if (!$bApply) {
                LocalRedirect('kplab_market_apps.php?lang='.LANGUAGE_ID);
            } else {
                LocalRedirect('kplab_market_apps_edit.php?ID='.$ID.'&lang='.LANGUAGE_ID.'&mess=ok');
            }
        } else {
            CAdminMessage::ShowMessage(['MESSAGE' => implode('; ', $res->getErrorMessages()), 'TYPE' => 'ERROR']);
        }
    }
}

// -------------------------------------------------------------------
// Получение данных для формы
// -------------------------------------------------------------------
$data = [
    'UF_CODE'          => '',
    'UF_NAME'          => '',
    'UF_CLIENT_ID'     => '',
    'UF_CLIENT_SECRET' => '',
    'UF_SCOPE'         => '',
    'UF_DESCRIPTION'   => '',
    'UF_STATUS'        => '',
];

if (!$isNew)
{
    $res = $dataClass::getById($ID)->fetch();
    if ($res)
        $data = array_merge($data, $res);
    else
        CAdminMessage::ShowMessage(['MESSAGE' => 'Запись не найдена', 'TYPE' => 'ERROR']);
}

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

if ($_REQUEST['mess'] === 'ok')
    CAdminMessage::ShowMessage(['MESSAGE' => 'Сохранено успешно', 'TYPE' => 'OK']);

// -------------------------------------------------------------------
// Форма
// -------------------------------------------------------------------
$aTabs = [[
    'DIV' => 'edit1',
    'TAB' => $isNew ? 'Новое приложение' : 'Редактирование приложения',
    'ICON' => 'main_user_edit',
    'TITLE' => 'Параметры приложения'
]];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
    <form method="POST" action="<?=$APPLICATION->GetCurPage()?>?lang=<?=LANGUAGE_ID?><?=($ID ? '&ID='.$ID : '')?>">
        <?=bitrix_sessid_post()?>
        <?php
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        ?>

        <tr>
            <td width="40%">Код приложения:</td>
            <td><input type="text" name="UF_CODE" value="<?=htmlspecialcharsbx($data['UF_CODE'])?>" size="30" /></td>
        </tr>
        <tr>
            <td>Название:</td>
            <td><input type="text" name="UF_NAME" value="<?=htmlspecialcharsbx($data['UF_NAME'])?>" size="40" /></td>
        </tr>
        <tr>
            <td>Client ID:</td>
            <td><input type="text" name="UF_CLIENT_ID" value="<?=htmlspecialcharsbx($data['UF_CLIENT_ID'])?>" size="60" /></td>
        </tr>
        <tr>
            <td>Client Secret:</td>
            <td><input type="text" name="UF_CLIENT_SECRET" value="<?=htmlspecialcharsbx($data['UF_CLIENT_SECRET'])?>" size="60" /></td>
        </tr>
        <tr>
            <td>Scope:</td>
            <td><input type="text" name="UF_SCOPE" value="<?=htmlspecialcharsbx($data['UF_SCOPE'])?>" size="60" /></td>
        </tr>
        <tr>
            <td>Описание:</td>
            <td><textarea name="UF_DESCRIPTION" cols="60" rows="3"><?=htmlspecialcharsbx($data['UF_DESCRIPTION'])?></textarea></td>
        </tr>
        <tr>
            <td>Статус:</td>
            <td><input type="text" name="UF_STATUS" value="<?=htmlspecialcharsbx($data['UF_STATUS'])?>" size="20" /></td>
        </tr>

        <?php
        $tabControl->Buttons([
            "disabled" => false,
            "back_url" => "kplab_market_apps.php?lang=".LANGUAGE_ID
        ]);
        $tabControl->End();
        ?>
    </form>

<?php
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
