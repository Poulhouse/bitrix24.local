<?php

namespace KPLab\GitBx\Snapshot;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\SystemException;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Snapshot\Providers\SnapshotProviderInterface;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;

class SnapshotManager
{
    /** @var SnapshotProviderInterface[] */
    protected array $providers = [];

    public function __construct()
    {
        $this->registerDefaultProviders();
    }

    /**
     * Регистрируем провайдеры снимков.
     */
    protected function registerDefaultProviders(): void
    {
        // Пока только CRM-поля
        $this->providers['crm_fields'] = new Providers\CrmFieldsProvider();

        // Примеры на будущее:
        // $this->providers['pipelines'] = new Providers\PipelinesProvider();
        // $this->providers['robots']    = new Providers\RobotsProvider();
    }

    public function addProvider(string $key, SnapshotProviderInterface $provider): void
    {
        $this->providers[$key] = $provider;
    }

    /**
     * Построить snapshot как массив.
     */
    public function buildArray(): array
    {
        $result = [
            '_meta' => [
                'generated_at' => date('c'),
                'version' => 1,
                'provider_count' => count($this->providers)
            ]
        ];

        foreach ($this->providers as $key => $provider) {
            $data = $provider->collect();

            // Исправляем пустые UF-объекты: [] → {}
            if ($key === 'crm_fields') {
                foreach ($data as $entity => $fields) {
                    if (!is_array($fields) || array_values($fields) === $fields) {
                        $data[$entity] = [];  // сериализуется как {}
                    }
                }
            }

            if (method_exists($provider, 'normalize')) {
                $data = $provider->normalize($data);
            }

            $result[$key] = $data;
        }

        return $result;
    }

    /**
     * Сохранение snapshot в файле.
     */
    public function saveSnapshot(?string $code = null): string
    {
        $dir = $this->resolvePath(ModuleSettings::get('dir_snapshots'));

        if (!Directory::isDirectoryExists($dir)) {
            Directory::createDirectory($dir);
        }

        if ($code === null) {
            $code = date('Ymd_His');
        }

        $path = $dir . "snapshot_{$code}.json";

        File::putFileContents($path, Json::encode($this->buildArray()));

        Logger::info("Snapshot saved", ['file' => $path]);

        return $path;
    }

    protected function resolvePath(string $path): string
    {
        $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        return $root . '/' . trim($path, '/') . '/';
    }
}
