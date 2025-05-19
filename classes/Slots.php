<?php
namespace KPLab;
use \Bitrix\Main;

class Slots {
	static function cmp($a, $b)
	{
		if (strtotime($a) == strtotime($b)) {
			return 0;
		}
		return (strtotime($a) < strtotime($b)) ? -1 : 1;
	}

	static function round_time($ts, $step) {
		return(floor(floor($ts/60)/60)*3600+floor(date("i",$ts)/$step)*$step*60);
	}

	public static function soglasovanieTime($fromDate, $toDate, array $UsersIds = []) {
		$params = [
			'from' => $fromDate,
			'to' => $toDate,
			'users' => $UsersIds,
		];
		//AddMessage2Log($params,'$params');
		$users = \CRest::call('calendar.accessibility.get',$params)['result'];
		foreach($users as $userId => $user)
		{
			foreach ($user as $event)
			{
				$rsUser = \CUser::GetByID($userId);
				$arUser = $rsUser->Fetch();

				$dateFrom = date('d.m.Y',strtotime($event['DATE_FROM']));
				if($dateFrom !== date('d.m.Y',strtotime($event['DATE_TO']))) {
					$dateTo = date('d.m.Y',strtotime($event['DATE_TO']));
					$eventResource[] = [
						//'EVENT_ID' => $event['ID'],
						'NAME' => $event['NAME'],
						'DATE_FROM_TO' => "",
						'TIME_FROM_TO' => 'с '.$dateFrom. ' '.date('H:i',strtotime($event['DATE_FROM'])). ' по '.$dateTo.' '.date('H:i',strtotime($event['DATE_TO'])),
						//'TO' => $event['DATE_TO'],
						'USERBUSY' => $arUser['LAST_NAME'].' '.$arUser['NAME']
					];
				} else {
					$eventResource[] = [
						//'EVENT_ID' => $event['ID'],
						'NAME' => $event['NAME'],
						'DATE_FROM_TO' => $dateFrom,
						'TIME_FROM_TO' => date('H:i',strtotime($event['DATE_FROM'])). ' - '.date('H:i',strtotime($event['DATE_TO'])),
						//'TO' => $event['DATE_TO'],
						'USERBUSY' => $arUser['LAST_NAME'].' '.$arUser['NAME']
					];
				}




			}
		}
/*
		for (
			reset($dateTimeList), $prevTime = null;
			($currentTime = current($dateTimeList));
			next($dateTimeList), $prevTime = $currentTime
		) {
			//return $dateTimeList;
			if ($prevTime === null) return $dateTimeList;
			// алгоритм проверяет предположение, что предыдущий интервал идёт сразу перед текущим
			// значит между ними есть 1 час, который требуется на комплексную мойку.
			$prevSplitTime = explode(':', $prevTime);
			$prevHour = $prevSplitTime[0];
			$prevMin = $prevSplitTime[1];

			$expectedHour = (string) (($prevMin == '00')? $prevHour : $prevHour++);
			$expectedMin = (string) (($prevMin == '00')? '30' : '00');
			$expectedTime = "{$expectedHour}:{$expectedMin}";

			if ($currentTime === $expectedTime) {
				$allowList[] = $prevTime;
			}
		}
*/
		$str = "";
		foreach ($eventResource as $event) {
			$NAME = $event['NAME'];
			if(!empty($event['DATE_FROM_TO'])) $DATE_FROM_TO = $event['DATE_FROM_TO'];
			$TIME_FROM_TO = $event['TIME_FROM_TO'];
			$USERBUSY = $event['USERBUSY'];

			$str .= "[b]{$NAME}[/b]\nДата и время: [b]{$DATE_FROM_TO} {$TIME_FROM_TO}[/b]\nСотрудник: [b]{$USERBUSY}[/b]\n\n";
		}
		return $str;
	}

