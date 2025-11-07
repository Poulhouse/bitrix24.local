<?php
namespace KPLab\Market\Repository;

interface InstallationRepositoryInterface
{
    public function findByMemberAndApp(string $memberId, string $appCode): ?array;
    public function upsert(string $memberId, string $appCode, array $fields): bool;
    public function updateToken(string $memberId, array $tokens): bool;
    public function updateStatus(string $memberId, string $appCode, string $status): bool;
    public function getActiveInstallations(): array;
}