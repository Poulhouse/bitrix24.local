<?php
ini_set('display_errors', 'On');
require_once '../vendor/autoload.php';

use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;




// Профиль приложения
$appProfile = ApplicationProfile::initFromArray([
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID' => 'local.67c861b2551452.09112784',
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => '3wZxvFL5faHPSVSVh7MYz8tXsjMPp92514Z4jyZ2JY3si5NEU5',
    'BITRIX24_PHP_SDK_APPLICATION_SCOPE' => 'crm,user_basic,placement'
]);

$B24 = ServiceBuilderFactory::createServiceBuilderFromPlacementRequest(
    Request::createFromGlobals(),
    $appProfile
);
function saveUploadedFile($file): string
{
    // Проверяем, был ли файл успешно загружен
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Ошибка при загрузке файла: " . $file['error']);
    }

    // Генерируем уникальное имя для файла
    $uploadDir = '../tmp/'; // Укажите путь к директории для сохранения
    $fileName = uniqid('uploaded_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filePath = $uploadDir . $fileName;

    // Перемещаем файл из временной директории в постоянную
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception("Не удалось сохранить файл.");
    }

    return $filePath; // Возвращаем путь к сохраненному файлу
}


if (isset($_POST['mappings']) && isset($_POST['entityTypeId']) && isset($_POST['filePath'])) {

    function readExcelFile($filePath): array
    {
        // Загружаем файл
        $spreadsheet = IOFactory::load($filePath);

        // Получаем активный лист
        $sheet = $spreadsheet->getActiveSheet();

        // Читаем данные в массив
        $data = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Включаем пустые ячейки

            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue(); // Получаем значение ячейки
            }

            $data[] = $rowData; // Добавляем строку в массив
        }

        return $data;
    }
    function parseExcelData($data): array
    {
        $headers = array_shift($data); // Убираем первую строку (заголовки)
        $rows = [];

        foreach ($data as $row) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? null; // Сохраняем данные в ассоциативный массив
            }
            $rows[] = $rowData;
        }

        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }
    function createEntities($rows, $mappings): array
    {
        $entities = [];

        foreach ($rows as $row) {
            $entity = [];
            foreach ($mappings as $fieldName => $columnName) {
                if (isset($row[$columnName])) {
                    $entity[$fieldName] = $row[$columnName]; // Присваиваем значение из строки
                } else {
                    $entity[$fieldName] = null; // Если поле не сопоставлено
                }
            }
            $entities[] = $entity;
        }

        return $entities;
    }


    // Шаг 1: Чтение файла
    $filePath = $_POST['filePath'];
    $data = readExcelFile($filePath);

    // Шаг 2: Разбор данных
    $parsedData = parseExcelData($data);
    $headers = $parsedData['headers'];
    $rows = $parsedData['rows'];

    // Шаг 3: Сопоставление полей (предположим, что mappings пришли от клиента)
    $mappings  = [];
    foreach ($_REQUEST as $fieldName => $value) {
        if ($fieldName == 'APP_SID'
            || $fieldName == 'AUTH_EXPIRES'
            || $fieldName == 'AUTH_ID'
            || $fieldName == 'DOMAIN'
            || $fieldName == 'LANG'
            || $fieldName == 'PLACEMENT'
            || $fieldName == 'PROTOCOL'
            || $fieldName == 'REFRESH_ID'
            || $fieldName == 'entityTypeId'
            || $fieldName == 'filePath'
            || $fieldName == 'mappings') {
            continue;
        }
        if(is_null($value) || $value === "" ) {
            continue;
        }
        $mappings[$fieldName] = $value;
    }

    // Шаг 4: Создание сущностей
    $entities = createEntities($rows, $mappings);

    foreach ($entities as $entity) {
        try {
            $entityTypeId = $_POST['entityTypeId']; // Example entity type ID

            $result = $B24
                ->getCRMScope()
                ->item()
                ->add($entityTypeId, $entity);
        } catch (Throwable $e) {
            json_encode(["error"  => $e->getMessage()]);
        }
    }

    echo json_encode(['success' => true, 'entities' => $entities]);
    unlink($filePath);
    exit;
}

