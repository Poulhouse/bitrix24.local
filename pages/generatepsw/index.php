<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Генератор паролей в ЛК");
$APPLICATION->IncludeComponent(
	'kplab:generate_password',
	'.default',
	Array(
		"AJAX" => 'Y',
	),
	false
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>