<?php namespace KPLab\TimeMan;

class WorkTime {
	public static function getMinutes($startDate, $endDate)
	{
		$originalStart = new \DateTime($startDate);
		$start = clone $originalStart;

		$originalEnd = new \DateTime($endDate);
		$end = clone $originalEnd;

		$workStartHour = 10;
		$workStartMin = 0;
		$workEndHour = 19;
		$workEndMin = 0;
		$workdayHours = 9;

		$hours = 0;
		$minutes = 0;
		$seconds = 0;

		$weekends = ['Saturday', 'Sunday'];


		//echo PHP_EOL."===================".PHP_EOL;

		while (in_array($start->format('l'), $weekends))
		{
			//echo "Начиная с выходных? Перейти к буднему дню.".PHP_EOL;
			$start->modify('midnight tomorrow');
		}

		//print_r($start);


		while (in_array($end->format('l'), $weekends))
		{
			//echo "Заканчивается на выходных? Вернитесь в будний день".PHP_EOL;
			$end->modify('-1 day')->setTime(23, 59);
		}

		//print_r($end);
		//echo PHP_EOL."===================".PHP_EOL;

		if ($start > $end) {
			//echo  "Дата начала после даты окончания? Может случиться, если начало и конец приходятся на одни и те же выходные (упс).".PHP_EOL;
			return 0; //throw new \Exception('Start date is AFTER end date!');
		}



		// Время выходит за рамки обычных рабочих часов? Если да, отрегулируйте.
		$startAdj = clone $start;

		if ($start < $startAdj->setTime($workStartHour, $workStartMin))
		{
			//echo "Старт раньше; адаптироваться к реальному времени начала.".PHP_EOL;
			$start = $startAdj;
		} else if ($start >= $startAdj->setTime($workEndHour, $workEndMin))
		{
			//echo "Начало после закрытия этого дня, адаптироваться к реальному времени окончания.".PHP_EOL;
			$start = $startAdj;
		}

		print_r($start);

		$endAdj = clone $end;

		if ($end >= $endAdj->setTime($workEndHour, $workEndMin))
		{
			//echo "Конец наступит позже; адаптироваться к реальному времени начала следующего дня.".PHP_EOL;
			$end = $endAdj;
		}
		else if ($end < $endAdj->setTime($workStartHour, $workStartMin))
		{
			//echo "Конец предшествует началу этого дня, установить на начало дня.".PHP_EOL;
			$end = $endAdj;
		}

		print_r($end);

		if($start->format('Y-m-d') == $end->format('Y-m-d')) {
			echo "Один и тот же день";
			$interval_0 = $start->diff($end);
			echo $interval_0->format("%H:%I:%S (Полных дней: %a)").PHP_EOL.PHP_EOL;
			$hours += $interval_0->h;
			$minutes += $interval_0->i;
			$seconds += $interval_0->s;
			$minutesRes = round( ($hours*60) + $minutes + ($seconds/60), 1);

			return $minutesRes;
		}

		// Рассчитайте разницу между времени начала и концом дня.
		$endDayTime = clone $start;
		$endDayTime->setTime($workEndHour, $workEndMin);

		$interval_1 = $start->diff($endDayTime);
		echo $interval_1->format("%H:%I:%S (Полных дней: %a)").PHP_EOL.PHP_EOL;
		$hours += $interval_1->h;
		$minutes += $interval_1->i;
		$seconds += $interval_1->s;

		// Рассчитайте разницу между началом дня и временем конца.
		$startDayTime = clone $end;
		$startDayTime->setTime($workStartHour, $workStartMin);

		$interval_2 = $startDayTime->diff($end);
		echo $interval_2->format("%H:%I:%S (Полных дней: %a)").PHP_EOL.PHP_EOL;
		$hours += $interval_2->h;
		$minutes += $interval_2->i;
		$seconds += $interval_2->s;

		echo PHP_EOL."===================".PHP_EOL;

		$start->setTime($workStartHour, $workStartMin)->modify("+1 days");
		$end->setTime($workEndHour, $workEndMin)->modify("-1 days");


		print_r($start);
		print_r($end);

		if ($start > $end) {
			echo "Часы: " . $hours . PHP_EOL;
			echo "Минуты: " . $minutes . PHP_EOL;
			echo "Секунды: " . $seconds . PHP_EOL;
			$minutesRes = round( ($hours*60) + $minutes + ($seconds/60), 1);

			return $minutesRes;
		} else {

			//echo PHP_EOL."===================".PHP_EOL;

			// Рассчитайте разницу между нашими модифицированными днями.
			$diff = $start->diff($end);

			// Пройдите каждый день, используя исходные значения, чтобы мы могли проверить выходные дни.
			$period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

			foreach ($period as $day)
			{
				if (in_array($day->format('l'), ['Saturday', 'Sunday'])) {
					//echo "Если это выходной день, вычтите его из общего количества дней в разнице.".PHP_EOL;
					$diff->d--;
				}
			}
			//echo PHP_EOL."===================".PHP_EOL;

			//echo $diff->format("%H:%I:%S (Полных дней: %d)"), "\n";

			if($diff->d < 0) {
				$diff->d = 0;
				$diff->h = 0;
			}else if($diff->d == 0) {
				$hours += $diff->h;
			} else {
				$hours += ($diff->d * $workdayHours) + $diff->h;
			}
			//print_r($diff);


			//echo PHP_EOL."===================".PHP_EOL;

			echo "Часы: " . $hours . PHP_EOL;
			echo "Минуты: " . $minutes . PHP_EOL;
			echo "Секунды: " . $seconds . PHP_EOL;
			$minutesRes = round( ($hours*60) + $minutes + ($seconds/60), 1);

			return $minutesRes;
		}

	}
}