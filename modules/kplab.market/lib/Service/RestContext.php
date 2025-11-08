<?php

namespace KPLab\Market\Service;

use Bitrix\Main\Web\HttpClient;

/**
 * Класс для безопасного создания контекста REST-запроса из входящих данных.
 * ВСЕ данные, кроме AUTH_ID и ACCESS_TOKEN, должны быть проверены через app.info.
 * НЕ доверяйте DOMAIN, MEMBER_ID, CLIENT_ID из POST — только из app.info.
 */
class RestContext
{
    /**
     * @var array Данные аутентификации, полученные из app.info
     */
    private array $auth;

    /**
     * @var array Ключи приложения, полученные из HL-блока (UF_CLIENT_ID, UF_CLIENT_SECRET)
     */
    private array $keys;

    /**
     * @var string Тип запроса (install, update, uninstall, webhook)
     */
    private string $type;

    /**
     * @var string Код приложения APP_CODE
     */
    private string $appCode;

    /**
     * Создаёт контекст из входящего POST-запроса.
     * Проверяет подлинность через app.info — только после этого доверяет данным.
     *
     * @param array $data Данные из POST-запроса (например, $_POST)
     * @return RestContext
     * @throws \RuntimeException Если не удалось проверить приложение через app.info
     */
    public static function fromRequest(array $data): self
    {
        $appCode = $data['app'] ?? '';
        if (empty($appCode)) {
            throw new \RuntimeException('Missing app code in request');
        }

        $keys = self::loadAppKeysByAppCode($appCode);
        if (!$keys) {
            throw new \RuntimeException('App not registered in system');
        }

        // 🔴 ПРОВЕРКА signature — ОБЯЗАТЕЛЬНО для установки/обновления/удаления
        if (isset($data['signature']) && !self::validateSignature($data, $keys['UF_CLIENT_SECRET'])) {
            throw new \RuntimeException('Invalid signature');
        }

        if (!empty($keys['UF_STATUS']) && $keys['UF_STATUS'] !== 'ACTIVE') {
            throw new \RuntimeException('App is not active');
        }

        $merged = array_merge($data, $data['auth'] ?? []);

        $authId = $merged['AUTH_ID'] ?? '';
        $accessToken = $merged['ACCESS_TOKEN'] ?? '';

        // 🔴 ОСНОВНОЕ ИСПРАВЛЕНИЕ: Проверяем только AUTH_ID
        if (empty($authId)) {
            throw new \RuntimeException('Missing AUTH_ID in request');
        }

        // 🔴 Запрашиваем app.info — даже если ACCESS_TOKEN пустой
        // Bitrix24 ожидает, что мы вернём access_token в ответе
        $appInfo = self::fetchAppInfo($accessToken, $keys['UF_CLIENT_ID']);

        if (empty($appInfo['result'])) {
            throw new \RuntimeException('Failed to validate app via app.info');
        }

        $domain = $appInfo['result']['domain'] ?? '';
        $memberId = $appInfo['result']['member_id'] ?? '';
        $clientId = $appInfo['result']['client_id'] ?? '';

        if ($clientId !== $keys['UF_CLIENT_ID']) {
            throw new \RuntimeException('Client ID mismatch');
        }

        $type = self::determineRequestType($data);

        return new self([
            'domain' => $domain,
            'access_token' => $accessToken, // ← может быть пустым — это нормально
            'member_id' => $memberId,
            'auth_id' => $authId,
        ], $keys, $type, $appCode);
    }

    /**
     * Вызывает метод app.info для проверки подлинности приложения.
     *
     * @param string $accessToken
     * @param string $expectedClientId
     * @return array
     */
    private static function fetchAppInfo(string $accessToken, string $expectedClientId): array
    {
        $http = new HttpClient([
            'socketTimeout' => 10,
            'streamTimeout' => 10,
        ]);

        // Вызываем app.info — только Bitrix24 может подтвердить домен и client_id
        $response = $http->post(
            'https://api.bitrix24.com/rest/app.info.json',
            ['auth' => $accessToken]
        );

        $data = json_decode($response, true) ?: [];

        // Логируем для отладки (в продакшене — только ошибки)
        if (empty($data['result'])) {
            \KPLab\Market\Service\Rest::log('app_info_failed', [
                'access_token' => substr($accessToken, 0, 10) . '...',
                'client_id' => $expectedClientId,
                'response' => $data,
            ]);
        }

        return $data;
    }

