<?php

namespace KPLab\Logs;
define("LOG_LOGS", $_SERVER['DOCUMENT_ROOT']."/local/classes/logs.log");
class IBlock {
    public static function setData(string $url, $jsonData, $jsonRes, array $objectData, array $time, string $point, $headers) {
        //File::AddMessage($url,"url",LOG_LOGS);
        //File::AddMessage($jsonData,"jsonData",LOG_LOGS);
        //File::AddMessage($jsonRes,"jsonRes",LOG_LOGS);
        //File::AddMessage($objectData,"objectData",LOG_LOGS);
        //File::AddMessage($time,"time",LOG_LOGS);
        //File::AddMessage($point,"point",LOG_LOGS);
        //File::AddMessage($headers,"headers",LOG_LOGS);

        $el = new \CIBlockElement;
        $PROP = array();
        if($point == "BX_SE") {
            $IBLOCK_SECTION_ID = 625;
            $POINT_OF_INTEGRATION_ENUM_ID = 575;
        }
        if($point == "SE_BX") {
            $IBLOCK_SECTION_ID = 626;
            $POINT_OF_INTEGRATION_ENUM_ID = 576;
        }
        if($point == "BX_ORDLAB") {
            $IBLOCK_SECTION_ID = 631;
            $POINT_OF_INTEGRATION_ENUM_ID = 662;
        }
        if($point == "BX_1C") {
            $IBLOCK_SECTION_ID = 660;
            $POINT_OF_INTEGRATION_ENUM_ID = 578;
        }
        if($point == "1C_BX") {
            $IBLOCK_SECTION_ID = 661;
            $POINT_OF_INTEGRATION_ENUM_ID = 577;
        }
        if($point == "BX_VBR") {
            $IBLOCK_SECTION_ID = 694;
            $POINT_OF_INTEGRATION_ENUM_ID = 713;
        }
        if($point == "EXTRANET_BX") {
            $IBLOCK_SECTION_ID = 696;
            $POINT_OF_INTEGRATION_ENUM_ID = 784;
        }
        if($objectData['METHOD'] == "POST") $METHOD_REQUEST_ENUM_ID = 584;
        if($objectData['METHOD'] == "GET") $METHOD_REQUEST_ENUM_ID = 585;
        if($jsonRes['success'] !== "") {
            $FLAG_ERRORS_ENUM_ID = 581;
            $PROP['STATUS_OPERATION'] = Array("VALUE" => 582 );
        }
        if($jsonRes['error'] !== "") {
            $FLAG_ERRORS_ENUM_ID = 580;
            $PROP['STATUS_OPERATION'] = Array("VALUE" => 583 );
        }

        $NAME = $objectData['ITEM_TITLE'];
        $PROP['METHOD_REQUEST'] = Array("VALUE" => $METHOD_REQUEST_ENUM_ID );
        $PROP['DATETIME_REQUEST'] = \CIBlockFormatProperties::DateFormat("d.m.Y H:i:s", MakeTimeStamp($time['date_start_site']));
        $PROP['TIME_RESPONSE'] = \KPLab\Logs\TimeData::finish($time)['duration'];
        $PROP['OPERATION'][0] = Array("VALUE" => $IBLOCK_SECTION_ID);
        $PROP['POINT_OF_INTEGRATION'] = Array("VALUE" => $POINT_OF_INTEGRATION_ENUM_ID );
        $PROP['FLAG_ERRORS'] = Array("VALUE" => $FLAG_ERRORS_ENUM_ID );
        $PROP['OBJECT_OF_INTEGRATION'] = $objectData['INIT_OBJECT_URL'];
        $PROP['URL_REQUEST'] = $url;
        $PROP['RESPONSE'] = json_encode($jsonRes['success']);
        $PROP['MESSAGES'][0] = Array("VALUE" => Array ("TEXT" => $jsonRes['error'], "TYPE" => "text"));
        $PROP['HEADERS_REQUEST'][0] = Array("VALUE" => Array ("TEXT" => json_encode($headers), "TYPE" => "text"));
        $PROP['BODY_REQUEST'][0] = Array("VALUE" => Array ("TEXT" => $jsonData, "TYPE" => "text"));

        $arLoadProductArray = Array(
            "MODIFIED_BY"    => 1, // элемент изменен текущим пользователем
            "IBLOCK_SECTION_ID" => $IBLOCK_SECTION_ID,          // элемент лежит в корне раздела
            "IBLOCK_ID"      => 173,
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => $NAME,
            "ACTIVE"         => "Y",            // активен
        );
        //File::AddMessage($arLoadProductArray,"arLoadProductArray",LOG_LOGS);

        if($res = $el->Add($arLoadProductArray)) {
            //File::AddMessage($res,"res",LOG_LOGS);
            return $res;
        }
        else {
            File::AddMessage($el->LAST_ERROR,"LAST_ERROR",LOG_LOGS);
            return $el->LAST_ERROR;
        }
    }
}