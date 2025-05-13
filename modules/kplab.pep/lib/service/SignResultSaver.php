<?php

namespace Kplab\Pep\Service;

use Bitrix\Main\Application;
use Kplab\Pep\Logger;

class SignResultSaver
{
    public static function save(string $documentHash, string $signature, string $signedAt, int $fileId, string $ip, string $phone = null): void
    {
        $conn = Application::getConnection();

        $conn->queryExecute("
            UPDATE kplab_pep_sgn_log
            SET 
                SIGNED_AT = '{$signedAt}',
                SIGNATURE = '{$signature}',
                IP_ADDRESS = '{$ip}',
                DOCUMENT_ID = {$fileId}
            WHERE DOCUMENT_HASH = '{$documentHash}'
            LIMIT 1
        ");

        Logger::log($documentHash, 'sign_saved', 'Сохранено: ' . $signature, $phone);
    }
}
