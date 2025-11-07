<?php
namespace KPLab\Market\Agent;

use Bitrix\Main\Type\DateTime;
use KPLab\Market\Service\AppStore;
use KPLab\Market\Service\AppKeys;
use KPLab\Market\Service\RestContext;
use KPLab\Market\Service\Rest;

class RefreshTokens
{
    /**
     * Основной цикл агента.
     * Возвращает строку вызова, чтобы Bitrix запустил его снова.
     */
    public static function run(): string
    {
        $list = AppStore::getExpiring(7200); // токен истекает менее чем через 2 часа
        if (empty($list)) {
            Rest::log('agent_refresh', 'Нет токенов для обновления');
            return __METHOD__ . '();';
        }

        foreach ($list as $row) {
            try {
                $memberId = $row['UF_MEMBER_ID'];
                $appCode  = $row['UF_APP_CODE'];

                // создаём контекст
                $ctx = new RestContext($memberId, $appCode);
                $rest = new Rest($ctx);

                // выполняем обновление
                $rest->refreshToken();
                Rest::log('agent_refresh_success', [
                    'member_id' => $memberId,
                    'app_code'  => $appCode,
                ]);
            } catch (\Throwable $e) {
                Rest::log('agent_refresh_error', [
                    'member_id' => $row['UF_MEMBER_ID'] ?? '?',
                    'app_code'  => $row['UF_APP_CODE'] ?? '?',
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return __METHOD__ . '();';
    }
}
