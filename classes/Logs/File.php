<?php
namespace KPLab\Logs;

use Bitrix\Main;
use Bitrix\Main\Diag;

define("LOG_LOGS", $_SERVER['DOCUMENT_ROOT']."/local/classes/logs.log");

class File {
    public static function message($message, $moduleName, $filepath = null) {

        if(is_null($filepath)) {
            $filepath = $_SERVER['DOCUMENT_ROOT']."/logs/global.log";
        }
        define("LOG_FILENAME", $filepath);
        AddMessage2Log($message, $moduleName);
        /*
        define("LOG_FILENAME", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/Organizations.log");
        $timeStart = \KPLab\Logs\TimeData::start();
        $str = "";
        $str .= $moduleName.PHP_EOL;
        $str .= "DataTime: ".$timeStart['date_start_site'].PHP_EOL;
        $str .= $message.PHP_EOL;
        $fw = fopen($filepath, "a+");
        fwrite($fw, $str.PHP_EOL);
        fclose($fw);*/

        //error_log("{$message}\n", 3, $filepath);
        return null;
    }

    public static function AddMessage($text, $module = '', $filepath = '', $traceDepth = 3, $showArgs = false )
    {
        if ($filepath !== '')
        {
            $logger = Diag\Logger::create('main.Default', [$filepath, $showArgs]);
            if ($logger === null)
            {
                $logger = new Diag\FileLogger($filepath, 0);
                $formatter = new Diag\LogFormatter($showArgs);
                $logger->setFormatter($formatter);
            }

            $trace = '';
            if ($traceDepth > 0)
            {
                $trace = Main\Diag\Helper::getBackTrace($traceDepth, ($showArgs ? null : DEBUG_BACKTRACE_IGNORE_ARGS), 2);
            }

            $context = [
                'module' => $module,
                'message' => $text,
                'trace' => $trace,
            ];

            $message = "Host: {host}\n"
                . "Date: {date}\n"
                . ($module != '' ? "Module: {module}\n" : '')
                . "{message}\n"
                . "{trace}"
                . "{delimiter}\n"
            ;

            $logger->debug($message, $context);
        }
    }


    public static function set(string $url, $jsonData, array $jsonRes, array $objectData, array $time, string $filePath,string $queryData = "GET") {

        $fw = fopen($filePath, "a+");
        $str = "";
        $str .= $objectData['ITEM_TITLE'].";";
        $str .= $objectData['ITEM_TYPE_ID'].";";
        $str .= $objectData['ITEM_ID'].";";
        $str .= "{$queryData} -- {$url};";
        $str .= "[{$time['date_start']}];";
        $str .= $jsonData.";";
        $str .= $jsonRes['success'].";";
        $str .= $jsonRes['error'].";";
        $str .= $objectData['INIT_OBJECT_URL'].";";
        $str .= \KPLab\Logs\TimeData::finish($time)['duration'].";"; //duration
        $str .= \KPLab\Logs\TimeData::finish($time)['processing'].";"; //processing
        fwrite($fw, $str.PHP_EOL);
        fclose($fw);

        return null;
    }
}