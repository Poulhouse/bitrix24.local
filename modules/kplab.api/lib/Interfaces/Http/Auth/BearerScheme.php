<?php

namespace KPLab\API\V2\Interfaces\Http\Auth;

final class BearerScheme  implements AuthSchemeInterface
{
    public function __construct(private string $apiKey) {}
    public function headerValue(): string
    {
        return 'Bearer ' . $this->apiKey;
        // ▸ если у сервера формат Bearer/Token/Key XYZ — просто поменяйте префикс
    }

}