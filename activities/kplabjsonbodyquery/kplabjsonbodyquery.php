<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

define("LOG_ACTIVITY_KPLABJSON", $_SERVER['DOCUMENT_ROOT']."/local/logs/KPLABJSON_PROXY.log");
class CBPKPLabJSONBodyQuery extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            "Title" => "",
            "Url" => "",
            "Method" => "POST",
            "ContentType" => "application/json",
            "AddTo" => "",
            "AuthKey" => "",
            "AuthValue" => "",
            "RequestBody" => "",
            "Log" => "N",
            "ResponseData" => null, // Для хранения ответа API
            "ResponseStatus" => null, // Для хранения HTTP-кода ответа
        ];
    }

    public function Execute()
    {
        if (empty($this->Url)) {
            $this->WriteToTrackingService("Ошибка: не указан URL запроса", 0, CBPTrackingType::Error);
            return CBPActivityExecutionStatus::Closed;
        }
        $options = [
            "version" => HttpClient::HTTP_1_1,
            "disableSslVerification" => false,
            'useCurl' => true,
	        'curlLogFile' => '/home/bitrix/www/local/logs/curlLogFile.log',
        ];

        $http = new HttpClient($options);
        $http->setHeader("Content-Type", $this->ContentType);

        // Добавляем авторизацию, если указана
        if (!empty($this->AddTo)) {
            if ($this->AddTo === "Header" && !empty($this->AuthKey) && !empty($this->AuthValue)) {
                $http->setHeader($this->AuthKey, $this->AuthValue);
            } elseif ($this->AddTo === "Query" && !empty($this->AuthKey) && !empty($this->AuthValue)) {
                $this->Url .= (!str_contains($this->Url, '?') ? '?' : '&') . "$this->AuthKey=" . urlencode($this->AuthValue);
            }
        }

        \KPLab\Logs\File::AddMessage($http->getHeaders(),"getHeaders", LOG_ACTIVITY_KPLABJSON);

        $requestBody = $this->RequestBody;
        // Декодируем в массив
        $data = json_decode($requestBody, true);

        // Кодируем обратно в минифицированный JSON
        $requestBody = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        \KPLab\Logs\File::AddMessage($this->Method,"method", LOG_ACTIVITY_KPLABJSON);
        \KPLab\Logs\File::AddMessage($this->Url,"url", LOG_ACTIVITY_KPLABJSON);
        \KPLab\Logs\File::AddMessage($requestBody,"body", LOG_ACTIVITY_KPLABJSON);

        // Отправка запроса
        $response = null;
        switch ($this->Method) {
            case "POST":
                $response = $http->post($this->Url, $requestBody);
                break;
            case "PUT":
                $response = $http->query("PUT", $this->Url, $requestBody);
                break;
            case "DELETE":
                $response = $http->query("DELETE", $this->Url);
                break;
        }
        $response = $http->getResult();
        // Декодируем в массив
        $responseData = json_decode($response, true);

        // Кодируем обратно в минифицированный JSON
        $responseData = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        \KPLab\Logs\File::AddMessage($responseData,"response", LOG_ACTIVITY_KPLABJSON);

        // Получаем HTTP-статус
        $status = $http->getStatus();

        // Записываем статус ответа и данные
        $this->ResponseStatus = $status;
        $this->ResponseData = $responseData;

        // Логирование
        if ($this->Log === "Y") {
            $this->WriteToTrackingService("Запрос: " . $requestBody);
            $this->WriteToTrackingService("Ответ ($this->ResponseStatus): " . print_r($this->ResponseData,true));
        }

        return CBPActivityExecutionStatus::Closed;
    }

    public static function ValidateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];
        if (empty($arTestProperties["Url"])) {
            $errors[] = ["code" => "NotExist", "parameter" => "Url", "message" => "Не указан URL запроса"];
        }
        return array_merge($errors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
    {
        $runtime = CBPRuntime::getRuntime();

        if (!is_array($arCurrentValues)) {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (!empty($arCurrentActivity["Properties"]['Url'])) {
                $arCurrentValues['url'] = $arCurrentActivity["Properties"]['Url'];
            }
            if (!empty($arCurrentActivity["Properties"]['Method']))
            {
                $arCurrentValues['method'] = $arCurrentActivity["Properties"]['Method'];
            }
            if (!empty($arCurrentActivity["Properties"]['ContentType']))
            {
                $arCurrentValues['content_type'] = $arCurrentActivity["Properties"]['ContentType'];
            }
            if (!empty($arCurrentActivity["Properties"]['AddTo']))
            {
                $arCurrentValues['add_to'] = $arCurrentActivity["Properties"]['AddTo'];
            }
            if (!empty($arCurrentActivity["Properties"]['AuthKey']))
            {
                $arCurrentValues['auth_key'] = $arCurrentActivity["Properties"]['AuthKey'];
            }
            if (!empty($arCurrentActivity["Properties"]['AuthValue']))
            {
                $arCurrentValues['auth_value'] = $arCurrentActivity["Properties"]['AuthValue'];
            }
            if (!empty($arCurrentActivity["Properties"]['RequestBody']))
            {
                $arCurrentValues['request_body'] = $arCurrentActivity["Properties"]['RequestBody'];
            }
            if (!empty($arCurrentActivity["Properties"]['Log']))
            {
                $arCurrentValues['log'] = $arCurrentActivity["Properties"]['Log'];
            }
        }

        $arResult = array(
            "arCurrentValues"  => $arCurrentValues,
            "formName"       => $formName,
            "documentType"   => $documentType,
            "activityName"   => $activityName,
        );
        return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php", ['arResult' => $arResult]);
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $arProperties = [
            "Url" => trim($arCurrentValues["url"]),
            "Method" => $arCurrentValues["method"],
            "ContentType" => $arCurrentValues["content_type"],
            "AddTo" => $arCurrentValues["add_to"],
            "AuthKey" => $arCurrentValues["auth_key"],
            "AuthValue" => $arCurrentValues["auth_value"],
            "RequestBody" => $arCurrentValues["request_body"],
            "Log" => $arCurrentValues["log"] === "Y" ? "Y" : "N",
        ];

        $arErrors = self::ValidateProperties($arProperties);
        if (count($arErrors) > 0) {
            return false;
        }
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = $arProperties;

        return true;
    }
}
?>
