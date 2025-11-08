<?php
namespace KPLab\Market\Repository;

use KPLab\Market\Service\HighloadLocator;

class InstallationRepository implements InstallationRepositoryInterface
{
    private ?string $hlClass = null;
    private array $cache = [];

    public function findByMemberAndApp(string $memberId, string $appCode): ?array
    {
        $key = $memberId . ':' . $appCode;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $class = $this->getEntityClass();
        $row = $class::getList([
            'filter' => [
                '=UF_MEMBER_ID' => $memberId,
                '=UF_APP_CODE' => $appCode,
            ],
            'limit' => 1,
        ])->fetch();

        return $this->cache[$key] = $row ?: null;
    }

    public function upsert(string $memberId, string $appCode, array $fields): bool
    {
        $existing = $this->findByMemberAndApp($memberId, $appCode);

        $class = $this->getEntityClass();

        if ($existing) {
            $result = $class::update($existing['ID'], $fields);
        } else {
            $fields['UF_MEMBER_ID'] = $memberId;
            $fields['UF_APP_CODE'] = $appCode;
            $result = $class::add($fields);
        }

        if (!$result->isSuccess()) {
            \KPLab\Market\Service\Rest::log('upsert_error', [
                'member_id' => $memberId,
                'app_code' => $appCode,
                'fields'    => $fields,
                'errors'    => $result->getErrorMessages()
            ]);
            return false;
        }

        // Сброс кэша
        $this->cache[$memberId . ':' . $appCode] = $result->getId() ? $this->findByMemberAndApp($memberId, $appCode) : null;

        return true;
    }

    public function updateToken(string $memberId, string $appCode, array $tokens): bool
    {
        $existing = $this->findByMemberAndApp($memberId, $appCode);
        if (!$existing) return false;

        $class = $this->getEntityClass();
        $result = $class::update($existing['ID'], $tokens);

        return $result->isSuccess();
    }

    public function updateStatus(string $memberId, string $appCode, string $status): bool
    {
        $existing = $this->findByMemberAndApp($memberId, $appCode);
        if (!$existing) return false;

        $class = $this->getEntityClass();
        $result = $class::update($existing['ID'], ['UF_STATUS' => $status]);

        return $result->isSuccess();
    }

    public function getActiveInstallations(): array
    {
        $class = $this->getEntityClass();
        return $class::getList([
            'filter' => ['=UF_STATUS' => 'ACTIVE'],
            'select' => ['UF_ID', 'UF_MEMBER_ID', 'UF_APP_CODE', 'UF_ACCESS_TOKEN', 'UF_REFRESH_TOKEN', 'UF_DOMAIN', 'UF_EXPIRES_AT']
        ])->fetchAll();
    }

    private function getEntityClass(): string
    {
        if ($this->hlClass) return $this->hlClass;

        $this->hlClass = HighloadLocator::getInstallationsDataClass();

        return $this->hlClass;
    }
}
