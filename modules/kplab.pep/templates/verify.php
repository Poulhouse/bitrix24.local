<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Kplab\Pep\Logger;

?>

<h2>Проверка подписанного документа</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="pdf_file" accept=".pdf" required>
    <button type="submit">Проверить</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $tmpPath = $_FILES['pdf_file']['tmp_name'];

    if (!file_exists($tmpPath)) {
        echo "<p style='color:red'>Файл не загружен.</p>";
        return;
    }

    $docHash = hash_file('sha256', $tmpPath);
    echo "<p><strong>SHA-256:</strong> {$docHash}</p>";

    $conn = Application::getConnection();
    $row = $conn->query("
        SELECT * FROM kplab_pep_sgn_log WHERE DOCUMENT_HASH = '{$docHash}' AND SIGNATURE IS NOT NULL LIMIT 1
    ")->fetch();

    if (!$row) {
        echo "<p style='color:red'><strong>Подпись не найдена</strong></p>";
        Logger::log($docHash, 'verify_fail', 'Файл не найден в логах');
        return;
    }

    $client = [
        'full_name' => $row['FULL_NAME'],
        'phone'     => $row['PHONE'],
        'email'     => $row['EMAIL'],
        'crm_id'    => $row['COMPANY_ID'],
        'signed_at' => $row['SIGNED_AT'],
    ];

    $secret = 'your_secret_key_here'; // TODO: вынести в опции
    $expectedSignature = hash_hmac('sha256', implode('|', [
        $docHash,
        $client['full_name'],
        $client['phone'],
        $client['email'],
        $client['crm_id'],
        $client['signed_at']
    ]), $secret);

    if ($expectedSignature !== $row['SIGNATURE']) {
        echo "<p style='color:red'><strong>Подпись недействительна (HMAC не совпадает)</strong></p>";
        Logger::log($docHash, 'verify_fail', 'HMAC mismatch');
        return;
    }

    echo "<p style='color:green'><strong>Подпись подтверждена</strong></p>";
    echo "<ul>
        <li><strong>ФИО:</strong> {$client['full_name']}</li>
        <li><strong>Email:</strong> {$client['email']}</li>
        <li><strong>Телефон:</strong> {$client['phone']}</li>
        <li><strong>ID компании:</strong> {$client['crm_id']}</li>
        <li><strong>Дата подписи:</strong> {$client['signed_at']}</li>
    </ul>";

    if (Loader::includeModule('crm')) {
        $company = \CCrmCompany::GetByID($client['crm_id']);
        if (!empty($company['UF_SIGNED_DOC'])) {
            $path = CFile::GetPath($company['UF_SIGNED_DOC']);
            echo "<p><a href='{$path}' target='_blank'>Скачать подписанный документ</a></p>";
        }
    }

    Logger::log($docHash, 'verify_success', 'Документ успешно подтверждён');
}
?>
