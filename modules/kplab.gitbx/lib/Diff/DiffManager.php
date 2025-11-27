<?php

namespace KPLab\GitBx\Diff;

use KPLab\GitBx\Diff\Providers\DiffProviderInterface;
use KPLab\GitBx\Util\Json;
use KPLab\GitBx\Util\Logger;
use Bitrix\Main\SystemException;

class DiffManager
{
    /** @var DiffProviderInterface[] */
    protected array $providers = [];

    public function __construct()
    {
        // Регистрируем провайдеры диффа
        $this->registerProvider(new Providers\CrmFieldsProvider());
    }

    public function registerProvider(DiffProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * Сравнить два снапшота (test vs prod).
     */
    public function compare(array $testSnapshot, array $prodSnapshot): array
    {
        unset($testSnapshot['_meta'], $prodSnapshot['_meta']);

        Logger::info("DiffManager started compare");

        $diff = [];

        $keys = array_unique(array_merge(
            array_keys($testSnapshot),
            array_keys($prodSnapshot)
        ));

        foreach ($keys as $key) {

            $provider = $this->providers[$key] ?? null;

            if (!$provider) {
                Logger::warning("No diff provider for section '{$key}'");
                continue;
            }

            try {
                $normTest = $provider->normalize($testSnapshot[$key] ?? []);
                $normProd = $provider->normalize($prodSnapshot[$key] ?? []);

                $sectionDiff = $provider->diff($normTest, $normProd);

                if (!empty($sectionDiff)) {
                    $diff[$key] = $sectionDiff;
                }

            } catch (\Throwable $e) {
                Logger::error("Diff provider '{$key}' failed", [
                    'exception' => $e->getMessage()
                ]);

                $diff[$key] = ['_error' => $e->getMessage()];
            }
        }

        Logger::info("DiffManager finished", [
            'sections' => count($diff)
        ]);

        return $diff;
    }
}
