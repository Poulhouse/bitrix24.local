<?php
require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";

use Bitrix\Bizproc\WorkflowInstanceTable;
use Bitrix\Bizproc\WorkflowTemplateTable;
use Bitrix\Main\Loader;

Loader::includeModule("bizproc");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $jsonData = json_decode($_POST["json"], true);
    $documentId = ["crm", "CCrmDocumentDeal", "DEAL_123"]; // Укажи свою сущность

    foreach ($jsonData as $key => $value) {
        if (preg_match('/\{\{(.+?)\}\}/', $value, $matches)) {
            $realValue = CBPDocument::GetFieldValue($documentId, trim($matches[1]));
            $jsonData[$key] = $realValue ?: $value; // Подставляем значение или оставляем как есть
        }
    }

    echo json_encode(["success" => true, "data" => $jsonData]);
}
?>
