<?php namespace KPLab\Logs;

use Bitrix\Main;
use Bitrix\Main\Diag;

define("LOG_LOGS", $_SERVER['DOCUMENT_ROOT']."/local/classes/logs.log");
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