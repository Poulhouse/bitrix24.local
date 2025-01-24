<?php
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$value = $request->getPost("table");

header('Content-Type: text/html; charset=utf-8');
header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');
header('Content-transfer-encoding: binary');
header('Content-Disposition: attachment; filename=list.xls');
header('Content-Type: application/x-unknown');

echo<<<HTML
<table border="1">
<tr><td>
htmlentities(iconv("utf-8", "windows-1251", $value),ENT_QUOTES, "cp1251"));
</td></tr>
</table>
HTML;
/**/
?>
<?php
/*$file = $_SERVER['DOCUMENT_ROOT'] . 'excel_report.txt';
$fp = fopen($file, "w+");//поэтому используем режим 'w'
// записываем данные в открытый файл
fwrite($fp, $value);
//не забываем закрыть файл, это ВАЖНО
fclose($fp);*/
/*
if($b = ) {
	echo "данные добавлены!";
} else {
	echo "данные не добавлены!";
}*/






