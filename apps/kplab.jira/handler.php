<?php

define("LOG_JIRA", $_SERVER['DOCUMENT_ROOT']."/local/apps/kplab.jira/logs/jira.log");
//2233c18ffe0e11129874ede07503b584
file_put_contents(LOG_JIRA, ["REQUEST" => print_r($_REQUEST,true)], FILE_APPEND);
//header("Content-type: application/json");
/*// Данные, полученные от Jira
$authCode = $_REQUEST['code'];
$clientId = '2233c18ffe0e11129874ede07503b584'; // Ваш Client ID
$clientSecret = '568b469a21e735b1c6f8b210b480754cdd6e22e524cbd35b3ffe97767f0b659c'; // Ваш Client Secret
$redirectUri = 'https://jira.seller-capital.ru/plugins/servlet/oauth2/client/callback/XCdokb3CcqduAnr9KcBT_A'; // Redirect URI

// Token URL для получения токена
$tokenUrl = 'https://oauth.bitrix.info/oauth/token/';

// Параметры для запроса
$params = [
    'grant_type' => 'authorization_code',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'code' => $authCode,
];
$tokenUrl .= '?' . http_build_query($params);
// Инициализация CURL
$ch = curl_init($tokenUrl);

// Настройка CURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Отключить проверку SSL (только для тестов)

// Выполнение запроса
$response = curl_exec($ch);

// Проверка на ошибки
if (curl_errno($ch)) {
    echo 'Ошибка CURL: ' . curl_error($ch);
} else {
    // Декодирование ответа
    $responseData = json_decode($response, true);

    // Проверка наличия токена
    if (isset($responseData['access_token'])) {
        echo 'Токен доступа: ' . $responseData['access_token'] . '<br>';
        echo 'Refresh Token: ' . $responseData['refresh_token'] . '<br>';
        echo 'Срок действия: ' . $responseData['expires_in'] . ' секунд<br>';
    } else {
        echo 'Ошибка при получении токена: ' . print_r($responseData, true);
    }
}

// Закрытие CURL
curl_close($ch);*/