<?php

namespace KPLab\API\V2\Interfaces\Http\Auth;

final class AuthScheme implements AuthSchemeInterface
{
    public function __construct(private string $value, private string $key = "") {}

    public function headerValue(): string
    {
        return ($this->key == "") ?  $this->value : $this->key . " ". $this->value;
    }
}