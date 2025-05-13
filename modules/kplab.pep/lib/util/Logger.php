<?php

namespace Kplab\Pep;

use Bitrix\Main\Application;

class Logger
{
    public static function log(string $docHash, string $eventType, string $message, ?string $phone = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $conn = Application::getConnection();
        $safeMsg = $conn->getSqlHelper()->forSql($message);

        $conn->queryExecute("
            INSERT INTO kplab_pep_log (DOCUMENT_HASH, EVENT_TYPE, PHONE, IP_ADDRESS, MESSAGE)
            VALUES ('{$docHash}', '{$eventType}', " . ($phone ? "'{$phone}'" : "NULL") . ", '{$ip}', '{$safeMsg}')
        ");
    }
}
