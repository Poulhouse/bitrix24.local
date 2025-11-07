<?php
use PHPUnit\Framework\TestCase;
use KPLab\Market\Repository\InstallationRepository;
use KPLab\Market\Logger\FileLogger;

class InstallationRepositoryTest extends TestCase
{
    public function testUpsertCreatesNewRecord(): void
    {
        $repo = new InstallationRepository();
        $result = $repo->upsert('member_123', 'myapp', [
            'UF_DOMAIN' => 'test.bitrix24.ru',
            'UF_ACCESS_TOKEN' => 'abc',
            'UF_REFRESH_TOKEN' => 'xyz',
            'UF_STATUS' => 'ACTIVE',
        ]);

        $this->assertTrue($result);
    }
}