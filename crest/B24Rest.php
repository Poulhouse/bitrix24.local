<?php
class B24Rest
{

    public static function call($callMethod, $callBody): ?array
    {
        $http = new \Bitrix\Main\Web\HttpClient();
        $http->setHeader('Content-Type', 'application/json');
        $http->post('https://testcrm.seller-capital.ru/rest/1/3lsxt0qfwbns0wve/' . $callMethod, json_encode($callBody));

        $responseJson = $http->getResult();
        return json_decode($responseJson, true);
    }
}
