<?php
namespace KPLab\VBR;

use Bitrix\Main\Loader;
use \KPLab\Logs;
define("LOG_VBR_POSTBACK", $_SERVER['DOCUMENT_ROOT']."/local/classes/vbr/postback.log");

class PostBack {
    //const URL = "https://adv.vbr.ru/api/v2/postback/kpk_sodeistvir/";
    public $Status;
    public $ClientOrderID;
    public $CLICKID;
    public $URL;

    public function __construct($elementId)
    {
        $this->URL = "https://adv.vbr.ru/api/v2/postback/kpk_sodeistvir/";
        $this->Status = "Request";
        $this->ClientOrderID = $elementId;
        //$this->CLICKID = $clickId;
    }
    public function getClientId() {
        $entityTypeId = 1;
        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
        $item = $factory -> getItem($this->ClientOrderID);
        if ($item) {
            $this->CLICKID = $item -> getData()["UF_CRM_LEAD_1714973916816"];
        }
        return $this;
    }
    public function request() {
        $timeData = Logs\TimeData::start();
        $point = "BX_VBR";
        $entityTypeId = 1;
        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
        $item = $factory -> getItem($this->ClientOrderID);
        if ($item)
        {
            $title = $item -> getData()['TITLE'];
            $objectData['ITEM_ID'] = $this->ClientOrderID;
            $objectData['ITEM_TYPE_ID'] = $entityTypeId;
            $objectData['ITEM_TITLE'] = "Отправка статуса клиента: ".$title;
            $objectData['METHOD'] = "POST";
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$this->ClientOrderID}/";

            Logs\File::AddMessage($item -> getData(),"item -> getData()",LOG_VBR_POSTBACK);

            $data["id"] = $this->CLICKID;
            $data["status"] = $this->Status;
            $data["clientOrderId"] = (string) $this->ClientOrderID;

            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
            $jsonResponse = \KPLab\Curl::postWithoutAuth($this->URL, $jsonData, $objectData, $timeData, $point);
            if (!empty($jsonResponse['success'])) {
                $responseArray = json_decode($jsonResponse['success'], true);
                return $jsonResponse;
            } else {
                $arErrors = $jsonResponse['errorArray'];
                if($arErrors['code'] == 'invalid-json') {
                    $statusCode = "Ошибка в запросе";
                    $detailError = $arErrors['detail'];
                }
                return $jsonResponse;
            }
        }
        return false;
    }
}