if (isset($_POST['entityTypeId']) && !isset($_POST['mappings'])) {
    // Получаем данные из формы
    $smartProcess = $_POST['entityTypeId'];
    $uploadedFile = $_FILES['file'];

    try {
        // Сохраняем файл на сервере
        $filePath = saveUploadedFile($uploadedFile);

        $spreadsheet = IOFactory::load($filePath);
        $data = $spreadsheet->getActiveSheet()->toArray();

        // Получение заголовков столбцов
        $columns = array_shift($data);

        // Получение списка полей для выбранного смарт-процесса
        $fields = $B24->core->call('crm.item.fields', ['entityTypeId' => $smartProcess])->getResponseData()->getResult();

        echo json_encode(['success' => true, 'data' => ['entityTypeId' => $smartProcess, 'columns' => $columns, 'fields' => $fields['fields'], 'filePath' => $filePath]]);
    }
    catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка чтения файла: ' . $e->getMessage()]);
    }
    exit;
}
elseif(!isset($_POST['mappings'])) {
    //print_r($_REQUEST);
    $DOMAIN = $_REQUEST['DOMAIN'] ?? '';
    $AUTH_ID = $_REQUEST['AUTH_ID'] ?? '';
    $APP_SID = $_REQUEST['APP_SID'] ?? '';
    $LANG = $_REQUEST['LANG'] ?? '';
    $PROTOCOL = $_REQUEST['PROTOCOL'] ?? '';
    $AUTH_EXPIRES = $_REQUEST['AUTH_EXPIRES'] ?? '';
    $REFRESH_ID = $_REQUEST['REFRESH_ID'] ?? '';
    $placement = $_REQUEST['PLACEMENT'] ?? '';
    if(isset($placement)) {
        preg_match('/CRM_DYNAMIC_(\d+)_LIST_TOOLBAR/', $placement, $matches);
        $entityTypeId = $matches[1];
        if($entityTypeId != 0) {?>
            <div class="import-container">
                <form id="importForm">
                    <input type="hidden" name="DOMAIN" value="<?= htmlspecialchars($DOMAIN) ?>">
                    <input type="hidden" name="LANG" value="<?= htmlspecialchars($LANG) ?>">
                    <input type="hidden" name="PROTOCOL" value="<?= htmlspecialchars($PROTOCOL) ?>">
                    <input type="hidden" name="APP_SID" value="<?= htmlspecialchars($APP_SID) ?>">
                    <input type="hidden" name="AUTH_ID" value="<?= htmlspecialchars($AUTH_ID) ?>">
                    <input type="hidden" name="AUTH_EXPIRES" value="<?= htmlspecialchars($AUTH_EXPIRES) ?>">
                    <input type="hidden" name="REFRESH_ID" value="<?= htmlspecialchars($REFRESH_ID) ?>">
                    <input type="hidden" name="PLACEMENT" value="<?= htmlspecialchars($placement) ?>">
                    <input type="hidden" id="entityTypeId" name="entityTypeId" value="<?=$entityTypeId?>">
                    <div class="form-group">
                        <label for="file">Загрузите CSV файл для импорта:</label>
                        <input type="file" id="file" name="file" accept=".csv,.xlsx">
                    </div>
                    <button type="submit">Далее</button>
                </form>
            </div>
    <?php
        }
    }
}
?>
<?php
$DOMAIN = $_REQUEST['DOMAIN'] ?? '';
$AUTH_ID = $_REQUEST['AUTH_ID'] ?? '';
$APP_SID = $_REQUEST['APP_SID'] ?? '';
$LANG = $_REQUEST['LANG'] ?? '';
$PROTOCOL = $_REQUEST['PROTOCOL'] ?? '';
$AUTH_EXPIRES = $_REQUEST['AUTH_EXPIRES'] ?? '';
$REFRESH_ID = $_REQUEST['REFRESH_ID'] ?? '';
$placement = $_REQUEST['PLACEMENT'] ?? '';
?>
<script src="/local/apps/kplab.smart_import/assets/script.js?v=<?=time()?>"></script>
<style>
    #mappingFormFields {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    .form-group {
        display: flex;
        flex-direction: row;
        gap: 16px;
    }

    #loader {
        text-align: center;
        font-size: 18px;
        color: #333;
    }

    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        margin: 20px auto;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
<!-- HTML для второй страницы -->
<div class="mapping-container" style="display:none;">
    <h2>Сопоставление полей</h2>
    <form id="mappingForm">
        <input type="hidden" name="DOMAIN" value="<?= htmlspecialchars($DOMAIN) ?>">
        <input type="hidden" name="LANG" value="<?= htmlspecialchars($LANG) ?>">
        <input type="hidden" name="PROTOCOL" value="<?= htmlspecialchars($PROTOCOL) ?>">
        <input type="hidden" name="APP_SID" value="<?= htmlspecialchars($APP_SID) ?>">
        <input type="hidden" name="AUTH_ID" value="<?= htmlspecialchars($AUTH_ID) ?>">
        <input type="hidden" name="AUTH_EXPIRES" value="<?= htmlspecialchars($AUTH_EXPIRES) ?>">
        <input type="hidden" name="REFRESH_ID" value="<?= htmlspecialchars($REFRESH_ID) ?>">
        <input type="hidden" name="PLACEMENT" value="<?= htmlspecialchars($placement) ?>">
        <input type="hidden" name="mappings" value="Y">
        <div id="mappingFormFields"></div>
        <!-- Здесь будут динамически добавлены поля для сопоставления -->
        <button type="submit" id="startImportButton">Начать импорт</button>
    </form>
</div>

<div id="loader" style="display: none;">
    <p>Идет импорт данных...</p>
    <div class="spinner"></div>
</div>

<div id="success-container" style="display: none;">
    <p>Импорт успешно завершен!</p>
</div>


<script>
    document.addEventListener("DOMContentLoaded", function () {
        sessionStorage.setItem("bitrix_domain", <?=$_REQUEST['DOMAIN']?>);
    });
</script>


