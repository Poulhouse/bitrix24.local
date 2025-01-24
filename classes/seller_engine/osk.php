<?php namespace KPLab;

define("LOG_OSK", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/seller_engine_osk.log");
define('API_KEY','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU');
define("TOKEN_LK", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");

use Bitrix\Main\Web\HttpClient;
use \KPLab\Logs;

class SellerCapitalOSK
{
    public $apiTestUrl;
    public $apiUrl;
    public $internalTestUrl;
    public $internalUrl;
    public $point;

    public $itemDatatitle;
    public $companyId;
    public $entityTypeId;
    public $CURLObjectData;
    public $multipartFormData;
    public $post_data;
    public $sellerInn;
    public $resSellerGetByInnStatus;
    public $sellerId;
    public $smartOSKId;
    public $sellerPhone;
    public $sellerFirstName;
    public $sellerLastName;
    public $sellerPatronymic;
    public $isPartner;
    public $isSeller;
    public $accountStatus;
    public $get_SellerGetByInn_testUrl;
    public $post_SellerCreateMinimal_testUrl;
    public $post_lkUserCreate_testUrl;
    public $arrayPingResponse;
    public $domain;

    public function __construct()
    {
        // Определяем текущий домен
        $this->domain = $_SERVER['HTTP_HOST'];

        if (strpos($this->domain, 'test') !== false) {
            $this -> apiUrl = "https://api.dev.seller-capital.ru/";
            $this -> internalUrl = "https://internal.dev.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.dev.seller-capital.ru/lk/user/Authenticate";
        } else {
            $this -> apiUrl = "https://api.seller-capital.ru/";
            $this -> internalUrl = "https://internal.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.seller-capital.ru/lk/user/Authenticate";
        }

        $this -> point = "BX_SE";
        $this -> errorMessage = null;
        $this->isSeller = false;
        $this->isPartner = false;
    }
    protected function getSellerInn($ENTITY_ID) {
        global $DB;
        $RQItemSQL = "SELECT * FROM b_crm_requisite WHERE ENTITY_ID='{$ENTITY_ID}' ORDER BY ID ASC;";

        $resRQItemsQuery = $DB->query($RQItemSQL);
        while($resRQItem = $resRQItemsQuery->Fetch()) {
            $this->sellerInn = $resRQItem['RQ_INN'];
        }
    }
    protected function getSellerRQ($ENTITY_ID) {
        global $DB;
        $RQItemSQL = "SELECT * FROM b_crm_requisite WHERE ENTITY_ID='{$ENTITY_ID}' ORDER BY ID ASC;";

        $resRQItemsQuery = $DB->query($RQItemSQL);
        while($resRQItem = $resRQItemsQuery->Fetch()) {
            $this->sellerInn = $resRQItem['RQ_INN'];
            $this->sellerLastName = $resRQItem['RQ_LAST_NAME'];
            $this->sellerFirstName = $resRQItem['RQ_FIRST_NAME'];
            $this->sellerSecondName = $resRQItem['RQ_SECOND_NAME'];
        }
    }
    protected function getInfoSmart($smartId) {
        global $DB;

        $entityTypeId = 134;
        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
        $item = $factory -> getItem($smartId);
        if($item) {
            $accountStatusId = $item->getData()['UF_CRM_LKSC_STATUS'];

            $itemSQL = "SELECT * FROM b_user_field_enum  WHERE ID='{$accountStatusId}' ORDER BY ID ASC;";
            $resItemsQuery = $DB->query($itemSQL);
            while($resItem = $resItemsQuery->Fetch()) {
                $this->accountStatus = (string) $resItem['XML_ID'];
            }

            $this->sellerId = $item->getData()['UF_CRM_LKSC_SELLERID'];
            $this->itemDatatitle = $item->getData()['TITLE'];

            $roleClientArrayIds = $item->getData()['UF_CRM_ROLE_OF_THE_CLIENT'];
            foreach($roleClientArrayIds as $roleClientId) {
                $itemSQL = "SELECT * FROM b_user_field_enum  WHERE ID='{$roleClientId}' ORDER BY ID ASC;";
                $resItemsQuery = $DB->query($itemSQL);
                while($resItem = $resItemsQuery->Fetch()) {
                    $this->roleClient[] = (string) $resItem['XML_ID'];
                }
            }
            foreach($this->roleClient as $role) {
                if($role == "IsSeller") $this->isSeller = true;
                if($role == "IsPartner") $this->isPartner = true;
            }
            $this->passwordRecoveryToken = $item->getData()['UF_CRM_29_1720813039867'];
            $this->passwordForMigration = $item->getData()['UF_CRM_PASSWORD_FOR_MIGRATION'];
        }
    }
    protected function UpdateInfoSmartLK($smartLKId, $params) {
        $entityTypeId = 128;
        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
        $item = $factory -> getItem($smartLKId);
        $item->set("UF_CRM_LKSC_SELLERID", $params["sellerId"]);
        $operation = $factory->getUpdateOperation($item);
        $operationResult = $operation->launch();
    }
    protected function setCURLObjectData($itemId) {
        $this->CURLObjectData['ITEM_ID'] = $itemId;
        $this->CURLObjectData['ITEM_TYPE_ID'] = $this->entityTypeId;
        $this->CURLObjectData['ITEM_TITLE'] = "ЛК: ". $this->itemDatatitle;
        $this->CURLObjectData['INIT_OBJECT_URL'] = "https://{$this->domain}/crm/type/{$this->entityTypeId}/details/{$itemId}/";
        return $this->CURLObjectData;
    }
    protected function requestPOST($url, $key, $internal, $emulation = false, $ping = false, $headerResponse = true) {
        $timeData = Logs\TimeData::start();

        $logData = [
            'objectData' => $this->CURLObjectData,
            'methodName' => __FUNCTION__,
            'controllerName' => get_class($this),
            'method' => 'POST',
            'timeData' => $timeData,
            'point' => $point
        ];

        if(!$internal) {
            if(!$key) {
                if (defined("TOKEN_LK")) {
                    $curlHeaders = array(
                        "Authorization" => "Bearer ".TOKEN_LK,
                        "Content-Type" => "application/json-patch+json"
                    );

                    $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $curlHeaders, $this->jsonData, $logData);
                }
            } else {
                if (defined("API_KEY")) {
                    $curlHeaders = array(
                        "key" => API_KEY,
                        "Content-Type" => "application/json-patch+json"
                    );
                    $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $curlHeaders, $this->jsonData, $logData);
                }
            }
        } else {
            if(!$key)
            {
                if (defined("TOKEN_LK"))
                {
                    $curlHeaders = array(
                        "Authorization" => "Bearer ".TOKEN_LK,
                        "Content-Type" => "application/json-patch+json"
                    );

                    $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $curlHeaders, $this->jsonData, $logData);
                }
            } else {
                if (defined("API_KEY"))
                {
                    $curlHeaders = array(
                        "key" => API_KEY,
                        "Content-Type" => "application/json-patch+json"
                    );

                    $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $curlHeaders, $this->jsonData, $logData);
                }
            }
        }
        if(!$ping) {
            $dataRequestArray = json_decode($this->jsonData, true);

            if($jsonResponse['status'] == "Failed") {
                $errorMessageAr = explode("--", $jsonResponse['response']);
                $errorMessageJson = substr_replace($errorMessageAr[2], "", 1, 0);
                $errorMessage = substr_replace($errorMessageJson, "{", 0, 0);
                $errorMessageLen = strlen($errorMessage);
                $errorMessage = substr_replace($errorMessage, "}", $errorMessageLen, 0);
                $errorMessageArray = json_decode($errorMessage, true);
                Logs\File::AddMessage($errorMessage, "errorMessage", LOG_OSK);

                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $dataRequestArray['crmId'],
                        "ENTITY_TYPE" => "DYNAMIC_134",
                        "COMMENT" => "[b][color=red]".$errorMessageArray['title'] . " | " . $errorMessageArray['detail']
                            ."[/color][/b]"
                    ]
                ]);
            }
        }

        return $jsonResponse;

    }
    protected function requestGET($url, $key = false, $internal = false) {
        $timeData = Logs\TimeData::start();
        if (defined("TOKEN_LK")) {
            $jsonResponse = \KPLab\Curl::get_LK(TOKEN_LK,$url,$this->jsonData,$this->CURLObjectData,$timeData,$this->point);
            return $jsonResponse;
        }
    }

    public function getCompany($companyId,$smartOSKId) {

        Logs\File::AddMessage([$companyId,$smartOSKId],"getCompany properties",LOG_OSK);
        global $DB;
        $this->smartOSKId = $smartOSKId;
        $this->getInfoSmart($this->smartOSKId);

        $this->companyId = $companyId;
        $this->entityTypeId = \CCrmOwnerType::Company;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->companyId);

        if($item) {
            $this->itemDatatitle = $item->getData()['TITLE'];
            $itemSQL = "SELECT * FROM b_crm_field_multi WHERE ENTITY_ID='COMPANY' AND ELEMENT_ID='{$companyId}' AND TYPE_ID='PHONE' AND VALUE_TYPE='MOBILE' ORDER BY ID ASC;";
            $resItemsQuery = $DB->query($itemSQL);
            while($resItem = $resItemsQuery->Fetch()) {
                $this->sellerPhone = (string) $resItem['VALUE'];
            }
            $this->getSellerInn($companyId);
            $this->getSellerRQ($companyId);
        }
        else {
            $this->errorMessage = "Не найдена Компания с ID:{$this->companyId}";
        }

        return $this;
    }
    public function getContact($contactId) {

        $this->itemId = $contactId;
        $this->entityTypeId = \CCrmOwnerType::Contact;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->itemId);

        if($item) {
            $this->itemDatatitle = $item->getData()['TITLE'];
            $this->getSellerInn($contactId);
        }
        else {
            $this->errorMessage = "Не найден Контакт с ID:{$this->itemId}";
        }

        return $this;
    }
    public function POST_PingSE() {
        $timeData = Logs\TimeData::start();
        $point = "BX_SE";
        $TOKEN_LK = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU";
        self::setCURLObjectData($this->companyId);
        self::getInfoSmart($this->smartLKId);

        $this->jsonData = "{}";

        $this->post_pingUrl = $this->apiUrl . "LegalEntity/GetInfoFromKontur?inn=".$this->sellerInn;
        //Logs\File::AddMessage($this->post_pingUrl,"post_pingUrl from LK",LOG_LK);

        $this->CURLObjectData['ITEM_TITLE'] = "ЛК[ПИНГ SE]: ". $this->itemDatatitle ." | ".$this->sellerId;
        $this->CURLObjectData['METHOD'] = "GET";
        $this->CURLObjectData['URL_REQUEST'] = $this->post_pingUrl;

        // Формируем логируемые данные
        $logData = [
            'objectData' => $this->CURLObjectData,
            'methodName' => __FUNCTION__,
            'controllerName' => get_class($this),
            'method' => 'GET',
            'timeData' => $timeData,
            'point' => $point
        ];

        // Используем KPLab\ApiRequest для выполнения GET-запроса
        $headersRequest = [
            'Authorization: Bearer ' . $TOKEN_LK,
            'Content-Type: application/json'
        ];
        // Вызов метода sendRequest с передачей $logData
        $this->arrayPingResponse = \KPLab\ApiRequest::sendRequest($this->post_pingUrl, 'GET', $headersRequest, null, $logData);


        /*if (defined("TOKEN_LK")) {
            $jsonResponse = \KPLab\Curl::get_LK(TOKEN_LK, $this->post_pingUrl, $this->jsonData, $this->CURLObjectData,
                $timeData, $this->point);
            $this->arrayPingResponse = $jsonResponse;
        }*/
        Logs\File::AddMessage($this->arrayPingResponse,"arrayPingResponse ПОСЛЕ from LK",LOG_LK);

        return $this->arrayPingResponse;
    }
    public function setAccountState() {
        $emulation = false;
        $timeData = Logs\TimeData::start();
        $this->entityTypeId = 134;
        $this->setCURLObjectData($this->smartOSKId);

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId);
        $item = $factory->getItem($this->smartOSKId);
        $smart134Data = $item->getData();
        $this->itemDatatitle = $smart134Data['TITLE'];
        /*$this->statusDocId = $smart1034Data['UF_CRM_STATUS_DOC'];
        $rsEnum = \CUserFieldEnum::GetList(array(), array(
            "ID" => $smart1034Data['UF_CRM_STATUS_DOC']
        ));
        if ($arEnum = $rsEnum->Fetch()) {
            $statusDoc = $arEnum['XML_ID'];
        }
        if($statusDoc == "edo_Success") $this->statusDocValue = "Assigned";*/
        $this->CURLObjectData['ITEM_TITLE'] = "ЛК[Передача статуса ОСК в SE]: ". $this->itemDatatitle;
        $this->CURLObjectData['METHOD'] = "POST";

        if (defined("TOKEN_LK")) {
            $httpHeaders = array(
                "key" => TOKEN_LK,
                "accept" => "application/json",
            );
            $queryData = "?accountStatus={$this->accountStatus}&id={$this->sellerId}";
            $queryUrl = "{$this->internalUrl}Lk/User/SetAccountState".$queryData;
            $jsonData = "{}";

            $logData = [
                'objectData' => $this->CURLObjectData,
                'methodName' => __FUNCTION__,
                'controllerName' => get_class($this),
                'method' => 'POST',
                'timeData' => $timeData,
                'point' => $this->point
            ];

            $jsonResponse = \KPLab\ApiRequest::sendRequest($queryUrl, 'POST', $httpHeaders, $jsonData, $logData);

            /*$jsonResponse = \KPLab\Curl::postWithQueryUrl(
                TOKEN_LK,
                $queryUrl,
                $this->CURLObjectData,
                $timeData,
                $this->point,
                $emulation,
                $httpHeaders
            );*/

            //Logs\File::AddMessage($jsonResponse,"jsonResponse",LOG_LK);

            if($jsonResponse['status'] == "Success") {
                $message = "Статус {$this->accountStatus} успешно отправлен в ЛК! ";
                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $item -> getId(),
                        "ENTITY_TYPE" => "DYNAMIC_134",
                        "COMMENT" => "[b]{$message}[/b]"
                    ]
                ]);
            } else {
                $errorMessageArray = json_decode($jsonResponse['response'], true);

                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $item -> getId(),
                        "ENTITY_TYPE" => "DYNAMIC_134",
                        "COMMENT" => "[b][color=red]".$errorMessageArray['title'] . " | " . $errorMessageArray['detail']
                            ."[/color][/b]"
                    ]
                ]);
            }

        }

        return $jsonResponse;
    }


}