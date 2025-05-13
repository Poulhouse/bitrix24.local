<?php

namespace Kplab\Pep\Controller;

class VerifyController
{
    public static function handle()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kplab.pep/templates/verify.php';
    }
}