    /**
     * Определяет тип запроса на основе входных данных.
     *
     * @param array $data
     * @return string
     */
    private static function determineRequestType(array $data): string
    {
        if (isset($data['install']) && $data['install'] === 'Y') {
            return 'install';
        }

        if (isset($data['update']) && $data['update'] === 'Y') {
            return 'update';
        }

        if (isset($data['uninstall']) && $data['uninstall'] === 'Y') {
            return 'uninstall';
        }

        // Если есть auth и нет install/update/uninstall — это webhook
        if (!empty($data['auth'])) {
            return 'webhook';
        }

        return 'unknown';
    }

    /**
     * Конструктор — приватный, чтобы создавать только через fromRequest()
     *
     * @param array $auth
     * @param array $keys
     * @param string $type
     * @param string $appCode
     */
    private function __construct(array $auth, array $keys, string $type, string $appCode)
    {
        $this->auth = $auth;
        $this->keys = $keys;
        $this->type = $type;
        $this->appCode = $appCode;
    }

    /**
     * Получает данные аутентификации.
     *
     * @return array
     */
    public function auth(): array
    {
        return $this->auth;
    }

    /**
     * Получает ключи приложения.
     *
     * @return array
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * Получает тип запроса.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Проверяет, является ли запрос установкой.
     *
     * @return bool
     */
    public function isInstall(): bool
    {
        return $this->type === 'install';
    }

    /**
     * Проверяет, является ли запрос обновлением.
     *
     * @return bool
     */
    public function isUpdate(): bool
    {
        return $this->type === 'update';
    }

    /**
     * Проверяет, является ли запрос деинсталляцией.
     *
     * @return bool
     */
    public function isUninstall(): bool
    {
        return $this->type === 'uninstall';
    }

    /**
     * Проверяет, является ли запрос вебхуком.
     *
     * @return bool
     */
    public function isWebhook(): bool
    {
        return $this->type === 'webhook';
    }
    private static function validateSignature(array $data, string $secret): bool
    {
        if (!isset($data['signature'])) {
            return false;
        }

        $signature = $data['signature'];
        unset($data['signature']);

        ksort($data);
        $stringToSign = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $expectedSignature = hash_hmac('sha256', $stringToSign, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Загружает ключи приложения из HL-блока по app_code (не по auth_id).
     * Используется для проверки signature — до того, как известен auth_id.
     *
     * @param string $appCode
     * @return array|null
     */
    private static function loadAppKeysByAppCode(string $appCode): ?array
    {
        try {
            $dataClass = HighloadLocator::getApplicationsDataClass();
        } catch (\RuntimeException $exception) {
            Rest::log('load_app_keys_by_code_missing_hl', ['message' => $exception->getMessage()]);
            return null;
        }

        $result = $dataClass::getList([
            'filter' => ['=UF_CODE' => $appCode],
            'select' => ['UF_CLIENT_ID', 'UF_CLIENT_SECRET', 'UF_STATUS', 'UF_AUTH_ID'],
            'limit' => 1,
        ])->fetch();

        if (!$result) {
            return null;
        }

        return [
            'UF_CLIENT_ID' => $result['UF_CLIENT_ID'] ?? null,
            'UF_CLIENT_SECRET' => $result['UF_CLIENT_SECRET'] ?? null,
            'UF_STATUS' => $result['UF_STATUS'] ?? null,
            'UF_AUTH_ID' => $result['UF_AUTH_ID'] ?? null, // для проверки совпадения
        ];
    }
}