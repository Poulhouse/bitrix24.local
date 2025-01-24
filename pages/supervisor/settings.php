<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Настройка времени для формирования очередей");
?>
<h1>Настройка времени</h1>
<h2>Настройка времени распределения заявок в очереди</h2>
<form>
<table>
	<tr><td>Тип очереди</td><td>Старт</td><td>Финиш</td><td></td></tr>
	<tr><td>Дневная</td><td><input type="text" style="width:80px" value="9" >:<input style="width:80px" type="text" value="00" > </td><td><input style="width:80px" type="text" value="19" >:<input style="width:80px" type="text" value="00" > </td><td><select><option>GMT+5 Екатеринбург, Свердловская область</option></select></td></tr>
	</table>
	<input type="submit" value="Обновить"/>
</form><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>