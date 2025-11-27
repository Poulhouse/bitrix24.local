<?php

namespace KPLab\GitBx\Migration\Actions;

class Registry
{
    protected static array $map = [
        'crm_fields' => CrmFieldsActions::class,
        // 'pipelines' => PipelinesActions::class,
        // 'robots'    => RobotsActions::class,
    ];

    protected static array $instances = [];

    public static function get(string $provider): ?ActionsProviderInterface
    {
        if (!isset(self::$map[$provider])) {
            return null;
        }

        if (!isset(self::$instances[$provider])) {
            $class = self::$map[$provider];
            self::$instances[$provider] = new $class();
        }

        return self::$instances[$provider];
    }
}