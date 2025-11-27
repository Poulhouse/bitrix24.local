<?php

namespace KPLab\GitBx\Api\Controller;

use Bitrix\Main\Context;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Web\HttpClient;
use KPLab\GitBx\Api\Auth;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Diff\DiffManager;
use KPLab\GitBx\Migration\MigrationManager;
use KPLab\GitBx\Snapshot\SnapshotManager;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;

class Migration extends Base
{
    /**
     * Применение migration-пакета.
     * Используется удалённым порталом (например, PROD → TEST).
     * Ожидает JSON-пакет в body.
     */
    public function applyAction(): array
    {
        // Внешний вызов защищён Authentication prefilter'ом,
        // но оставим явную проверку (если вдруг фильтр снимут).
        if (!Auth::checkBearerToken()) {
            http_response_code(401);

            return [
                'status'  => 'error',
                'message' => 'invalid_token',
            ];
        }

        $payload = $this->readJsonBody();

        if (!is_array($payload) || empty($payload)) {
            return $this->fail('Invalid or empty JSON payload', 'invalid_json');
        }

        Logger::info('Migration.apply received package', [
            'operations' => isset($payload['operations']) && is_array($payload['operations'])
                ? count($payload['operations'])
                : null,
        ]);

        $runner = new \KPLab\GitBx\Migration\Scripts\Runner();
        $result = $runner->run($payload);

        Logger::info('Migration.apply finished', [
            'status' => 'ok',
        ]);

        return $this->success($result);
    }

