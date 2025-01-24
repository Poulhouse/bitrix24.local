<?php
namespace KPLab;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Uri;
use KPLab\Logs;
use KPLab\API\V2\LogsAction;

class ApiRequest
{
    /**
     * Unified HTTP request function for POST, GET, and other methods.
     *
     * @param string $url The request URL.
     * @param string $method The HTTP method ('POST' or 'GET').
     * @param array $headers Array of headers (e.g., ['Authorization' => 'Bearer token']).
     * @param mixed $data Data for POST requests, can be JSON string or array.
     * @param array $logData Additional data for logging purposes.
     * @param bool $emulation Set to true for testing without actual HTTP request.
     * @return array Response containing 'success' and 'error'.
     */
    public static function sendRequest($url, $method = 'POST', $headers = [], $data = null, $logData = [], $emulation = false): array|string
    {
        \Bitrix\Main\Loader::includeModule('kplab.api.v2');

        $http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
        $http->setHeaders($headers);
        $jsonRes = ['status' => 'Success', 'response' => null];

        /*if(!empty($data)) {
            $requestDecoded = json_decode($data, true);
            $requestToSave = $requestDecoded !== null ? json_encode($requestDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data;
        }*/


        if ($emulation) {
            $jsonRes['response'] = "Emulated request successful!";
            return self::logRequest($jsonRes, $url, $headers, $data, $logData, 'Success');
        }

        $response = ($method === 'POST') ? $http->post($url, $data) : $http->get($url);
        $status = $http->getStatus();

        $result = $http->getResult();

        $resultDecoded = json_decode($result, true);
        $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $result;

        if ($status === 200) {
            $jsonRes['response'] = $resultToSave;
        } else {
            $jsonRes['status'] = "Failed";
            $jsonRes['response'] = $resultToSave;
        }

        return self::logRequest($jsonRes, $url, $headers, $data, $logData, $status === 200 ? 'Success' : 'Failed');
    }

    /**
     * Logs request and response details.
     *
     * @param array $response Array containing response details.
     * @param string $url Request URL.
     * @param array $headers Headers sent with the request.
     * @param mixed $data Request body data.
     * @param array $logData Data related to the request for logging purposes.
     * @param string $status Status of the request (e.g., 'Success' or 'Failed').
     * @return array Returns the response with 'success' or 'error' details.
     */
    private static function logRequest($response, $url, $headers, $data, $logData, $status)
    {

        $responseDecoded = json_decode($response['response'], true);
        $responseToSave = $responseDecoded !== null ? $responseDecoded : $response['response'];


        LogsAction::Request(
            $logData['objectData'],
            $logData['methodName'],
            $url,
            $logData['controllerName'],
            $logData['method'],
            $status,
            $response,
            $logData['timeData'],
            $data,
            json_encode($headers),
            $logData['taskId'] ?? null,
            $logData['requestTypeId'] ?? 0,
            true
        );

        //Logs\IBlock::setData($url, json_encode($data), $response, $logData['objectData'], $logData['timeData'], $logData['point'], $headers);

        //$responseDecoded = json_decode($response, true);
        return $response;
    }
}
