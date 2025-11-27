<?php
namespace KPLab\GitBx\Api\Controller;

use KPLab\GitBx\Api\Auth;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Logger;

class Ping extends Base
{
    /**
     * Основная логика: Ping.run
     */
    public function runAction(): array
    {
        // Проверяем токен "remote_token" из настроек модуля
        if (!Auth::checkBearerToken()) {
            http_response_code(401);

            return [
                'status' => 'error',
                'message' => 'invalid_token'
            ];
        }

        return [
            'env' => ModuleSettings::get('env'),
            'ts'  => time(),
            'iso' => date('c'),
        ];
    }
}
