<?php
namespace KPLab\GitBx\Api;

use Bitrix\Main\Context;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Logger;

class Auth
{
    public static function getBearerToken(): ?string
    {
        $request = Context::getCurrent()->getRequest();
        $server  = Context::getCurrent()->getServer();

        // 1. Нормальный путь: Authorization header
        $header = (string)$request->getHeader('Authorization');
        if ($header !== '') {
            if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
                return trim($m[1]);
            }
        }

        // 2. Для кривых серверов — REMOTE_USER
        $remoteUser = (string)$server->get('REMOTE_USER');
        if ($remoteUser !== '' && stripos($remoteUser, 'Bearer ') === 0) {
            return trim(substr($remoteUser, 7));
        }

        return null;
    }


    public static function getQueryToken(): ?string
    {
        $request = Context::getCurrent()->getRequest();
        $token   = (string) $request->getQuery('token');

        $token = trim($token);

        return $token !== '' ? $token : null;
    }

    public static function getIncomingToken(): ?string
    {
        $bearer = self::getBearerToken();
        $query  = self::getQueryToken();

        $incoming = $bearer ?: $query;

        return $incoming ?: null;
    }

    public static function isLocalRequest(): bool
    {
        $server = Context::getCurrent()->getServer();

        $host = strtolower(trim($server->getHttpHost()));               // например: testcrm.ru:443
        $currentHost = strtolower(trim($_SERVER['SERVER_NAME'] ?? '')); // например: testcrm.ru

        // Удаляем порты
        $host = preg_replace('/:\d+$/', '', $host);
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);

        Logger::info("AUTH isLocalRequest FIXED", [
            'host' => $host,
            'currentHost' => $currentHost,
        ]);

        return $host === $currentHost;
    }



    public static function checkBearerToken(): bool
    {
        // Если запрос локальный — токен не нужен
        if (self::isLocalRequest()) {
            return true;
        }

        $incoming = self::getIncomingToken();
        $expected = trim((string) ModuleSettings::currentToken());

        if ($incoming === null || $incoming === '') {
            Logger::warning('AUTH checkBearerToken: empty incoming token', [
                'expected' => $expected ?: '(empty)',
            ]);
            return false;
        }

        if ($expected === '') {
            Logger::warning('AUTH checkBearerToken: empty expected token', [
                'incoming' => $incoming,
            ]);
            return false;
        }

        $ok = hash_equals($expected, $incoming);

        if (!$ok) {
            Logger::warning('AUTH checkBearerToken: token mismatch', [
                'incoming' => $incoming,
                'expected' => $expected,
            ]);
        } else {
            Logger::info('AUTH checkBearerToken: OK', [
                'incoming' => $incoming,
            ]);
        }

        return $ok;
    }
}
