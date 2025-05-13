<?php namespace KPLab\Tasks;

define("LOG_TASKS_HANDLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/tasks_handler.log");

class Handler
{
    public static function getAddCommentByData($data)
    {
        $itemId = $data['FIELDS_AFTER']['ID'];
        $taskId = $data['FIELDS_AFTER']['TASK_ID'];

        $data = [
            "taskId" => (int) $taskId,
            "itemId" => (int) $itemId
        ];

        $urlMethod = WEB_HOOK_URL . 'task.commentitem.get/';
        \KPLab\Logs\File::AddMessage($urlMethod, "urlMethod", LOG_SCRUM);

        $result = CRest::call("task.commentitem.get",$data)['result'];
        \KPLab\Logs\File::AddMessage($result, "result", LOG_SCRUM);

        return $result;

    }

    public static function getUpdateTaskByData($data)
    {

    }
}