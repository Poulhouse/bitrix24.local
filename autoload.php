<?php
// Для основного кода модуля (если есть)


\Bitrix\Main\Loader::registerNamespace('\\KPLab\\', dirname(__FILE__) . '/classes');


//region CRest
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    '\CRest' => '/local/crest/crest.php',
    'CRest' => '/local/crest/crest.php',
    '\B24Rest' => '/local/crest/B24Rest.php',
    'B24Rest' => '/local/crest/B24Rest.php',
    '\RestTest' => '/local/php_interface/class-resttest.php',
    '\MyClass' => '/local/classes/MyClass.php',
    '\MergePDF' => '/local/classes/MergePDF.php',
    '\Bitrix24API' => '/local/classes/Bitrix24API.php',
]);
//endregion CRest