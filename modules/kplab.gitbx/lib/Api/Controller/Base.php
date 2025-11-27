<?php

namespace KPLab\GitBx\Api\Controller;

use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;

use Bitrix\Main\Context;
use Bitrix\Main\Web\HttpClient;

abstract class Base extends \Bitrix\Main\Engine\Controller
{
    protected function getDefaultPreFilters(): array
    {

        return [
            new \Bitrix\Main\Engine\ActionFilter\Cors(
                origin: ModuleSettings::remoteUrl(),
                credentials: false
            ),

            // Аутентификация будет второй
            new \KPLab\GitBx\Api\ActionFilter\Authentication(),
        ];
    }


    protected function success(array $data = []): array
    {
        return [
            'status' => 'success',
            'data'   => $data,
            'errors' => null,
        ];
    }

    protected function fail(string $message, string $code = 'error'): array
    {
        return [
            'status' => 'error',
            'data'   => null,
            'errors' => [
                ['message' => $message, 'code' => $code],
            ],
        ];
    }
    protected function postToRemote($target, string $controllerName, string $actionName)
    {
        $methodName = 'kplab:gitbx.' . $controllerName . '.' . str_replace('Action', "", $actionName);
        $url   = ModuleSettings::remoteUrl();
        $token = ModuleSettings::remoteToken();

        if (!$url || !$token) {
            return $this->fail('Remote URL/token not configured', 'remote_config_missing');
        }

        $http = new HttpClient([
            'socketTimeout' => ModuleSettings::apiTimeout() ?: 30,
            'streamTimeout' => ModuleSettings::apiTimeout() ?: 30,
        ]);

        $http->setHeader('Authorization', 'Bearer ' . $token, true);
        $http->setHeader('Content-Type', 'application/json', true);

        $remoteUrl = rtrim($url, '/') . "/bitrix/services/main/ajax.php?action={$methodName}";

        Logger::info('Snapshot.get remote', [
            'target'    => $target,
            'remoteUrl' => $remoteUrl,
        ]);

        // Всегда просим "local" snapshot на удалённом портале
        return $http->post($remoteUrl, Json::encode([
            'target' => 'local',
        ]));
    }
}
