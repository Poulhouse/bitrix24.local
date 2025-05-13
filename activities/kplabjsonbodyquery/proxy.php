<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die; ?>
<?php

use Bitrix\Main\Web\HttpClient;

define("LOG_ACTIVITY_KPLABJSON", $_SERVER['DOCUMENT_ROOT']."/local/logs/KPLABJSON_PROXY.log");

header("Content-Type: application/json");

// Получаем данные из AJAX
$data = json_decode(file_get_contents("php://input"), true);

\KPLab\Logs\File::AddMessage($data,"data", LOG_ACTIVITY_KPLABJSON);

if (!$data || !isset($data["url"]) || !isset($data["method"])) {
    echo json_encode(["error" => "Некорректный запрос"]);
    exit;
}

$url = $data["url"];
$method = strtoupper($data["method"]);
$headers = $data["headers"] ?? [];
$body = $data["body"] ?? null;

// Создаем HTTP-клиент
$options = [
    "version" => HttpClient::HTTP_1_1,
    "disableSslVerification" => false,
    "waitResponse" => false,
];

$http = new HttpClient($options);

// Добавляем заголовки, если они есть
foreach ($headers as $key => $value) {
    $http->setHeader($key, $value);
}

// 🔥 Если метод GET и есть body, передаем JSON как query-параметр
if ($method === "GET" && $body) {
    $arBody = json_decode($body, true);
    foreach ($arBody as $key => $value) {
        $url .= (!str_contains($url, "?") ? "?" : "&") . urlencode($key) ."=" . urlencode($value);
    }
}

// Отправляем запрос
$response = null;
if ($method === "GET") {
    $response = $http->get($url);
} elseif ($method === "POST") {
    $response = $http->post($url, $body);
} elseif ($method === "PUT") {
    $response = $http->query("PUT", $url, $body);
} elseif ($method === "DELETE") {
    $response = $http->query("DELETE", $url);
}

\KPLab\Logs\File::AddMessage($method,"method", LOG_ACTIVITY_KPLABJSON);
\KPLab\Logs\File::AddMessage($url,"url", LOG_ACTIVITY_KPLABJSON);
\KPLab\Logs\File::AddMessage($body,"body", LOG_ACTIVITY_KPLABJSON);

// Получаем HTTP-статус
$status = $http->getStatus();

// Возвращаем JSON-ответ
echo json_encode([
    "status" => $status,
    "response" => json_decode($response, true) ?? $response
]);
?>
