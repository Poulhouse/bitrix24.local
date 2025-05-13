<?php
namespace KPLab\API\V2\Interfaces\Http\Auth;

final class ApiKeyScheme implements AuthSchemeInterface
{
    public function __construct(private string $apiKey) {}
    public function headerValue(): string
    {
        return 'ApiKey ' . $this->apiKey;
        // ▸ если у сервера формат Bearer/Token/Key XYZ — просто поменяйте префикс
    }
}