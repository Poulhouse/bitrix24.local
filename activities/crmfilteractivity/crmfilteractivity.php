<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Bizproc\Activity\BaseActivity;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\ContactTable;
use Bitrix\Crm\CompanyTable;
use Bitrix\Crm\LeadTable;
use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG__ACTIVITY", $_SERVER['DOCUMENT_ROOT'] . "/local/activities/crmfilteractivity/log.log");

class CBPCrmFilterActivity extends BaseActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            "Title" => "",
            "EntityType" => null, // Тип сущности CRM
            "FilterFields" => null,
            "IDs" => null,
            "FilterFieldsKey" => null,
            "FilterFieldsCondition" => null,
            "FilterFieldsValue" => null,
            "FilterFieldsList" => "N",
            "ElementsId" => null
        ];

        $this->SetPropertiesTypes([
            'ElementsId' => [
                'Name' => ['ru' => 'IDs', 'en' => 'IDs'],
                'Type' => 'string',
                'Multiple' => 'Y',
            ]
        ]);
    }

    public static function getFileName(): string
    {
        return __FILE__; // Возвращает путь к текущему файлу активити
    }

    public function ReInitialize()
    {
        parent::ReInitialize();

        $this->ElementsId = null;
        $this->FilterFieldsList = "N";
    }

    public function Execute()
    {
        if (!Loader::includeModule('crm')) {
            Logs\File::AddMessage('CRM module not loaded', 'Error', LOG__ACTIVITY);
            return CBPActivityExecutionStatus::Closed;
        }

        Logs\File::AddMessage('CRM module loaded', 'Info', LOG__ACTIVITY);

        $this->ElementsId = null;
        $documentType = $this->DocumentType;
        $filterFieldsKey = $this->FilterFieldsKey;
        $filterFieldsValue = $this->FilterFieldsValue;
        $entityType = $this->EntityType;

        Logs\File::AddMessage($documentType,"documentType", LOG__ACTIVITY);
        if (!$documentType)
        {

            $this->WriteToTrackingService("1", 0, CBPTrackingType::Error);
            $this->WriteToTrackingService(GetMessage('BPGLDA_ERROR_DT'), 0, CBPTrackingType::Error);

            return CBPActivityExecutionStatus::Closed;
        }

        $filter = [];

        // Логируем фильтры и сущность
        Logs\File::AddMessage($filterFieldsKey, 'Filter Keys', LOG__ACTIVITY);
        Logs\File::AddMessage($filterFieldsValue, 'Filter Values', LOG__ACTIVITY);
        Logs\File::AddMessage($entityType, 'Entity Type', LOG__ACTIVITY);

        if (!empty($filterFieldsKey) && !empty($filterFieldsValue)) {
            foreach ($filterFieldsKey as $keyIndex => $key) {
                $value = $filterFieldsValue[$keyIndex];
                if (!empty($key) && !empty($value)) {
                    $filter[$key] = $value;
                }
            }
        }

        // Логируем итоговый фильтр
        Logs\File::AddMessage($filter, 'Final Filter', LOG__ACTIVITY);

        // Определяем таблицу в зависимости от сущности
        switch ($entityType) {
            case 'DEAL':
                $crmTable = DealTable::class;
                break;
            case 'CONTACT':
                $crmTable = ContactTable::class;
                break;
            case 'COMPANY':
                $crmTable = CompanyTable::class;
                break;
            case 'LEAD':
                $crmTable = LeadTable::class;
                break;
            default:
                Logs\File::AddMessage('Unknown entity type', 'Error', LOG__ACTIVITY);
                return CBPActivityExecutionStatus::Closed;
        }

        // Выполняем запрос к выбранной таблице
        try {
            $elements = $crmTable::getList([
                'filter' => $filter,
                'select' => ['ID'],
            ]);
        } catch (Exception $e) {
            Logs\File::AddMessage($e->getMessage(), 'DB Error', LOG__ACTIVITY);
            return CBPActivityExecutionStatus::Closed;
        }

        $this->ElementsId = [];
        while ($element = $elements->fetch()) {
            $this->ElementsId[] = $element['ID'];
        }

        // Логируем результат
        Logs\File::AddMessage($this->ElementsId, 'Resulting IDs', LOG__ACTIVITY);

        return CBPActivityExecutionStatus::Closed;
    }

    public static function ValidateProperties($testProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];

        try
        {
            CBPHelper::ParseDocumentId($testProperties['DocumentType']);
        }
        catch (Exception $e)
        {
            $errors[] = [
                "code" => "NotExist",
                "parameter" => "DocumentType",
                "message" => GetMessage("BPGLDA_ERROR_DT"),
            ];
        }
        return array_merge($errors, parent::ValidateProperties($testProperties, $user));
    }

    public static function GetPropertiesDialog($paramDocumentType, $activityName, $arWorkflowTemplate,
                                               $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = '', $popupWindow = null, $siteId = '')
    {
        if (!CModule::IncludeModule('lists'))
        {
            return null;
        }

        if (!is_array($arCurrentValues))
        {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (!empty($arCurrentActivity["Properties"]['FilterFieldsKey']))
            {
                $arCurrentValues['filter_fields_key'] = $arCurrentActivity["Properties"]['FilterFieldsKey'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsCondition']))
            {
                $arCurrentValues['filter_fields_condition'] = $arCurrentActivity["Properties"]['FilterFieldsCondition'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsValue']))
            {
                $arCurrentValues['filter_fields_value'] = $arCurrentActivity["Properties"]['FilterFieldsValue'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsList']))
            {
                $arCurrentValues['filter_fields_list'] = $arCurrentActivity["Properties"]['FilterFieldsList'];
            }
            if (!empty($arCurrentActivity["Properties"]['EntityType'])) {
                // Указываем entity_type для работы с типами сущностей
                $arCurrentValues['entity_type'] = $arCurrentActivity["Properties"]['EntityType'];
            }
            if (!empty($arCurrentActivity["Properties"]['IDs']))
            {
                $arCurrentValues['ids'] = $arCurrentActivity["Properties"]['IDs'];
            }
        }

        $documentType = (!empty($arCurrentValues['lists_document_type']))
            ? explode('@', $arCurrentValues['lists_document_type']) : null;

        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, [
            'documentType' => $paramDocumentType,
            'activityName' => $activityName,
            'workflowTemplate' => $arWorkflowTemplate,
            'workflowParameters' => $arWorkflowParameters,
            'workflowVariables' => $arWorkflowVariables,
            'currentValues' => $arCurrentValues,
            'formName' => $formName,
            'siteId' => $siteId,  // Добавляем параметр siteId
        ]);

        $dialog->setMap(static::getPropertiesMap($paramDocumentType, ['listsDocumentType' => $documentType]));

        return $dialog;
    }

    // Получаем значения для интерфейса активити
    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    ): bool
    {
        if (!CModule::IncludeModule('crm')) {
            return false;
        }

        $runtime = CBPRuntime::GetRuntime();

        $arProperties = [
            'EntityType' => $arCurrentValues['entity_type'],  // Получаем значение сущности
            "FilterFields" => $arCurrentValues['filter_fields'],
            "IDs" => $arCurrentValues['ids'],
            'FilterFieldsKey' => $arCurrentValues['filter_fields_key'],
            'FilterFieldsCondition' => $arCurrentValues['filter_fields_condition'],
            'FilterFieldsValue' => $arCurrentValues['filter_fields_value'],
            'FilterFieldsList' => $arCurrentValues['filter_fields_list'],
        ];

        // Валидация
        $validationErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if (!empty($validationErrors)) {
            $arErrors = array_merge($arErrors, $validationErrors);
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $currentActivity['Properties'] = $arProperties;

        return true;
    }

    protected static function getPropertiesMap(array $documentType, array $context = []): array
    {
        // Определяем условия фильтрации
        $conditionList = [
            "=" => "равно",
            "!=" => "не равно",
            ">" => "больше",
            ">=" => "больше, либо равно",
            "<" => "меньше",
            "<=" => "меньше, либо равно",
            "%" => "содержит",
            "!%" => "не содержит"
        ];

        // Список типов CRM-сущностей
        $entityTypeList = [
            'LEAD' => "Лиды",
            'DEAL' => "Сделки",
            'CONTACT' => "Контакты",
            'COMPANY' => "Компании"
        ];

        return [
            'FilterFields' => [
                'Name' => "Фильтр по документу",
                'FieldName' => 'filter_fields',
            ],
            'EntityType' => [
                'Name' => "Типы CRM",
                'FieldName' => 'entity_type',
                'Type' => 'select',
                'Required' => true,
                'Multiple' => false,
                'Options' => $entityTypeList,
            ],
            'IDs' => [
                'Name' => 'IDs',
                'FieldName' => 'ids',
                'Type' => 'string',
                'Multiple' => true,
            ],
            'FilterFieldsKey' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filter_fields_key',
                'Type' => 'select',
                'Required' => true,
                'Multiple' => false,
                'Options' => [], // Поля будут загружаться динамически
            ],
            'FilterFieldsCondition' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filter_fields_condition',
                'Type' => 'select',
                'Required' => true,
                'Multiple' => false,
                'Options' => $conditionList,
            ],
            'FilterFieldsValue' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filter_fields_value',
                'Type' => 'string',
                'Required' => true
            ],
            'FilterFieldsList' => [
                'Name' => "Фильтруем по значению из списка?",
                'FieldName' => 'filter_fields_list',
                'Type' => 'bool',
                'Required' => true,
                'Default' => 'N'
            ],
        ];
    }
    public static function getAjaxResponse($request)
    {
        if (!empty($request['lists_document_type']) && !empty($request['form_name']))
        {
            $documentType = explode('@', $request['lists_document_type']);

            $options = [];
            foreach (self::getDocumentFieldsOptions($documentType) as $value => $text)
            {
                $options[] = ['value' => $value, 'text' => $text];
            }

            return ['options' => $options];
        }

        return null;
    }

    private static function getDocumentTypeField()
    {
        $field = [
            'Name' => GetMessage('BPGLDA_DOC_TYPE'),
            'FieldName' => 'lists_document_type',
            'Type' => 'select',
            'Required' => true,
        ];

        $options = $groups = [];

        $processesType = COption::getOptionString("lists", "livefeed_iblock_type_id", 'bitrix_processes');
        $groups = [
            'lists' => ['name' => GetMessage('BPGLDA_DT_LISTS'), 'items' => []],
            $processesType => ['name' => GetMessage('BPGLDA_DT_PROCESSES'), 'items' => []],
            'lists_socnet' => ['name' => GetMessage('BPGLDA_DT_LISTS_SOCNET'), 'items' => []],
        ];
        // other lists
        $typesResult = CLists::GetIBlockTypes();
        while ($typeRow = $typesResult->fetch())
        {
            $groups[$typeRow['IBLOCK_TYPE_ID']] = ['name' => $typeRow['NAME'], 'items' => []];
        }

        $iterator = CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], [
            'ACTIVE' => 'Y',
            'TYPE' => array_keys($groups),
            'CHECK_PERMISSIONS' => 'N',
        ]);

        while ($row = $iterator->fetch())
        {
            $value = 'lists@' . ($row['IBLOCK_TYPE_ID'] === $processesType ? 'BizprocDocument'
                    : 'Bitrix\Lists\BizprocDocumentLists') . '@iblock_' . $row['ID'];
            $name = '[' . $row['LID'] . '] ' . $row['NAME'];

            $options[$value] = $name;
            $groups[$row['IBLOCK_TYPE_ID']]['items'][$value] = $name;
        }

        $field['Options'] = $options;
        $field['Settings'] = ['Groups' => $groups];

        return $field;
    }

    private static function getDocumentFieldsOptions(array $documentType)
    {
        $documentService = CBPRuntime::GetRuntime(true)->GetService("DocumentService");
        $fields = $documentService->GetDocumentFields($documentType);

        $listFields = static::getVisibleFieldsList(mb_substr($documentType[2], 7));

        $options = [];

        foreach ($fields as $fieldKey => $fieldValue)
        {
            if (in_array($fieldKey, $listFields))
            {
                $options[$fieldKey] = $fieldValue['Name'];
            }
        }

        return $options;
    }

    private static function getVisibleFieldsList($iblockId)
    {
        $list = new CList($iblockId);
        $listFields = $list->getFields();
        $result = [];
        foreach ($listFields as $key => $field)
        {
            if (mb_strpos($key, 'PROPERTY_') === 0)
            {
                if (!empty($field['CODE']))
                {
                    $key = 'PROPERTY_' . $field['CODE'];
                }
            }
            $result[] = $key;
            $result[] = $key . '_PRINTABLE';
            $result[] = $key . '_printable';
        }
        array_unshift($result, 'ID');
        return $result;
    }

    private static function buildFieldsMap(array $documentType, $fields)
    {
        $documentService = CBPRuntime::GetRuntime()->GetService("DocumentService");
        $documentFields = $documentService->GetDocumentFields($documentType);

        $listFields = static::getVisibleFieldsList(mb_substr($documentType[2], 7));
        $map = [];
        foreach ($fields as $field)
        {
            if (in_array($field, $listFields) && isset($documentFields[$field]))
            {
                $map[$field] = \Bitrix\Bizproc\FieldType::normalizeProperty($documentFields[$field]);
            }
        }
        return $map;
    }
}
