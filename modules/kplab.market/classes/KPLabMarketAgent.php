<?php
use Bitrix\Main\Type\DateTime;
use KPLab\Market\Service\App;

class KPLabMarketAgent
{
    public static function refreshTokens()
    {
        $class = App::getEntity();
        $now = new DateTime();
        $apps = $class::getList([
            'filter' => [
                '<UF_EXPIRES_AT' => $now->add('+1 hour'),
                '=UF_STATUS' => 'ACTIVE'
            ]
        ]);

        while ($app = $apps->fetch())
        {
            // здесь логика обновления токена (через Bitrix24 OAuth)
            // например, file_get_contents("https://oauth.bitrix.info/oauth/token?...")

            $class::update($app['ID'], [
                'UF_EXPIRES_AT' => new DateTime('+1 hour'),
                'UF_LOG' => 'Token refreshed ' . date('Y-m-d H:i:s')
            ]);
        }

        return __METHOD__ . '();';
    }
}
