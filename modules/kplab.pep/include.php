<?php

use Bitrix\Main\Loader;

Loader::registerAutoloadClasses("kplab.pep", [
    "\\Kplab\\Pep\\Service\\SignService"     => "lib/service/SignService.php",
    "\\Kplab\\Pep\\Service\\PdfGenerator"    => "lib/service/PdfGenerator.php",
    "\\Kplab\\Pep\\Service\\SmsService"      => "lib/service/SmsService.php",
    "\\Kplab\\Pep\\Service\\SignResultSaver" => "lib/service/SignResultSaver.php",
    "\\Kplab\\Pep\\Service\\SignSessionManager" => "lib/service/SignSessionManager.php",
    "\\Kplab\\Pep\\Logger"          => "lib/util/Logger.php",
    "\\Kplab\\Pep\\Controller\\SignedController" => "lib/controller/SignedController.php",
    "\\Kplab\\Pep\\Controller\\VerifyController" => "lib/controller/VerifyController.php",
    "\\Kplab\\Pep\\EventHandlers" => "lib/EventHandlers.php",
]);

require_once __DIR__ . '/lib/bootstrap.php';

require_once __DIR__ . '/lib/admin/Menu.php';