	public static function freeTime($fromDate, $toDate, array $UsersIds = []) {
		$params = [
			'from' => $fromDate,
			'to' => $toDate,
			'users' => $UsersIds,
		];



		$users = \CRest::call('calendar.accessibility.get',$params)['result'];


		foreach($users as $userId => $user)
		{
			if(!empty($user))
			{
				foreach ($user as $i => $event)
				{

					$dateFrom = date('d.m.Y', strtotime($event['DATE_FROM']));
					$timeFrom = date('H:i', strtotime($event['DATE_FROM']));
					$timeTo = date('H:i', strtotime($event['DATE_TO']));

					if ($dateFrom !== date('d.m.Y', strtotime($event['DATE_TO'])))
					{
						$dateTo = date('d.m.Y', strtotime($event['DATE_TO']));
						$dateTime['date'] = $dateFrom . ' ' . $dateTo;
						$dateTime['time']['start'] = $timeFrom;
						$dateTime['time']['end'] = $timeTo;
					} else
					{
						$dateTo = $dateFrom;
						$dateTime['date'] = $dateFrom;
						$dateTime['time']['start'] = $timeFrom;
						$dateTime['time']['end'] = $timeTo;
					}
					//$dateParseFrom = date_parse($event['DATE_FROM']);

					/*$dateTime['date'] = $dateFrom .'';
					$dateTime['time'] = $timeFrom . ' - ' . $timeTo;
					$dateTime['end']['date'] = $dateTo;
					$dateTime['end']['time'] = $timeTo;*/
					$arDateTime[$userId][$i] = $dateTime;
					/*$arDateTime[$i]['start']['time'] = $timeFrom;
					$arDateTime[$i]['end']['date'] = $dateTo;
					$arDateTime[$i]['end']['time'] = $timeTo;*/

				}
			}
			$dateFrom = date('d.m.Y', strtotime('now'));
			$dateTo = $dateFrom;
			$timeFrom = date('H:i', strtotime('09:00'));
			$timeTo = date('H:i', strtotime('10:00'));

			$dateTime['date'] = $dateFrom;
			$dateTime['time']['start'] = $timeFrom;
			$dateTime['time']['end'] = $timeTo;

			$arDateTime[$userId][0] = $dateTime;

		}



		foreach($arDateTime as $k => $arDateTimeItem) {
			foreach($arDateTimeItem as $dateTimeItem) {
				$ar[] = $dateTimeItem;
			}
		}

		$countAr = count($ar);
		//for($j = 0;$j < $countAr; $j++) {

		//$date = $ar[0]['date'];

		for($i = 0; $i < $countAr; $i++)
		{
			$arrr[$ar[$i]['date']][] = $ar[$i]['time'];
		}



		foreach($arrr as $key => $arrrItem) {
			usort($arrr[$key], function ($a, $b) {
				if ($a == $b) {
					return 0;
				}
				return ($a < $b) ? -1 : 1;
			});
		}


		foreach($arrr as $g => $arItem)
		{
			$countM = count($arItem);
			for($i = 0; $i < $countM; $i++)
			{
				//return $arItem[$i+1]['start'];
				if(!empty($arItem[$i+1]['start'])) {
					if($arItem[$i]['end'] !== $arItem[$i+1]['start'] && strtotime("+1 hour", strtotime($arItem[$i]['end'])) < strtotime($arItem[$i+1]['start']) ) {
						//$m[$g." - ".$i." - ".$i+1] = "{$g} Конец: {$arItem[$i]['end']} и Начало: {$arItem[$i+1]['start']} Не Равны и разница больше часа";
						return date("d.m.Y H:i:s",strtotime($g . ' ' . $arItem[$i]['end']));

					} elseif($arItem[$i]['start'] !== "09:00" && $arItem[$i+1]['start'] !== "10:00" ) {
						return date("d.m.Y H:i:s",strtotime($g . ' ' . $arItem[$i]['end']));
					}
				} elseif($arItem[$i]['start'] !== "09:00" && $arItem[$i]['start'] !== "10:00") {
					return date("d.m.Y H:i:s",strtotime($g . ' 09:00'));
				} else {
					$timestamp = time();
					$time = ceil($timestamp / 3600) * 3600+1800;
					$newDateTime = date("d.m.Y H:i:s", $time);
					//AddMessage2Log($newDateTime,"Окургление до 30 минут");
					return $newDateTime;
				}
			}

			//$m[explode(" ",$arItem)[0]]['start'][] = explode(" ",$arItem)[1];
			//$m[explode(" ",$arItem)[0]]['end'][] = explode(" ",$arItem)[2];

		}


		/*for($i = 0; $i < $countM; $i++) {
			if($m['end'][$i] !== $m['start'][$i+1]) {

			} else {
				continue;
			}
		}*/



		//}


		return null;
	}
}