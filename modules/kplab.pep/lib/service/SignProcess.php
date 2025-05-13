<?php

namespace Kplab\Pep\Service;

use Bitrix\Main\Loader;
use Kplab\Pep\Logger;

class SignProcess
{
    public static function handleConfirmation(array $client, string $docHash, string $code, string $filePath, int $fileId, int $companyId): ?int
    {
        if (!SmsService::verifyCode($client['phone'], $code, $docHash)) {
            Logger::log($docHash, 'sms_check', 'Неверный код: ' . $code, $client['phone']);
            return null;
        }

        $signedAt = date('Y-m-d H:i:s');
        $client['signed_at'] = $signedAt;

        $sourcePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        $signedPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/signed_' . $fileId . '.pdf';

        $result = PdfGenerator::generate($sourcePath, $client, $signedPath);

        $fileArray = \CFile::MakeFileArray($signedPath);
        $fileArray['name'] = 'signed_document.pdf';
        $newFileId = \CFile::SaveFile($fileArray, "pep_signed");

        SignResultSaver::save(
            $docHash,
            $result['signature'],
            $signedAt,
            $newFileId,
            $_SERVER['REMOTE_ADDR'],
            $client['phone']
        );

        Logger::log($docHash, 'sign_success', 'Документ подписан.', $client['phone']);

        // Привязка к компании
        if (Loader::includeModule('crm')) {
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $companyItem = $factoryCompany->getItem($companyId);
            $companyItem->setFromCompatibleData([
                'UF_CRM_6433D98DC980E' => [\CFile::MakeFileArray($newFileId)],
            ]);
            $operation = $factoryCompany->getUpdateOperation($companyItem);
            $operation->disableAllChecks();
            $operation->launch();
        }

        return $newFileId;
    }
    public static function resendCode(array $client, string $docHash): bool
    {
        $phone = $client['phone'];

        if (!SmsService::canSend($phone, $docHash)) {
            return false;
        }

        SmsService::sendCode($phone, $docHash);
        \Kplab\Pep\Logger::log($docHash, 'sms_send_repeat', 'Ручная отправка кода повторно', $phone);

        return true;
    }
}
