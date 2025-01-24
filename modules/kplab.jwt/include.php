<?php
$module_folder = \Bitrix\Main\Application::getDocumentRoot() . '/local/modules/kplab.jwt';

\Bitrix\Main\Loader::registerNamespace('KPLab\JWT\Controller', $module_folder . '/controller');

$classes = [
	'KPLab\JWT\Controller\ActionFilter\Authentication' => 'controller/actionfilter/authentication.php',
];

\Bitrix\Main\Loader::registerAutoLoadClasses(
	'kplab.jwt',
	$classes
);