    /**
     * Актуализация тестового портала (TEST ← PROD).
     *
     * Работает пошагово в зависимости от mode (для UI и внешнего API):
     *   fetch_test_snapshot  — только шаг 1
     *   fetch_prod_snapshot  — шаги 1–2
     *   calculate_diff       — шаги 1–3
     *   build_package        — шаги 1–4
     *   send_to_test         — шаги 1–5
     *   full                 — все шаги (по умолчанию)
     *
     * Поддерживает:
     *   - внутренний вызов через BX.ajax.runAction('kplab:gitbx.Migration.exporttotest', {mode: ...})
     *   - внешний вызов с JSON body { "mode": "..." } и Bearer-токеном.
     */
    public function exporttotestAction(): array
    {
        // Для внешних вызовов — проверка токена.
        // Предполагаем, что локальные запросы уже "разруливаются" внутри Auth/Authentication.
        if (!Auth::checkBearerToken()) {
            http_response_code(401);

            return [
                'status'  => 'error',
                'message' => 'invalid_token',
            ];
        }

        $request = $this->getRequest();

        // Читаем JSON-body
        $payload = $this->readJsonBody();
        if (!is_array($payload)) {
            $payload = [];
        }

        // Если пришёл mode через обычный POST (runAction), перезапишем
        $postMode = $request->getPost('mode');
        if ($postMode !== null && $postMode !== '') {
            $payload['mode'] = $postMode;
        }

        $mode = $payload['mode'] ?? 'full';

        $modeMaxStepMap = [
            'fetch_test_snapshot' => 1,
            'fetch_prod_snapshot' => 2,
            'calculate_diff'      => 3,
            'build_package'       => 4,
            'send_to_test'        => 5,
            'full'                => 5,
        ];
        $maxStep = $modeMaxStepMap[$mode] ?? 5;

        $steps   = [];
        $timeout = ModuleSettings::apiTimeout();
        $testUrl = ModuleSettings::remoteUrl();
        $testTok = ModuleSettings::remoteToken();

        $snapDir = rtrim(ModuleSettings::dirSnapshots(), '/');
        if (!Directory::isDirectoryExists($snapDir)) {
            Directory::createDirectory($snapDir);
        }

        try {
            Logger::info('=== EXPORT TO TEST STARTED ===', ['mode' => $mode]);

            $currentStep = 0;
            $testSnapshot = [];
            $prodSnapshot = [];

            /**
             * ----------------------------------------
             * STEP 1: Получаем snapshot TEST (удалённый портал)
             * ----------------------------------------
             */
            $currentStep++;
            $steps[] = ['step' => 'fetch_test_snapshot', 'ok' => false];

            $http = new HttpClient([
                'socketTimeout' => $timeout,
                'streamTimeout' => $timeout,
            ]);
            $http->setHeader('Authorization', 'Bearer ' . $testTok, true);

            $remoteSnapshotUrl = rtrim($testUrl, '/') .
                '/bitrix/services/main/ajax.php?action=kplab:gitbx.Snapshot.get';

            Logger::info('ExportToTest: request remote snapshot TEST', [
                'url' => $remoteSnapshotUrl,
            ]);

            // Запрашиваем на удалённом портале его "локальный" snapshot
            $rawTestSnapshot = $http->post($remoteSnapshotUrl, Json::encode([
                'target' => 'local',
            ]));

            if (!$rawTestSnapshot) {
                throw new \Exception('TEST portal did not return snapshot');
            }

            // Сохраняем сырой ответ целиком (как есть)
            $testSnapshotFile = $snapDir . '/snapshot_test.json';
            \Bitrix\Main\IO\File::putFileContents($testSnapshotFile, $rawTestSnapshot);

            $jsonTest = Json::decode($rawTestSnapshot);
            if (isset($jsonTest['data']['_meta'])) {
                // уже нормальный snapshot
                $testSnapshot = $jsonTest['data'];
            }
            elseif (isset($jsonTest['data']['data']['_meta'])) {
                $testSnapshot = $jsonTest['data']['data'];
            } else {
                throw new \Exception("Invalid snapshot structure");
            }

            $steps[$currentStep - 1]['ok']   = true;
            $steps[$currentStep - 1]['info'] = ['bytes' => strlen($rawTestSnapshot)];

            Logger::info('Test snapshot received', [
                'size' => strlen($rawTestSnapshot),
                'file' => $testSnapshotFile,
            ]);

            if ($currentStep >= $maxStep) {
                return $this->success([
                    'steps'  => $steps,
                    'result' => [
                        'mode'    => $mode,
                        'status'  => 'partial',
                        'maxStep' => $maxStep,
                    ],
                ]);
            }

            /**
             * ----------------------------------------
             * STEP 2: Локально собираем snapshot PROD
             * ----------------------------------------
             */
            $currentStep++;
            $steps[] = ['step' => 'fetch_prod_snapshot', 'ok' => false];

            $prodSnapshot = (new SnapshotManager())->buildArray();
            $prodSnapshotFile = $snapDir . '/snapshot_prod.json';

            \Bitrix\Main\IO\File::putFileContents(
                $prodSnapshotFile,
                Json::encode($prodSnapshot)
            );

            $steps[$currentStep - 1]['ok']   = true;
            $steps[$currentStep - 1]['info'] = ['sections' => count($prodSnapshot)];

            Logger::info('Prod snapshot generated', [
                'sections' => count($prodSnapshot),
                'file'     => $prodSnapshotFile,
            ]);

            if ($currentStep >= $maxStep) {
                return $this->success([
                    'steps'  => $steps,
                    'result' => [
                        'mode'    => $mode,
                        'status'  => 'partial',
                        'maxStep' => $maxStep,
                    ],
                ]);
            }

            /**
             * ----------------------------------------
             * STEP 3: diff TEST → PROD
             * ----------------------------------------
             */
            $currentStep++;
            $steps[] = ['step' => 'calculate_diff', 'ok' => false];

            $diff = (new DiffManager())->compare($testSnapshot, $prodSnapshot);
            $steps[$currentStep - 1]['ok']   = true;
            $steps[$currentStep - 1]['info'] = ['sections' => count($diff)];

            Logger::info('Diff calculated', ['sections' => count($diff)]);

            if ($currentStep >= $maxStep) {
                return $this->success([
                    'steps'  => $steps,
                    'result' => [
                        'mode'    => $mode,
                        'status'  => 'partial',
                        'maxStep' => $maxStep,
                    ],
                ]);
            }

            /**
             * ----------------------------------------
             * STEP 4: собираем Migration Package
             * ----------------------------------------
             */
            $currentStep++;
            $steps[] = ['step' => 'build_package', 'ok' => false];

            $migrationManager = new MigrationManager();
            $package          = $migrationManager->buildPackage($diff);

            $steps[$currentStep - 1]['ok']   = true;
            $steps[$currentStep - 1]['info'] = [
                'operations' => isset($package['operations']) && is_array($package['operations'])
                    ? count($package['operations'])
                    : 0,
            ];

            Logger::info('Migration package built', [
                'operations' => $steps[$currentStep - 1]['info']['operations'],
            ]);

            if ($currentStep >= $maxStep) {
                return $this->success([
                    'steps'  => $steps,
                    'result' => [
                        'mode'       => $mode,
                        'status'     => 'partial',
                        'maxStep'    => $maxStep,
                        'operations' => $steps[$currentStep - 1]['info']['operations'],
                    ],
                ]);
            }

            /**
             * ----------------------------------------
             * STEP 5: отправляем пакет на тестовый портал
             * ----------------------------------------
             */
            $currentStep++;
            $steps[] = ['step' => 'send_to_test', 'ok' => false];

            $http2 = new HttpClient([
                'socketTimeout' => $timeout,
                'streamTimeout' => $timeout,
            ]);
            $http2->setHeader('Authorization', 'Bearer ' . $testTok, true);
            $http2->setHeader('Content-Type', 'application/json', true);

            $applyUrl = rtrim($testUrl, '/') .
                '/bitrix/services/main/ajax.php?action=kplab:gitbx.Migration.apply';

            Logger::info('ExportToTest: send package to TEST', [
                'url'        => $applyUrl,
                'operations' => $steps[$currentStep - 2]['info']['operations'] ?? null,
            ]);

            $applyJson = $http2->post(
                $applyUrl,
                Json::encode($package)
            );

            if (!$applyJson) {
                throw new \Exception('TEST portal did not return migration result');
            }

            $applyResult = Json::decode($applyJson);

            $steps[$currentStep - 1]['ok']   = true;
            $steps[$currentStep - 1]['info'] = ['raw_bytes' => strlen($applyJson)];

            Logger::info('Migration applied on TEST', [
                'response_bytes' => strlen($applyJson),
            ]);

            Logger::info('=== EXPORT TO TEST COMPLETED ===', [
                'mode' => $mode,
            ]);

            return $this->success([
                'steps'  => $steps,
                'result' => $applyResult,
            ]);

        } catch (\Throwable $e) {

            Logger::error('Export to test failed', [
                'exception' => $e->getMessage(),
            ]);

            if (!empty($steps)) {
                $last = count($steps) - 1;
                if ($steps[$last]['ok'] === false) {
                    $steps[$last]['info'] = ['error' => $e->getMessage()];
                }
            }

            return $this->fail($e->getMessage(), 'export_failed');
        }
    }

    /**
     * Утилита для чтения JSON-тела.
     * Работает и для внешних REST-вызовов, и для runAction (если фронт пошлёт JSON).
     */
    protected function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
