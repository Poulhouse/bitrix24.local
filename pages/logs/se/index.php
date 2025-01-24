<!doctype html>
<html lang="ru">
<head>
	<!-- Обязательные метатеги -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

	<title>Привет мир!</title>
</head>
<body>
<?
$file_path = $_SERVER['DOCUMENT_ROOT'] . "/local/pages/logs/se/log.txt"; //путь к лог файлу с ошибками
$logs = file($file_path);

if (false !== $logs) {

	$file = file($file_path, FILE_IGNORE_NEW_LINES);

	$result = [];

	for ($i = count($file) - 1; $i >= 0; $i --){

		$current = explode(';', $file[$i]);

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

<?if (isset($error)) : ?>

	<p><?=$error;?></p>

<? else : ?>

	<table class="table table-bordered table-hover">
		<thead>
			<tr>
				<td>№</td>
				<td>Объект</td>
				<td>Type</td>
				<td>ID</td>
				<td>Направление</td>
				<td>Дата/время</td>
				<td>Данные</td>
				<td>Ответ</td>
				<td>Ошибка</td>
				<td>Основание</td>
				<td>Время ответа</td>
				<td>Продолжительность</td>
			</tr>
		</thead>
		<tbody>
		<?foreach ($result as $i => $item) : ?>
			<?

				$classes = '';
				if($item['error'] !== '') $classes .= 'table-danger ';

			?>
			<tr class="<?=$classes;?>">
				<td><?=$i+1;?></td>
				<td><?=$item['object'];?></td>
				<td><?=$item['entityTypeId'];?></td>
				<td><?=$item['itemId'];?></td>
				<td><?=$item['direction'];?></td>
				<td><?=$item['dateTime'];?></td>
				<td><?=$item['jsonData'];?></td>
				<td><?=$item['response'];?></td>
				<td><?=$item['error'];?></td>
				<td><?=$item['link'];?></td>
				<td><?=$item['responseTime'];?></td>
				<td><?=$item['duration'];?></td>
			</tr>

		<? endforeach ?>
		</tbody>
	</table>

<? endif ?>

<!-- Дополнительный JavaScript; выберите один из двух! -->

<!-- Вариант 1: Bootstrap в связке с Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<!-- Вариант 2: Bootstrap JS отдельно от Popper
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
-->
</body>
</html>


