<?php

namespace KPLab\CustomAgents;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_AGENTS_SMART", $_SERVER['DOCUMENT_ROOT']."/local/logs/CustomAgents_Smart.log");

class Smart {
    public static function goToStages(int $entityTypeId, int $idVoronka,int $qCard, string $currentStage, string $newStage, int $hardTime = 0, int $workdays = 0) {
        $dateTime = new \DateTime('now');
        $day = $dateTime->format('N');
        if($workdays == 1 && ($day == 6 || $day == 7)) {
            return "\KPLab\CustomAgents\Smart::goToStages({$entityTypeId}, {$idVoronka}, {$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
        }
        else
        {
            //Logs\File::AddMessage([$qCard,$currentStage,$newStage,$hardTime,$workdays],"НаВходе",LOG_AGENTS);
            global $DB;
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

            $curRes = $DB->Query("SELECT STATUS_ID FROM b_crm_status WHERE ENTITY_ID='DYNAMIC_".$entityTypeId."_STAGE_".$idVoronka."' AND NAME='".$currentStage."'");
            $newRes = $DB->Query("SELECT STATUS_ID FROM b_crm_status WHERE ENTITY_ID='DYNAMIC_".$entityTypeId."_STAGE_".$idVoronka."' AND NAME='".$newStage."'");

            $curRow = $curRes->Fetch();
            $CURRENT_STATUS_ID = $curRow['STATUS_ID'];

            $newRow = $newRes->Fetch();
            $NEW_STATUS_ID = $newRow['STATUS_ID'];

            $items = $factory->getItems([
                'filter' => [
                    '%STAGE_ID' => $CURRENT_STATUS_ID
                ]
            ]);

            if ($hardTime == 1) {
                //AddMessage2Log($NEW_STATUS_ID,"goToStages");
                $opening_hours = '11:00 - 19:00'; //(по ЕКБ)
                $opening_start = trim(explode('-', $opening_hours)[0]);
                $opening_start_hours = explode(':', $opening_start)[0];
                $opening_start_minutes = explode(':', $opening_start)[1];
                $opening_start_today = $opening_start_hours * 60 + $opening_start_minutes;

                $opening_end = trim(explode('-', $opening_hours)[1]);
                $opening_end_hours = explode(':', $opening_end)[0];
                $opening_end_minutes = explode(':', $opening_end)[1];
                $opening_end_today = $opening_end_hours * 60 + $opening_end_minutes;

                //if ($opening_end_today <= $opening_start_today) $opening_end_today = 24 * 60 + $opening_end_today;
                //AddMessage2Log($opening_start_today,"начало");
                //AddMessage2Log($opening_end_today,"конец");
                $now = new \DateTime('now');
                $now_hours = $now->format('H');
                $now_minutes = $now->format('i');
                $now_today = $now_hours * 60 + $now_minutes;

                if($now_today < $opening_start_today || $now_today > $opening_end_today) {
                    //AddMessage2Log($now_today,"НЕ Работает");

                    //Logs\File::AddMessage($now_today,"НЕ Работает",LOG_AGENTS);

                    //return "\KPLab\CustomAgents\Leads::goToStages({$qCard},'{$currentStage}','{$newStage}', {$hardTime}, {$workdays});";
                    return "\KPLab\CustomAgents\Smart::goToStages({$entityTypeId}, {$idVoronka}, {$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
                }

                //AddMessage2Log($now_today,"Работает");
            }

            if($items):
                if($qCard > count($items)) {
                    $sliceItems = array_slice($items, 0, count($items));
                } else {
                    $sliceItems = array_slice($items, 0, $qCard);
                }
                //AddMessage2Log(count($sliceItems),"кол-во элементов sliceItems");

                //AddMessage2Log($items[0]['ID'],"1 массив items");
                //AddMessage2Log($sliceItems[0]['ID'],"1 массив sliceItems");

                foreach ($sliceItems as $k => $item)
                {
                    $item->setStageId($NEW_STATUS_ID);
                    //$result = $item->save();

                    // Step 1: get operation
                    $operation = $factory->getUpdateOperation($item);

                    // Step 2: config operation (optional)
                    $operation->disableAllChecks();

                    // Step 3: launch operation
                    $operationResult = $operation->launch();

                    if ( $operationResult->isSuccess() )
                    {
                        /**
                         * Operation success
                         */
                        $message = "Данные успешно сохранились";
                        \CRest::call('crm.timeline.comment.add',[
                            'fields'=> [
                                "ENTITY_ID" => $item->getId(),
                                "ENTITY_TYPE" => "dynamic_{$entityTypeId}",
                                "COMMENT" => "[b]Автоматическая смена стадии [color=gray]{$currentStage} > {$newStage}[/color] ![/b]"
                            ]
                        ]);

                        Logs\File::AddMessage("{$currentStage} > {$newStage}","Автоматическая смена стадии", LOG_AGENTS_SMART);
                    }
                    else
                    {
                        /**
                         * Operation failed with error
                         *
                         * @operationResult->getErrors();
                         * @operationResult->getErrorMessages();
                         */
                        $message = $operationResult->getErrorMessages();
                        /*\CRest::call('crm.timeline.comment.add',[
                            'fields'=> [
                                "ENTITY_ID" => $item->getId(),
                                "ENTITY_TYPE" => "dynamic_{$entityTypeId}",
                                "COMMENT" => print_r($message)
                            ]
                        ]);*/
                    }
                    //AddMessage2Log($message,"Сообщение");
                    Logs\File::AddMessage($message,"Сообщение", LOG_AGENTS_SMART);
                }
            endif;



        }
        return "\KPLab\CustomAgents\Smart::goToStages({$entityTypeId}, {$idVoronka}, {$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
        //Logs\File::AddMessage([$qCard,$currentStage,$newStage,$hardTime,$workdays],"НаВыходе",LOG_AGENTS);
        //return "\KPLab\CustomAgents\Leads::goToStages({$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
    }
}