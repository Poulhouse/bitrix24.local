<?php
$file_path_se = $_SERVER['DOCUMENT_ROOT'] . "/local/pages/logs/se/log.txt"; //путь к лог файлу с ошибками
$logs_SE = file($file_path_se);

if (false !== $logs_SE) {

	$file_SE = file($file_path_se, FILE_IGNORE_NEW_LINES);

	$result = [];

	for ($i = count($file_SE) - 1; $i >= 0; $i --){

		$current = explode(';', $file_SE[$i]);

		//if (strpos($current[0], 'say') !== FALSE) {

		$result[$i]['object'] = $current[0];
		$result[$i]['entityTypeId'] = $current[1];
		$result[$i]['itemId'] = $current[2];
		$result[$i]['direction'] = $current[3];
		$result[$i]['dateTime'] = $current[4];
		$result[$i]['jsonData'] = $current[5];
		$result[$i]['response'] = $current[6];
		$result[$i]['error'] = $current[7];
		$result[$i]['link'] = $current[8];
		$result[$i]['responseTime'] = $current[9];
		$result[$i]['duration'] = $current[10];


		if (count($result) === 1000)
			break;
		//}
	}

	$result = array_reverse($result);

} else {
	$error = 'Файл не может быть прочитан';
}
?>

<table class="table table-bordered table-hover align-middle">
	<thead class="table-light">
	<tr>
		<th>Название операции</th>
		<th>Статус операции
			<a href="#" class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Подсказка внизу">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16">
					<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
					<path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94"/>
				</svg>
			</a>
		</th>
		<th>Точка интеграции</th>
		<th>Ошибки за сегодня</th>
		<th>Вызовов за сегодня</th>
		<th>Вызовов за последний месяц</th>
		<th>Вызовов за все время</th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td>
			<a href="operation" class="text-primary" >Обмен физ. лиц (1C:АК-КРЕДИТ > Б24)</a><br>
			<small class="text-secondary">ss_sync_fl_update</small>
		</td>
		<td class="text-success">все хорошо</td>
		<td>SE -> BX</td>
		<td class="text-decoration-underline">0</td>
		<td>315</td>
		<td>9151</td>
		<td>2025668</td>
	</tr>
	<tr>
		<td>
			<a href="operation" class="text-primary" >Обмен Юр. лиц (1C:АК-КРЕДИТ > Б24)</a><br>
			<small class="text-secondary">ss_sync_ul_update</small>
		</td>
		<td class="text-warning">есть проблемы</td>
		<!--			<td class="text-success">все хорошо</td>-->
		<!--			<td class="text-danger">много ошибок</td>-->
		<!--			<td class="text-muted">нет вызовов</td>-->
		<td>SE -> BX</td>
		<td class="text-decoration-underline">2</td>
		<td>1256</td>
		<td>66259</td>
		<td>2455648</td>
	</tr>
	</tbody>
</table>
