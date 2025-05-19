<?php

namespace KPLab\CustomAgents;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_AGENTS_LEADS", $_SERVER['DOCUMENT_ROOT']."/local/logs/CustomAgents_Leads.log");

class Leads {
    public static function syncLists($listsId, string $syncProperty_Id, string $syncUserField_Id, string $flagProperty_Code) {

        Loader::includeModule('iblock');

        $arSelect = ["ID", "PROPERTY_908"];

        $arFilter = [
            "IBLOCK_ID" => IntVal($listsId),
            "ACTIVE_DATE" => "Y",
            "ACTIVE"=>"Y"
        ];

        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            $arFilter,
            false,
            [],
            $arSelect
        );
        $c = 0;

        $entityTypeId = 1;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        //$items = $factory->getItems();

        while($ob = $res->GetNextElement())
        {
            $c++;
            $i++;
            $arFields = $ob->GetFields();
            //$_arFields['ID'] = $arFields['ID'];
            //$_arFields['LEAD_ID'] = $arFields[$syncProperty_Id];
            $item = $factory->getItem($arFields[$syncProperty_Id]);
            if ($item)
            {
                $syncField = $item -> get($syncUserField_Id);

                if ($syncField !== null)
                {
                    \CIBlockElement::SetPropertyValues($arFields['ID'], IntVal($listsId), 1, $flagProperty_Code);
                    //echo "Поле у лида {$item->getId()} заполнено: " . $syncField . "\n";
                } else
                {
                    $i++;
                    echo "Поле у лида {$item->getId()} не заполнено \n";
                    $item -> set($syncUserField_Id, $arFields['ID']);
                    //echo "Поле {$syncUserField_Id} у лида {$item->getId()} теперь заполнено значением: {$arFields['ID']} \n";
                    $result = $item -> save();
                    if (count($result->getErrorMessages())>0) {
                        \CIBlockElement::SetPropertyValues($arFields['ID'], IntVal($listsId), 0, $flagProperty_Code);
                        echo '<pre>';
                        var_dump($result->getErrorMessages());
                        echo '</pre>';
                    } else {
                        \CIBlockElement::SetPropertyValues($arFields['ID'], IntVal($listsId), 1, $flagProperty_Code);
                    }
                }
            }
        }
        //echo "Количество элементов списка {$listsId}: ".$c;
        //echo "Количество не синхронизированных элементов списка {$listsId}: ".$i;

        \CAgent::RemoveAgent("\KPLab\CustomAgents\Leads::syncLists({$listsId},{$syncProperty_Id},{$syncUserField_Id},{$flagProperty_Code});");

        //return $res;
        //return "KPLab\CustomAgents\Leads::syncLists({$listsId},{$syncProperty_Id},{$syncUserField_Id});";
        return true;
    }
    public static function goToStages(int $qCard, string $currentStage, string $newStage, int $hardTime = 0, int $workdays = 0) {
        $dateTime = new \DateTime('now');
        $day = $dateTime->format('N');
        if($workdays == 1 && ($day == 6 || $day == 7)) {
            return "\KPLab\CustomAgents\Leads::goToStages({$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
        }
        else
        {
            global $DB;
            $entityTypeId = 1;
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

            $curRes = $DB->Query("SELECT STATUS_ID FROM b_crm_status WHERE ENTITY_ID='STATUS' AND NAME='".$currentStage."'");
            $newRes = $DB->Query("SELECT STATUS_ID FROM b_crm_status WHERE ENTITY_ID='STATUS' AND NAME='".$newStage."'");

            $curRow = $curRes->Fetch();
            $CURRENT_STATUS_ID = $curRow['STATUS_ID'];

            $newRow = $newRes->Fetch();
            $NEW_STATUS_ID = $newRow['STATUS_ID'];

            $filter = [
                '%STATUS_ID' => $CURRENT_STATUS_ID
            ];
            $itemsCount = $factory->getItemsCount($filter);

            if ($itemsCount == 0) {
                Logs\File::AddMessage("Нет карточек для смены стадии для {$currentStage}","Сообщение", LOG_AGENTS_LEADS);
                return "\KPLab\CustomAgents\Leads::goToStages({$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
            }

            if($qCard <= $itemsCount) {
                $items = $factory->getItems([
                    'filter' => $filter,
                    'limit' => $qCard
                ]);
            }
            else {
                $items = $factory->getItems([
                    'filter' => $filter
                ]);
            }

            if ($hardTime == 1) {
                $opening_hours = '11:00 - 19:00'; //(по ЕКБ)
                $opening_start = trim(explode('-', $opening_hours)[0]);
                $opening_start_hours = explode(':', $opening_start)[0];
                $opening_start_minutes = explode(':', $opening_start)[1];
                $opening_start_today = $opening_start_hours * 60 + $opening_start_minutes;

                $opening_end = trim(explode('-', $opening_hours)[1]);
                $opening_end_hours = explode(':', $opening_end)[0];
                $opening_end_minutes = explode(':', $opening_end)[1];
                $opening_end_today = $opening_end_hours * 60 + $opening_end_minutes;

                $now = new \DateTime('now');
                $now_hours = $now->format('H');
                $now_minutes = $now->format('i');
                $now_today = $now_hours * 60 + $now_minutes;

                if($now_today < $opening_start_today || $now_today > $opening_end_today) {
                    return "\KPLab\CustomAgents\Leads::goToStages({$qCard},'{$currentStage}','{$newStage}', {$hardTime}, {$workdays});";
                }
            }

            if($items):
                foreach ($items as $item)
                {
                    $item->setStageId($NEW_STATUS_ID);
                    $operation = $factory->getUpdateOperation($item);
                    $operation->disableAllChecks();
                    $operationResult = $operation->launch();

                    if ($operationResult->isSuccess() )
                    {
                        $message = "Данные успешно сохранились";
                        \CRest::call('crm.timeline.comment.add',[
                            'fields'=> [
                                "ENTITY_ID" => $item->getId(),
                                "ENTITY_TYPE" => "lead",
                                "COMMENT" => "[b]Автоматическая смена стадии [color=gray]{$currentStage} > {$newStage}[/color] ![/b]"
                            ]
                        ]);

                        Logs\File::AddMessage("{$currentStage} > {$newStage}","Автоматическая смена стадии", LOG_AGENTS_LEADS);
                    }
                    else
                    {
                        $message = $operationResult->getErrorMessages();
                        \CRest::call('crm.timeline.comment.add',[
                            'fields'=> [
                                "ENTITY_ID" => $item->getId(),
                                "ENTITY_TYPE" => "lead",
                                "COMMENT" => print_r($message)
                            ]
                        ]);

                        Logs\File::AddMessage($message,"Сообщение", LOG_AGENTS_LEADS);
                    }
                }
            endif;



        }
        return "\KPLab\CustomAgents\Leads::goToStages({$qCard}, '{$currentStage}', '{$newStage}', {$hardTime}, {$workdays});";
    }
}