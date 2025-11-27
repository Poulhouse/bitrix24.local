<?php

namespace KPLab\GitBx\Api\Controller;

use Bitrix\Main\Context;
use Bitrix\Main\Web\HttpClient;
use KPLab\GitBx\Api\Auth;
use KPLab\GitBx\Snapshot\SnapshotManager;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;

class Snapshot extends Base
{
    /**
     * Получение snapshot локально или удалённо.
     *
     * target:
     *   - local (по умолчанию) — собирать snapshot текущего портала
     *   - test / prod          — запрос к удалённому порталу через remote_url/remote_token
     *
     * Поддерживает:
     *   - внутренние вызовы через BX.ajax.runAction('kplab:gitbx.Snapshot.get', {target: 'local'})
     *   - внешние REST-вызовы с JSON body {"target":"..."} и Bearer-токеном.
     */
    public function getAction(): array
    {
        // Локальные запросы (из того же хоста) могут не требовать токен — логика в Auth/Authentication.
        if (!Auth::isLocalRequest() && !Auth::checkBearerToken()) {
            http_response_code(401);
            return $this->fail('invalid_token');
        }

        $className = (new \ReflectionClass(__CLASS__))->getShortName();

        $request = Context::getCurrent()->getRequest();

        // JSON-body (для внешних REST-запросов)
        $rawBody = file_get_contents('php://input');
        $json    = json_decode($rawBody, true);
        $json    = is_array($json) ? $json : [];

        $target =
            $request->getPost('target')
                ?: $request->getQuery('target')
                ?: ($json['target'] ?? 'local');

        Logger::info('Snapshot.get', [
            'target' => $target,
            'class'    => $className,
            'method' => __FUNCTION__,
        ]);

        /**
         * CASE 1: Локальный snapshot
         */
        if ($target === 'local') {

            return (new SnapshotManager())->buildArray();
            //return $this->success($data)['data'];
        }

        /**
         * CASE 2: Удалённый snapshot (test/prod)
         *
         * По сути сейчас это один remote_url/remote_token.
         * В дальнейшем можно развести remote для test/prod отдельно.
         */
        if ($target === 'test' || $target === 'prod') {

            $rawRemote = $this->postToRemote($target, $className,__FUNCTION__);

            if (!$rawRemote) {
                return $this->fail('Remote portal did not respond', 'remote_no_response');
            }

            $jsonRemote = Json::decode($rawRemote);

            if (!is_array($jsonRemote)) {
                return $this->fail('Invalid response from remote', 'invalid_remote_response');
            }

            return $jsonRemote;
        }

        return $this->fail("Unknown target: {$target}", 'invalid_target');
    }

    /**
     * Проверка наличия файлов snapshot на текущем портале.
     * Используется UI для отображения кнопок "Скачать snapshot ...".
     */
    public function statusAction(): array
    {
        $dir = rtrim(ModuleSettings::dirSnapshots(), '/');

        return [
            'test' => file_exists($dir . '/snapshot_test.json'),
            'prod' => file_exists($dir . '/snapshot_prod.json'),
        ];
    }
}
