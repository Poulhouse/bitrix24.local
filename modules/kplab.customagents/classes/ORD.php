<?php

namespace KPLab\CustomAgents;


use Bitrix\Main\Loader;
use \KPLab\Logs;


define("LOG_AGENTS_ORD", $_SERVER['DOCUMENT_ROOT']."/local/logs/CustomAgents_ORD.log");
class ORD {
    public static function loadFullOrd() {
        //$res = \KPLab\OrdLab\KKTU::KtuNewList();

        //Logs\File::AddMessage($res,"Отработал KtuNewList",LOG_AGENTS);
        //sleep(10);
        $loaderORD = new \KPLab\OrdLab\LoadFromORD();
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/organizations/search",176,"ORG");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/platforms/search",175,"PLA");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/contracts/search",177,"CON");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/creatives/search",174,"CRE");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/invoices/search",234,"INV");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/creative-statistics/search",181,"STA");
        sleep(10);
        $loaderORD -> LoadFromOrdFULL("https://api.ord-lab.ru/api/v3/deleting-requests/list","","DEL");
        Logs\File::AddMessage(null,"Загрузка с ОРД закончена",LOG_AGENTS_ORD);
        return "\KPLab\CustomAgents\ORD::loadFullOrd();";
    }
    public static function DeleteKKTU($iblockId, $batchElements, $batchSections) {
        $arTotalDeleted = \KPLab\OrdLab\KKTU::DeleteAllElementsAndSections($iblockId, $batchElements, $batchSections, false);
        if(empty($arTotalDeleted)){

            Logs\File::AddMessage(null,"Удаление ККТУ не произошло",LOG_AGENTS_ORD);
        } else {
            Logs\File::AddMessage($arTotalDeleted,"Удаление ККТУ произошло",LOG_AGENTS_ORD);
        }
        return "\KPLab\CustomAgents\ORD::DeleteKKTU({$iblockId}, {$batchElements}, {$batchSections});";
    }
}