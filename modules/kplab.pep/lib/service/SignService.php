<?php

namespace Kplab\Pep\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class SignService
{
    public function startSigning(int $companyId, int $fileId, bool $skipSms = false): ?string
    {
        if (!Loader::includeModule('crm')) {
            return null;
        }

        // Получить данные компании

        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $companyItem = $factoryCompany -> getItem($companyId);
        $company = $companyItem->getData();

        // Получаем EMAIL и PHONE через CCrmFieldMulti
        $email = self::getMultiFieldValue('EMAIL', 'WORK', 'COMPANY', $companyId);
        $phone = self::getMultiFieldValue('PHONE', 'MOBILE', 'COMPANY', $companyId);

        if (!$company || empty($email) || empty($phone)) {
            return null;
        }

        $client = [
            'full_name' => $company['TITLE'],
            'email'     => $email,
            'phone'     => preg_replace('/\D+/', '', $phone)
        ];

        // Получить путь к файлу
        $file = \CFile::MakeFileArray($fileId);
        if (!file_exists($file['tmp_name'])) {
            return null;
        }

        $filePath = $file['tmp_name'];
        $docHash = hash_file('sha256', $filePath);

        // Проверка: запись уже есть?
        $conn = Application::getConnection();
        $exists = $conn->query("
            SELECT ID FROM kplab_pep_sgn_log 
            WHERE DOCUMENT_HASH = '{$docHash}' AND COMPANY_ID = {$companyId}
            LIMIT 1
        ")->fetch();

        if (!$exists) {
            $conn->queryExecute("
                INSERT INTO kplab_pep_sgn_log 
                (COMPANY_ID, DOCUMENT_ID, DOCUMENT_HASH, FULL_NAME, EMAIL, PHONE, SIGNED_AT, IP_ADDRESS)
                VALUES 
                ({$companyId}, {$fileId}, '{$docHash}', '{$client['full_name']}', '{$client['email']}', '{$client['phone']}', NULL, '')
            ");
        }

        // Отправка СМС
        if (!$skipSms) {
            SmsService::sendCode($client['phone'], $docHash);
        }

        // Вернуть ссылку
        $baseUrl = Option::get('kplab.pep', 'pep_verify_url', 'https://testcrm.seller-capital.ru/pep/verify/');
        $signedUrl = str_replace('verify', 'signed', $baseUrl);
        $signedUrl .= '?d=' . urlencode($docHash);
        if ($skipSms) {
            $signedUrl .= '&smssend=1';
        }

        return $signedUrl;
    }

    private static function getMultiFieldValue(string $type, string $valueType, string $entityType, int $entityId): ?string
    {
        $result = \CCrmFieldMulti::GetList([], [
            'ENTITY_ID' => $entityType,
            'ELEMENT_ID' => $entityId,
            'TYPE_ID' => $type,
            'VALUE_TYPE' => $valueType
        ]);

        if ($row = $result->Fetch()) {
            return $row['VALUE'];
        }

        return null;
    }
}
