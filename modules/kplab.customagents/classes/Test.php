<?php

namespace KPLab\CustomAgents;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_AGENTS_TEST", $_SERVER['DOCUMENT_ROOT']."/local/logs/CustomAgents_Test.log");

class Test
{
    public static function agent() {
        Logs\File::AddMessage([],"Агент Включился",LOG_AGENTS);
        Logs\File::AddMessage([],"Агент Выключился",LOG_AGENTS);
        return "\KPLab\CustomAgents\Test::agent();";
    }

}