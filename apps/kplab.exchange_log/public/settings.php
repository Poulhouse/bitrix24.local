<?php
// public/settings.php
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Symfony\Component\HttpFoundation\Request;

//require_once __DIR__ . '/../vendor/autoload.php';

$appProfile = ApplicationProfile::initFromArray([
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID' => 'local.67f7ee62621d91.33258822',
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => 'g8Rb25w35ZfA725uZCRQ94v8qWK6ndje3quyaj9WhJ1SfcE3fh',
    'BITRIX24_PHP_SDK_APPLICATION_SCOPE' => 'crm,user_basic,user.userfield'
]);

$B24 = ServiceBuilderFactory::createServiceBuilderFromPlacementRequest(
    Request::createFromGlobals(),
    $appProfile
);

/**
 * Пример фиксированного списка стандартных CRM-сущностей
 */
$crmEntities = [
    'crm.deal',
    'crm.lead',
    'crm.contact',
    'crm.company',
];

/**
 * Получаем список полей для стандартных сущностей через соответствующие REST-вызовы.
 * Результаты будем сохранять в массив $fieldsList, где ключ – тип сущности.
 */
$fieldsList = [];
foreach ($crmEntities as $entity) {
    switch ($entity) {
        case 'crm.deal':
            $fields = $B24->core->call('crm.deal.fields', [])->getResponseData()->getResult();
            break;
        case 'crm.lead':
            $fields = $B24->core->call('crm.lead.fields', [])->getResponseData()->getResult();
            break;
        case 'crm.contact':
            $fields = $B24->core->call('crm.contact.fields', [])->getResponseData()->getResult();
            break;
        case 'crm.company':
            $fields = $B24->core->call('crm.company.fields', [])->getResponseData()->getResult();
            break;
        default:
            $fields = [];
            break;
    }
    if (isset($fields['result']) && is_array($fields['result'])) {
        $fieldsList[$entity] = $fields['result'];
    } else {
        $fieldsList[$entity] = [];
    }
}


// Получаем список смарт-процессов
$smartProcessResponse = $B24->core->call('crm.type.list', []);
$smartProcesses = $smartProcessResponse->getResponseData()->getResult()["types"];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройка отслеживания изменений</title>
    <link rel="stylesheet" href="/local/apps/kplab.exchange_log/assets/css/style.css">
    <style>
        /* Дополнительные стили для интерфейса */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1, h2, h3 {
            color: #333;
        }
        #tracking-objects label, .object-fields label, .smart-processes label {
            margin-right: 15px;
            display: inline-block;
        }
        .object-fields, .smart-process-fields {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 10px;
            background: #f9f9f9;
        }
        button {
            margin-top: 20px;
            padding: 8px 16px;
            font-size: 16px;
        }
    </style>
</head>
<body>
<h1>Настройка объектов и полей для отслеживания изменений</h1>

<form method="post" action="save_config.php">
    <!-- Стандартные CRM-сущности -->
    <h2>Выберите стандартные объекты для отслеживания:</h2>
    <div id="tracking-objects">
        <?php foreach ($crmEntities as $entity): ?>
            <label>
                <input type="checkbox" name="objects[]" value="<?= htmlspecialchars($entity) ?>">
                <?php
                // Простой вывод удобочитаемого названия
                switch ($entity) {
                    case 'crm.deal':
                        echo 'Сделки';
                        break;
                    case 'crm.lead':
                        echo 'Лиды';
                        break;
                    case 'crm.contact':
                        echo 'Контакты';
                        break;
                    case 'crm.company':
                        echo 'Компании';
                        break;
                    default:
                        echo $entity;
                }
                ?>
            </label>
        <?php endforeach; ?>
    </div>

    <h2>Настройка полей для стандартных объектов</h2>
    <p>После выбора объектов будут отображены поля, полученные через \CRest::call().</p>
    <div id="object-fields">
        <?php foreach ($fieldsList as $entity => $fields): ?>
            <div class="object-fields" data-object="<?= htmlspecialchars($entity) ?>" style="display: none;">
                <h3>
                    <?php
                    switch ($entity) {
                        case 'crm.deal':
                            echo 'Сделки';
                            break;
                        case 'crm.lead':
                            echo 'Лиды';
                            break;
                        case 'crm.contact':
                            echo 'Контакты';
                            break;
                        case 'crm.company':
                            echo 'Компании';
                            break;
                        default:
                            echo $entity;
                    }
                    ?>
                </h3>
                <?php if (!empty($fields)): ?>
                    <?php foreach ($fields as $fieldCode => $fieldInfo): ?>
                        <label>
                            <input type="checkbox" name="fields[<?= htmlspecialchars($entity) ?>][]" value="<?= htmlspecialchars($fieldCode) ?>">
                            <?php echo htmlspecialchars(isset($fieldInfo['EDIT_FORM_LABEL']['ru']) ? $fieldInfo['EDIT_FORM_LABEL']['ru'] : $fieldCode); ?>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Не удалось получить поля для <?= htmlspecialchars($entity) ?>.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Смарт-процессы -->
    <h2>Смарт-процессы</h2>
    <div id="smart-processes">
        <?php if (!empty($smartProcessList)): ?>
            <?php foreach ($smartProcessList as $smartProcess): ?>
                <label>
                    <input type="checkbox" name="smart_processes[]" value="<?= htmlspecialchars($smartProcess['ID']) ?>">
                    <?= htmlspecialchars($smartProcess['NAME'] ?? $smartProcess['ID']) ?>
                </label>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Смарт-процессы не найдены.</p>
        <?php endif; ?>
    </div>

    <!-- При желании можно добавить блок для настройки полей для смарт-процессов.
         Обычно для смарт-процессов список полей можно получить динамически через
         соответствующий метод API (например, crm.item.fields), если доступен. -->

    <button type="submit">Сохранить настройки</button>
</form>

<script>
    // Обработка отображения блоков настроек для стандартных объектов при выборе чекбоксов
    document.addEventListener('DOMContentLoaded', function() {
        const objectCheckboxes = document.querySelectorAll('#tracking-objects input[type=checkbox]');
        objectCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const objectType = this.value;
                const objectFieldsDiv = document.querySelector('.object-fields[data-object="'+objectType+'"]');
                if (this.checked) {
                    objectFieldsDiv.style.display = 'block';
                } else {
                    objectFieldsDiv.style.display = 'none';
                    // Снимаем выбор со всех полей данного объекта
                    const inputs = objectFieldsDiv.querySelectorAll('input[type=checkbox]');
                    inputs.forEach(function(input) {
                        input.checked = false;
                    });
                }
            });
        });
    });
</script>
</body>
</html>
