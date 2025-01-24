<?php namespace KPLab\Logs;

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

	public static function AddMessage($text, $module = '', $filepath = '', $traceDepth = 6, $showArgs = false )
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

class TimeData {

	static function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec);
	}

	static function microtimeFormat($data, $format=null, $lng=null)
	{
		$duration = self::microtime_float() - $data;

		$duration = $duration/1000;

		$hours = (int)($duration/60/60);
		$minutes = (int)($duration/60)-$hours*60;
		$seconds = $duration-$hours*60*60-$minutes*60;
		return number_format((float)$duration, 3, '.', '');
	}

	public static function start() {
		$timeData = [];
		$timeData['start'] = microtime(true);
		$date_start = new \DateTimeImmutable('now');
		$timeData['date_start'] = $date_start->format("Y-m-d\\TH:i:sP");
		$timeData['date_start_site'] = $date_start->format("d.m.Y H:i:s");

		return $timeData;
	}

	public static function finish($time) {
		$date_finish  = new \DateTimeImmutable('now');

		$time['finish'] = microtime(true);
		$time['date_finish'] = $date_finish->format("Y-m-d\\TH:i:sP");

		$durationSeconds = round(($time['finish'] - $time['start']), 5);

		$time['duration'] = $durationSeconds;

		$mtime = microtime(true);
		$endtime = $mtime;
		$totaltime = round(($endtime - $time['start']), 5);

		$time['processing'] = $totaltime;






		return $time;
	}
}