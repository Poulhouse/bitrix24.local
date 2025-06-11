<?php namespace Kplab\Exchange_log\Handlers;

use Kplab\Exchange_log\Helpers\ChangeChecker;
use KPLab\Logs;
define("LOG_LEAD_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/lead_changes.log");
define("LOG_LEAD_ERRORS_CHANGES", $_SERVER['DOCUMENT_ROOT']."/local/modules/kplab.exchange_log/lead_errors_changes.log");


class Lead {
    public static function OnAfterCrmLeadAdd(&$arFields) {

    }
    public static function OnAfterCrmLeadUpdate(&$arFields) {

    }
}