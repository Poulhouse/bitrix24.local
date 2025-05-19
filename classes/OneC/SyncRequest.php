<?php

namespace KPLab\OneC;

use Bitrix\Main\Application;
use Bitrix\Main\Event;
use KPLab\Logs;

class SyncRequest extends \Bitrix\Main\Engine\Controller {
    private $syncRequest;
    public function getDefaultPreFilters()
    {
        return [
            new Authentication(),
        ];
    }
    public function getDefaultPostFilters()
    {
        return array();
    }

    protected function prepareParams()
    {
        //$this->loans = new \KPLab\JWT\Loans();
        return parent::prepareParams();
    }

    public function onBeforeAction(Event $event) {

        /*$context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();*/

        global $USER;
        if (!is_object($USER))
            $USER = new \CUser;
        // по умолчанию авторизация из-под админа
        $USER->Authorize(1);
        /*
                $apikey = json_decode($request->getInput(),true)['apiKey'];

                if ($apikey)
                {
                    if ($apikey !== APIKEY)
                    {
                        $this -> addError(new Error('API key not found', 401));
                        return new EventResult(EventResult::ERROR, '', '', $this);
                    } else
                    {
                        global $USER;
                        if (!is_object($USER))
                            $USER = new \CUser;
                        // по умолчанию авторизация из-под админа
                        $USER->Authorize(1);
                    }
                }
        */

        return null;
    }

    public function syncAction() {

        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        Logs\File::AddMessage($request,"request", LOG_ONEC_SYNC_TWO);

        $response = $context -> getResponse();
        $server = $context -> getServer();
        $point = "1C_BX";
        $url = "https://crm.seller-capital.ru/api/OneCSync/";
        $headers = $request->getHeaders();
        $recieve = json_decode($request->getInput(),true);
        if(isset($recieve['JSON']))
        {
            $strJson = $recieve['JSON'];
            try {
                $data = \Bitrix\Main\Web\Json::decode($strJson);
            } catch (\Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                ss_SocNetMessageAdd(1, 483, $e->getMessage()."Ошибка чтения JSON при получении данных из АК-Кредит - ".$strJson);

                $jsonRes['success'] = "";
                $jsonRes['error'] = $e->getMessage()."Ошибка чтения JSON при получении данных из АК-Кредит - ".$strJson;
            }
            $jsonRes['success'] = '{"status":"success"}';
            $jsonRes['error'] = "";
        }

        $objectData['ITEM_TITLE'] = $data['Наименование'] . " | " . $data['Номер']. " от " . $data['Дата'];
        $objectData['ITEM_TYPE_ID'] = "";
        $objectData['METHOD'] = $server['REQUEST_METHOD'];
        $objectData['INIT_OBJECT_URL'] = "";


        if($jsonRes['error'] == "") {
            $res = Logs\IBlock::setData($url, $strJson, $jsonRes, $objectData, $timeData, $point, $headers);
            return $res;
        } else {
            Logs\IBlock::setData($url, $strJson, $jsonRes, $objectData, $timeData, $point, $headers);
            return false;
        }

    }
}