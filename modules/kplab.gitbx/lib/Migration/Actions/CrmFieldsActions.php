<?php

namespace KPLab\GitBx\Migration\Actions;

use Bitrix\Main\Loader;
use KPLab\GitBx\Util\Logger;

class CrmFieldsActions implements ActionsProviderInterface
{
    public function __construct()
    {
        Loader::includeModule('crm');
    }

    public function execute(string $action, array $op): mixed
    {
        return match ($action) {
            'create' => $this->createField($op),
            'update' => $this->updateField($op),
            'delete' => $this->deleteField($op),
            default   => throw new \RuntimeException("Unknown FSM action: {$action}")
        };
    }

    protected function createField(array $op): array
    {
        $entity = $op['entity'];
        $code   = $op['code'];
        $data   = $op['payload'];

        $ufe = new \CUserTypeEntity();

        $field = [
            'ENTITY_ID' => $entity,
            'FIELD_NAME' => $code,
            'USER_TYPE_ID' => $data['USER_TYPE_ID'],
            'XML_ID' => $data['XML_ID'] ?? '',
            'EDIT_FORM_LABEL' => $data['EDIT_FORM_LABEL'],
            'LIST_COLUMN_LABEL' => $data['LIST_COLUMN_LABEL'],
            'SETTINGS' => $data['SETTINGS'] ?? []
        ];

        $id = $ufe->Add($field);

        if (!$id) {
            throw new \RuntimeException("Failed to create UF {$entity}/{$code}");
        }

        $this->syncEnum($id, $data);

        return ['id' => $id];
    }

    protected function updateField(array $op): array
    {
        $entity = $op['entity'];
        $code   = $op['code'];
        $changes = $op['payload'];

        $ufe = new \CUserTypeEntity();

        $res = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entity,
            'FIELD_NAME' => $code
        ]);

        if (!($field = $res->Fetch())) {
            throw new \RuntimeException("UF not found: {$entity}/{$code}");
        }

        $update = [];

        foreach ($changes as $k => $v) {
            if ($k === 'SETTINGS') continue;
            $update[$k] = $v['new'];
        }

        if (!empty($update)) {
            $ufe->Update($field['ID'], $update);
        }

        if (isset($changes['SETTINGS'])) {
            $settings = $field['SETTINGS'];
            foreach ($changes['SETTINGS'] as $k => $change) {
                if ($k !== 'ENUM') {
                    $settings[$k] = $change['new'];
                }
            }

            $ufe->Update($field['ID'], ['SETTINGS' => $settings]);

            // ENUM
            if (isset($changes['SETTINGS']['ENUM'])) {
                $this->syncEnum($field['ID'], ['SETTINGS' => ['ENUM' => $changes['SETTINGS']['ENUM']]]);
            }
        }

        return ['updated' => true];
    }

    protected function deleteField(array $op): array
    {
        $entity = $op['entity'];
        $code   = $op['code'];

        $res = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entity,
            'FIELD_NAME' => $code
        ]);

        if (!($field = $res->Fetch())) {
            return ['skip' => true];
        }

        $ufe = new \CUserTypeEntity();
        $ufe->Delete($field['ID']);

        return ['deleted' => true];
    }

    protected function syncEnum($fieldId, array $data): void
    {
        if (!isset($data['SETTINGS']['ENUM'])) {
            return;
        }

        $enum = $data['SETTINGS']['ENUM'];

        $ufe = new \CUserFieldEnum();

        $values = [];
        $i = 10;
        foreach ($enum as $xml => $item) {
            $values['n' . $i] = [
                'VALUE'  => $item['VALUE'],
                'XML_ID' => $xml,
                'SORT'   => $i * 10
            ];
            $i++;
        }

        $ufe->SetEnumValues($fieldId, $values);
    }
}
