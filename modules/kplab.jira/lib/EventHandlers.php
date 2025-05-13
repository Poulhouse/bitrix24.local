<?php

namespace Kplab\Jira;

use Bitrix\Main\Event;
use KPLab\Logs;

define("LOG_JIRA_EVENT", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.jira/logs/jira_events.log");
class EventHandlers
{
    public static function OnTaskCommentAdd(&$event) {
        //Logs\File::AddMessage($event, "event", LOG_JIRA_EVENT);
        file_put_contents(LOG_JIRA_EVENT, ["event" => print_r($event,true)], FILE_APPEND);

        /*$itemId = $data['FIELDS_AFTER']['ID'];
        $taskId = $data['FIELDS_AFTER']['TASK_ID'];

        $data = [
            "taskId" => (int) $taskId,
            "itemId" => (int) $itemId
        ];

        $urlMethod = WEB_HOOK_URL . 'task.commentitem.get/';
        \KPLab\Logs\File::AddMessage($urlMethod, "urlMethod", LOG_SCRUM);

        $result = CRest::call("task.commentitem.get",$data)['result'];
        \KPLab\Logs\File::AddMessage($result, "result", LOG_SCRUM);*/

        return $event;
    }
    public static function OnTaskAdd(&$event) {
        Logs\File::AddMessage($event, "OnTaskAdd", LOG_JIRA_EVENT);
        return $event;
    }
    public static function OnTaskUpdate(&$event) {
        Logs\File::AddMessage($event, "OnTaskUpdate", LOG_JIRA_EVENT);
        return $event;
    }
}