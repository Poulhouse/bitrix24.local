<?php

namespace Kplab\Pep\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

class SmsService
{
    public static function sendCode(string $phone, string $docHash): bool
    {
        $code = rand(100000, 999999);
        $ttl = (int) Option::get('kplab.pep', 'pep_sms_lifetime', '10');
        $expiresAt = (new \DateTime("+{$ttl} minutes"))->format('Y-m-d H:i:s');

        $conn = Application::getConnection();
        $conn->queryExecute("
            INSERT INTO kplab_pep_sms_codes (PHONE, DOCUMENT_HASH, SMS_CODE, EXPIRES_AT)
            VALUES ('{$phone}', '{$docHash}', '{$code}', '{$expiresAt}')
        ");

        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/upload/pep_sms_log.txt', "[{$phone}] {$code}\n", FILE_APPEND);

        return true;
    }

    public static function verifyCode(string $phone, string $code, string $docHash): bool
    {
        $conn = Application::getConnection();
        $row = $conn->query("
            SELECT ID FROM kplab_pep_sms_codes
            WHERE PHONE = '{$phone}' AND SMS_CODE = '{$code}' AND DOCUMENT_HASH = '{$docHash}'
            AND IS_USED = 0 AND EXPIRES_AT >= NOW()
            ORDER BY ID DESC LIMIT 1
        ")->fetch();

        if ($row) {
            $conn->queryExecute("UPDATE kplab_pep_sms_codes SET IS_USED = 1 WHERE ID = {$row['ID']}");
            return true;
        }

        return false;
    }

    public static function canSend(string $phone, string $docHash, int $cooldownSeconds = 60): bool
    {
        $conn = \Bitrix\Main\Application::getConnection();
        $row = $conn->query("
        SELECT CREATED_AT 
        FROM kplab_pep_sms_codes 
        WHERE PHONE = '{$phone}' AND DOCUMENT_HASH = '{$docHash}' 
        ORDER BY ID DESC LIMIT 1
    ")->fetch();

        if (!$row) return true;

        $lastSent = strtotime($row['CREATED_AT']);
        return (time() - $lastSent) >= $cooldownSeconds;
    }
}
