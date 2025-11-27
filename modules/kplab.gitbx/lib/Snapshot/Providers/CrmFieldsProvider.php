<?php

namespace KPLab\GitBx\Snapshot\Providers;

use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use KPLab\GitBx\Util\Arr;

class CrmFieldsProvider implements SnapshotProviderInterface
{
    /** @var string[] */
    protected array $entityMap = [
        'lead'    => 'CRM_LEAD',
        'deal'    => 'CRM_DEAL',
        'contact' => 'CRM_CONTACT',
        'company' => 'CRM_COMPANY',
    ];

    public function __construct()
    {
        Loader::includeModule('crm');
    }

    public function collect(): array
    {
        $result = [];

        foreach ($this->entityMap as $key => $entityId) {
            $result[$key] = $this->collectForEntity($entityId);
        }

        return $result;
    }

    /**
     * Снять snapshot пользовательских полей для одной сущности CRM.
     */
    protected function collectForEntity(string $entityId): array
    {
        $fields = [];

        $res = UserFieldTable::getList([
            'filter' => [
                '=ENTITY_ID' => $entityId,
            ],
            'order' => [
                'FIELD_NAME' => 'ASC',
            ],
        ]);

        while ($row = $res->fetch()) {
            // Нормализуем только то, что нужно для миграций
            $fields[$row['FIELD_NAME']] = [
                'FIELD_NAME'    => $row['FIELD_NAME'],
                'XML_ID'        => $row['XML_ID'],
                'USER_TYPE_ID'  => $row['USER_TYPE_ID'],
                'MULTIPLE'      => $row['MULTIPLE'],
                'MANDATORY'     => $row['MANDATORY'],
                'SHOW_FILTER'   => $row['SHOW_FILTER'],
                'SHOW_IN_LIST'  => $row['SHOW_IN_LIST'],
                'EDIT_IN_LIST'  => $row['EDIT_IN_LIST'],
                'IS_SEARCHABLE' => $row['IS_SEARCHABLE'],
                'SETTINGS'      => $this->normalizeSettings($row['SETTINGS'] ?? []),
            ];
        }

        // Сортируем по FIELD_NAME для стабильности snapshot
        return Arr::sortByKey($fields);
    }

    /**
     * Нормализация настроек, чтобы snapshot был стабильным.
     */
    protected function normalizeSettings($settings): array
    {
        if (!is_array($settings)) {
            $settings = [];
        }

        ksort($settings);

        return $settings;
    }
}