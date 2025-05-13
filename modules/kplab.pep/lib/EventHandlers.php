<?php

namespace Kplab\Pep;

use \Kplab\Pep\Controller\SignedController;
use \Kplab\Pep\Controller\VerifyController;

class EventHandlers
{
    public static function onPageStartHandler(): void
    {
        $uri = $_SERVER['REQUEST_URI'];

        if (preg_match('#^/pep/signed/#', $uri)) {
            SignedController::handle();
            die;
        }

        if (preg_match('#^/pep/verify/#', $uri)) {
            VerifyController::handle();
            die;
        }
    }
}
