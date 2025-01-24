<?php
//$module_folder = \Bitrix\Main\Application::getDocumentRoot() . '/local/modules/kplab.downloadChat';

//\Bitrix\Main\Loader::registerNamespace('KPLab\Chat', $module_folder . '/lib');

$classes = [
	'KPLab\Chat\Entry' => '/local/modules/kplab.downloadChat/lib/entry.php',
];

\Bitrix\Main\Loader::registerAutoLoadClasses(
	'kplab.downloadChat',
	$classes
);