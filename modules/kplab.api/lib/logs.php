<?php
namespace KPLab\API\V2;

use Bitrix\Bizproc\Workflow\Template\Packer\Result\Pack;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use KPLab\Logs;

define("LOGS_ACTION", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/logsAction.log");

class LogsAction
{
    public static function Request($objectData, $methodName, $url, $controllerName,
                                   $method, $status, $response, array $time,
                                   $requestBody, $requestHeaders, $taskId = 0,
                                   $requestTypeId = 0, $outRequest, $partnerName = "Битрикс24", $dev = false): void
    {
        // Получаем настройки
        $serverNameProd = Option::get('kplab.api', 'server_name_prod', '');
        $serverNameTest = Option::get('kplab.api', 'server_name_test', '');
        $iblockId = Option::get('kplab.api', 'iblock_id', '');
        $iblockIdTest = Option::get('kplab.api', 'iblock_id_test', '');
        $propertyIDToken = Option::get('kplab.api', 'property_token', '');

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $context = Application::getInstance()->getContext();
        $server = $context->getServer();
        $serverArray = $server->toArray();
        $serverName = $serverArray['SERVER_NAME'];
        $authorization = $server->get('REMOTE_USER');
        $authText = stristr($authorization, 'BitrixAuth');

        Logs\File::AddMessage($partnerName,"partnerName1", LOGS_ACTION);

        if (!$outRequest) {
            $requestType = "Входящий";
            if(empty($partnerName)) {
                if ($dev) {
                    $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.IjYwMzY5NCI.dq6N9xjzSIfAVYDrHs5MwmcghyO52cSru-BQzldMOM4";
                }
                else {
                    if ($authText === false) {
                        $token = $authorization;
                    } else {
                        $token = str_replace('BitrixAuth ', '', $authorization);
                    }
                }

                if ($token) {
                    Loader::includeModule('iblock');

                    if ($serverName == $serverNameProd) {
                        $arFilter = ["IBLOCK_ID" => $iblockId, "PROPERTY_{$propertyIDToken}" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
                    } elseif ($serverName == $serverNameTest) {
                        $arFilter = ["IBLOCK_ID" => $iblockIdTest, "PROPERTY_{$propertyIDToken}" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
                    }

                    $res = \CIBlockElement::GetList(['ID' => 'ASC'], $arFilter, false, [], ["*", "PROPERTY_*"])->Fetch();
                    $partnerName = $res ? $res['NAME'] : "Unknown Partner"; // Название партнёра
                } else {
                    $apikey = json_decode($requestBody,true)['apiKey'];
                    if ($apikey)
                    {
                        $partnerName = "SE";
                    }
                }
            }   
        } else {
            $requestType = "Исходящий";
            $partnerName = "Битрикс24";
        }

        Logs\File::AddMessage($partnerName,"partnerName2", LOGS_ACTION);

        $title = $objectData['ITEM_TITLE'];
        $objectUrl = $objectData['INIT_OBJECT_URL'];

        // Экранирование значений для SQL
        $title = $sqlHelper->forSql($objectData['ITEM_TITLE']);
        $objectUrl = $sqlHelper->forSql($objectData['INIT_OBJECT_URL']);
        //$partnerName = $sqlHelper->forSql($objectData['partner_name']);
        $methodName = $sqlHelper->forSql($methodName);
        $url = $sqlHelper->forSql($url);
        $controllerName = $sqlHelper->forSql($controllerName);
        $method = $sqlHelper->forSql($method);
        $status = $sqlHelper->forSql($status);
        $response = $sqlHelper->forSql($response['response']); // JSON уже экранирован, оставляем как есть
        $executionTime = floatval(\KPLab\API\V2\Time::finish($time)['duration']);
        $requestBody = $sqlHelper->forSql($requestBody);
        $requestType = $sqlHelper->forSql($requestType);
        $requestHeaders = $sqlHelper->forSql($requestHeaders);
        $taskId = $sqlHelper->forSql($taskId);
        $requestTypeId = intval($requestTypeId);

        $sql = "INSERT INTO kplab_api_logs 
                (title, object_url, partner_name, method_name, request_url, controller_name, request_method, request_status, response, execution_time, request_body, request_type, request_headers, task_id, request_type_id)
                VALUES ('$title', '$objectUrl', '$partnerName', '$methodName', '$url', '$controllerName', '$method', '$status', '$response', $executionTime, '$requestBody', '$requestType', '$requestHeaders', '$taskId', $requestTypeId)
            ";

        $connection->queryExecute($sql);
    }

    // Вспомогательная функция для экранирования и декодирования JSON-строк
    private static function sanitizeAndDecodeJson($data)
    {
        // Проверка: если это строка, применяем экранирование кавычек внутри значений и декодирование
        if (is_string($data)) {
            $data = preg_replace_callback('/"([^"]+)"\s*:\s*"(.*?)"/', function ($matches) {
                $field = $matches[1];
                $value = str_replace('"', '\"', $matches[2]); // Экранируем кавычки в значении
                return "\"{$field}\":\"{$value}\"";
            }, $data);

            // Пробуем декодировать JSON
            $decoded = json_decode($data, true);

            // Если декодирование успешно, возвращаем массив; иначе - оригинальную строку
            return $decoded !== null ? $decoded : $data;
        }

        // Если входные данные - это массив, применяем обработку к каждому элементу
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeAndDecodeJson($value); // Рекурсивный вызов для каждого элемента
            }
        }

        return $data;
    }

    private static function escapeInnerQuotes($jsonString)
    {
        // Ищем ключ-значение и экранируем кавычки только внутри значений
        return preg_replace_callback('/"([^"]+)"\s*:\s*"(.*?)"/', function ($matches) {
            $field = $matches[1];
            $value = preg_replace('/(?<!\\\\)"/', '\"', $matches[2]); // Экранируем только внутренние кавычки в значениях
            return "\"{$field}\":\"{$value}\"";
        }, $jsonString);
    }

}