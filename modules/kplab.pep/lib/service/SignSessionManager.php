<?php

namespace Kplab\Pep\Service;

use \Bitrix\Main\Application;

class SignSessionManager
{
    private array $record;

    public static function loadByHash(string $hash): ?self
    {
        $conn = Application::getConnection();
        $data = $conn->query("SELECT * FROM kplab_pep_sgn_log WHERE DOCUMENT_HASH = '{$hash}' LIMIT 1")->fetch();

        if (!$data) {
            return null;
        }

        return new self($data);
    }

    private function __construct(array $record)
    {
        $this->record = $record;
    }

    public function getClient(): array
    {
        return [
            'full_name' => $this->record['FULL_NAME'],
            'email'     => $this->record['EMAIL'],
            'phone'     => $this->record['PHONE'],
        ];
    }

    public function getFilePath(): string
    {
        return \CFile::GetPath($this->record['DOCUMENT_ID']);
    }

    public function isSigned(): bool
    {
        return !empty($this->record['SIGNED_AT']);
    }

    public function getCompanyId(): int
    {
        return (int)$this->record['COMPANY_ID'];
    }

    public function getFileId(): int
    {
        return (int)$this->record['DOCUMENT_ID'];
    }

    public function getHash(): string
    {
        return $this->record['DOCUMENT_HASH'];
    }
}
