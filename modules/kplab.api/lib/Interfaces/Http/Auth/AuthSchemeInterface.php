<?php
namespace KPLab\API\V2\Interfaces\Http\Auth;

interface AuthSchemeInterface
{
    /** Вернёт строку, полностью готовую для заголовка */
    public function headerValue(): string;
}