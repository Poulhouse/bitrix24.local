<?php

namespace Kplab\Pep\Controller;

use Bitrix\Main\Context;
use Bitrix\Main\Application;
use Kplab\Pep\Service\SignSessionManager;

class SignedController
{
    public static function handle()
    {
        $request = Context::getCurrent()->getRequest();
        $docHash = $request->getQuery('d');
        $skipSms = $request->getQuery('smssend') === '1';

        if (!$docHash) {
            echo "Документ не найден.";
            return;
        }

        $conn = Application::getConnection();
        $record = $conn->query("
            SELECT * FROM kplab_pep_sgn_log WHERE DOCUMENT_HASH = '{$docHash}' LIMIT 1
        ")->fetch();

        if (!$record) {
            echo "Информация о документе не найдена.";
            return;
        }

        $filePath = \CFile::GetPath($record['DOCUMENT_ID']);
        $session = SignSessionManager::loadByHash($docHash);
        if (!$session) {
            echo "Информация о документе не найдена.";
            return;
        }

        $data = [
            'documentHash' => $session->getHash(),
            'companyId'    => $session->getCompanyId(),
            'fileId'       => $session->getFileId(),
            'filePath'     => $session->getFilePath(),
            'client'       => $session->getClient(),
            'isSigned'     => $session->isSigned(),
            'skipSms'      => $skipSms,
            'session'      => &$_SESSION,
            'request'      => $request,
        ];

        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kplab.pep/templates/signed.php';
    }
}
