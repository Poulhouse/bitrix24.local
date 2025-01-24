<?php
$module_folder = \Bitrix\Main\Application::getDocumentRoot() . '/local/modules/kplab.api';

\Bitrix\Main\Loader::registerNamespace('KPLab\API\Controller', $module_folder . '/controller');

$classes = [
	'KPLab\API\Controller\ActionFilter\Authentication' => 'controller/actionfilter/authentication.php',
	'KPLab\API\Controller\ActionFilterBots\Authentication' => 'controller/actionfilter/authenticationforbots.php',
	'KPLab\API\Controller\DashBoard' => 'controller/dashboard/dashboard.php',
];

\Bitrix\Main\Loader::registerAutoLoadClasses(
	'kplab.api',
	$classes
);