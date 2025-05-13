<?php
namespace KPLab\API\V2\Interfaces\Http\Auth;

final class BasicScheme implements AuthSchemeInterface
{
    public function __construct(private string $username, private string $password) {}
    public function headerValue(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }
}