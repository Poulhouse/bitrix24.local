<?php
$module_folder = \Bitrix\Main\Application::getDocumentRoot() . '/local/modules/kplab.onec';

\Bitrix\Main\Loader::registerNamespace('KPLab\OneC\Controller', $module_folder . '/controller');

$classes = [
	'KPLab\OneC\Controller\ActionFilter\Authentication' => 'controller/actionfilter/authentication.php',
];

\Bitrix\Main\Loader::registerAutoLoadClasses(
	'kplab.onec',
	$classes
);