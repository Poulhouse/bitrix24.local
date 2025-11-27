<?php

namespace KPLab\GitBx\Migration;

use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\SystemException;
use KPLab\GitBx\Config\ModuleSettings;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;

class MigrationManager
{
    public const TYPE_SNAPSHOT_DIFF = 'snapshot_diff';

    /**
     * Построение миграционного пакета из diff.
     */
    public function buildPackage(array $diff): array
    {
        $operations = $this->buildOperations($diff);

        $pkg = [
            '_meta' => [
                'created_at' => date('c'),
                'type'       => self::TYPE_SNAPSHOT_DIFF,
                'version'    => 1,
                'operation_count' => count($operations)
            ],
            'operations' => $operations
        ];

        Logger::info("Migration package created", [
            'operations' => count($operations)
        ]);

        return $pkg;
    }

    /**
     * Конвертация diff → operations
     */
    protected function buildOperations(array $diff): array
    {
        $result = [];

        foreach ($diff as $section => $sectionDiff) {

            // Структура crm_fields сейчас такая:
            // [
            //   ENTITY => [
            //      added => [...]
            //      removed => [...]
            //      changed => [...]
            //   ]
            // ]

            foreach ($sectionDiff as $entity => $changes) {

                // added
                foreach ($changes['added'] ?? [] as $code => $payload) {
                    $result[] = [
                        'provider' => $section,
                        'action'   => 'create',
                        'entity'   => $entity,
                        'code'     => $code,
                        'payload'  => $payload
                    ];
                }

                // removed
                foreach ($changes['removed'] ?? [] as $code => $payload) {
                    $result[] = [
                        'provider' => $section,
                        'action'   => 'delete',
                        'entity'   => $entity,
                        'code'     => $code,
                        'payload'  => $payload
                    ];
                }

                // changed
                foreach ($changes['changed'] ?? [] as $code => $payload) {
                    $result[] = [
                        'provider' => $section,
                        'action'   => 'update',
                        'entity'   => $entity,
                        'code'     => $code,
                        'payload'  => $payload
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Сохранение пакета в файл.
     */
    public function savePackage(array $package, ?string $code = null): string
    {
        $dir = $this->resolvePath(ModuleSettings::get('dir_migrations'));

        if (!Directory::isDirectoryExists($dir)) {
            Directory::createDirectory($dir);
        }

        if ($code === null) {
            $code = date('Ymd_His');
        }

        $path = $dir . "migration_{$code}.json";

        File::putFileContents($path, Json::encode($package));

        return $path;
    }

    protected function resolvePath(string $path): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/' . trim($path, '/') . '/';
    }
}
