<?php
//irzd6a126ajwbemzyz9aj2adevl0y3oe
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

const WEB_HOOK_URL = 'https://testcrm.seller-capital.ru/rest/1/3lsxt0qfwbns0wve/';
define("LOG_SCRUM", $_SERVER['DOCUMENT_ROOT']."/local/logs/handlers_scrum.log");

//file_put_contents(LOG_SCRUM, ["REQUEST" => print_r($_REQUEST,true)], FILE_APPEND);

$event = $_REQUEST['event'];
$data = $_REQUEST['data'];
$auth = $_REQUEST['auth'];

switch ($event) {
    case 'ONTASKUPDATE':
        \KPLab\Tasks\Handler::getUpdateTaskByData($data);
        break;
    case 'ONTASKCOMMENTADD':
        $commentData = \KPLab\Tasks\Handler::getAddCommentByData($data);
        break;
    case 'default':
        break;
}
//file_put_contents(LOG_SCRUM, ["commentData" => print_r($commentData,true)], FILE_APPEND);
