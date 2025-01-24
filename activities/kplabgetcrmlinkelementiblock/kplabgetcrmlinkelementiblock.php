<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Bizproc\Activity\PropertiesDialog;
use Bitrix\Bizproc\FieldType;
use Bitrix\Crm\Service\Container;
use KPLab\Logs;

define("LOG__ACTIVITY", $_SERVER['DOCUMENT_ROOT']."/local/activities/kplabgetcrmlinkelementiblock/log.log");

class CBPKPLabGetCRMLinkElementIblock extends CBPActivity {
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            "Title" => "",
            "DocumentType" => null,
            "FilterFields" => null,
            "IDs" => null,
            "FilterFieldsKey" => null,
            "FilterFieldsCondition" => null,
            "FilterFieldsValue" => null,
            "FilterFieldsList" => "N",
            //"FieldsMap" => null,

            //return ElementsId
            "ElementsId" => null
        ];

        $this->SetPropertiesTypes([
            'ElementsId' => [
                'Name' => [
                    'ru' => 'IDs',
                    'en' => 'IDs'
                ],
                'Type' => 'string',
                'Multiple' => 'Y',
            ]
        ]);

    }

    public function ReInitialize()
    {
        parent::ReInitialize();

        $this->ElementsId = null;
        $this->FilterFieldsList = "N";
    }

    public function Execute()
    {
        if (!\Bitrix\Main\Loader::includeModule('lists'))
        {
            return CBPActivityExecutionStatus::Closed;
        }
        $this->ElementsId = null;

        //$ElementsId = [];
        $documentType = $this->DocumentType;
        $filterFieldsKey = $this->FilterFieldsKey;
        $filterFieldsCondition = $this->FilterFieldsCondition;
        $filterFieldsValue = $this->FilterFieldsValue;
        $filterFieldsList = $this->FilterFieldsList;

        \KPLab\Logs\File::AddMessage($documentType,"documentType", LOG__ACTIVITY);
        if (!$documentType)
        {

            $this->WriteToTrackingService("1", 0, CBPTrackingType::Error);
            $this->WriteToTrackingService(GetMessage('BPGLDA_ERROR_DT'), 0, CBPTrackingType::Error);

            return CBPActivityExecutionStatus::Closed;
        }

        $arSelect = ["ID"];
        $arFilter = ["IBLOCK_ID"=>mb_substr($documentType[2], 7)];

        $this->WriteToTrackingService("filterFieldsList: {$filterFieldsList}", 0, CBPTrackingType::Error);
        if($filterFieldsList == 'Y') {
            $filterFieldsKey = $filterFieldsKey."_VALUE";
        } elseif($filterFieldsList == 'N') {
            $filterFieldsKey = $filterFieldsKey."";
        }
        if($filterFieldsCondition == "=") {
            $arFilter[$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == "!=") {
            $arFilter["!".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == "%") {
            $arFilter["%".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == "!%") {
            $arFilter["!%".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == ">") {
            $arFilter[">".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == ">=") {
            $arFilter[">=".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == "<") {
            $arFilter["<".$filterFieldsKey] = $filterFieldsValue;
        }
        elseif($filterFieldsCondition == "<=") {
            $arFilter["<=".$filterFieldsKey] = $filterFieldsValue;
        }

        ///////////////////////////
        $this->WriteToTrackingService("УСЛОВИЕ: {$filterFieldsKey} {$filterFieldsCondition} {$filterFieldsValue}", 0, CBPTrackingType::Error);
        ///////////////////////////

        $res = CIBlockElement::GetList([], $arFilter, false, [], $arSelect);

        while($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            $elementId = $arFields['ID'];
            //$this->WriteToTrackingService("{$elementId}", 0, CBPTrackingType::Error);
            $ElementsId[] = $elementId;
            //$this->arProperties['ElementsId'] = $ElementsId;

            //$documentId = [$documentType[0], $documentType[1], $elementId];
            //$documentService = $this->workflow->GetService("DocumentService");
            //$document = $documentService->GetDocument($documentId, $documentType);
            //$_document = json_encode($document);
            //$this->WriteToTrackingService("{$_document}", 0, CBPTrackingType::Error);

/*
            foreach ($map as $id => $field)
            {
                $field = json_encode($field);
                //$_documentId = json_encode($documentId);
                //$this->WriteToTrackingService("field {$field}", 0, CBPTrackingType::Error);
                //$this->WriteToTrackingService("id {$id}", 0, CBPTrackingType::Error);
                //$this->WriteToTrackingService("documentId {$_documentId}", 0, CBPTrackingType::Error);

                //$values[$id] = $document[$id];
                $this->arProperties['ElementsId'] = $ElementsId;
            }
*/
            //array_push($ElementsId, $arFields['ID']);
            /*

            $documentService = $this->workflow->GetService("DocumentService");

            $this->logDebug($elementId, $documentType);

            try { $realDocumentType = $documentService->GetDocumentType($documentId); }
            catch (Exception $e){  }


            if (!$realDocumentType || $realDocumentType !== $documentType)
            {
                $this->WriteToTrackingService("2", 0, CBPTrackingType::Error);
                $this->WriteToTrackingService(GetMessage('BPGLDA_ERROR_DT'), 0, CBPTrackingType::Error);
                return CBPActivityExecutionStatus::Closed;
            }

            $document = $documentService->GetDocument($documentId, $documentType);

            if (!$document || !is_array($map))
            {
                $this->WriteToTrackingService(GetMessage('BPGLDA_ERROR_EMPTY_DOCUMENT'), 0, CBPTrackingType::Error);
                return CBPActivityExecutionStatus::Closed;
            }

            //$this->SetPropertiesTypes($map);

            $values = [];

            foreach ($map as $id => $field)
            {
                $values[$id] = $document[$id];
                $this->arProperties[$id] = $document[$id];
            }

            $this->logDebugFields($map, $values);
            */
            //$i++;
        }


        /*$this->SetPropertiesTypes([
            [
                'ElementsId' => [
                    'Name' => [
                        'ru' => 'IDs',
                        'en' => 'IDs'
                    ],
                    'Type' => 'string',
                    'Multiple' => 'Y',
                    'Default' => null
                ]
            ]
        ]);*/

        $_ElementsId = json_encode($ElementsId);
        $this->WriteToTrackingService("_ElementsId {$_ElementsId}", 0, CBPTrackingType::Error);
        $this->ElementsId = $ElementsId;

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
/*
        if (empty($testProperties['ElementId']))
        {
            $errors[] = [
                "code" => "NotExist",
                "parameter" => "ElementId",
                "message" => GetMessage("BPGLDA_ERROR_ELEMENT_ID"),
            ];
        }
*/
        /*
        if (empty($testProperties['Fields']))
        {
            $errors[] = ["code" => "NotExist", "parameter" => "Fields", "message" => GetMessage("BPGLDA_ERROR_FIELDS")];
        }
*/
        return array_merge($errors, parent::ValidateProperties($testProperties, $user));
    }

    public static function GetPropertiesDialog($paramDocumentType, $activityName, $arWorkflowTemplate,
                                               $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "", $popupWindow = null)
    {
        if (!CModule::IncludeModule('lists'))
        {
            return null;
        }

        if (!is_array($arCurrentValues))
        {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            /*if (!empty($arCurrentActivity["Properties"]['ElementId']))
            {
                $arCurrentValues['lists_element_id'] = $arCurrentActivity["Properties"]['ElementId'];
            }*/
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsKey']))
            {
                $arCurrentValues['filterFieldsKey'] = $arCurrentActivity["Properties"]['FilterFieldsKey'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsCondition']))
            {
                $arCurrentValues['filterFieldsCondition'] = $arCurrentActivity["Properties"]['FilterFieldsCondition'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsValue']))
            {
                $arCurrentValues['filterFieldsValue'] = $arCurrentActivity["Properties"]['FilterFieldsValue'];
            }
            if (!empty($arCurrentActivity["Properties"]['FilterFieldsList']))
            {
                $arCurrentValues['filterFieldsList'] = $arCurrentActivity["Properties"]['FilterFieldsList'];
            }
            if (!empty($arCurrentActivity["Properties"]['DocumentType']))
            {
                $arCurrentValues['lists_document_type'] = implode('@',
                    $arCurrentActivity["Properties"]['DocumentType']);
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
        ]);

        $dialog->setMap(static::getPropertiesMap($paramDocumentType, ['listsDocumentType' => $documentType]));

        return $dialog;
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate,
                                                     &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$errors)
    {
        if (!CModule::IncludeModule('lists'))
        {
            return false;
        }

        $runtime = CBPRuntime::GetRuntime();

        if (is_array($arCurrentValues) && count($arCurrentValues)>0) {

            $arProperties = [
                'DocumentType' => $arCurrentValues['lists_document_type']
                    ? explode('@', $arCurrentValues['lists_document_type']) : null,
                "FilterFields" => $arCurrentValues['filter_fields'],
                "IDs" => $arCurrentValues['ids'],
                'FilterFieldsKey' => $arCurrentValues['filterFieldsKey'],
                'FilterFieldsCondition' => $arCurrentValues['filterFieldsCondition'],
                'FilterFieldsValue' => $arCurrentValues['filterFieldsValue'],
                'FilterFieldsList' => $arCurrentValues['filterFieldsList'],
            ];

            $errors = self::ValidateProperties($arProperties,
                new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

            if ($errors)
            {
                return false;
            }
        }



        //$arProperties['ElementsId'] = self::buildFieldsMap($arProperties['DocumentType'], $arProperties['IDs']);

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
            $arWorkflowTemplate,
            $activityName
        );
        $arCurrentActivity['Properties'] = $arProperties;
        /*
        //$properties['FieldsMap'] = self::buildFieldsMap($properties['DocumentType'], $properties['Fields']);
        //$properties['ElementsId'] = self::buildFieldsMap($properties['DocumentType'], $properties['Fields']);//$arCurrentValues['elementsId'];
        //$arProperties['ElementsId'] = self::buildFieldsMap($arProperties['DocumentType'], ['ID']);
        */
        return true;

    }

    protected static function getPropertiesMap(array $documentType, array $context = []): array
    {
        /*
            'ElementsId' => [
                'Name' => [
                    'ru' => 'IDs',
                    'en' => 'IDs'
                ],
                'Type' => 'string',
                'Multiple' => 'Y',
                'Default' => null
            ],
        */

        $fieldList = isset($context['listsDocumentType']) ? self::getDocumentFieldsOptions($context['listsDocumentType']) : [];
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

        return [
            /*'ElementId' => [
                'Name' => GetMessage('BPGLDA_ELEMENT_ID'),
                'FieldName' => 'lists_element_id',
                'Type' => 'string',
                'Required' => true,
            ],*/
            'FilterFields' => [
                'Name' => "Фильтр по документу",
                'FieldName' => 'filter_fields',
            ],
            'DocumentType' => self::getDocumentTypeField(),
            'IDs' => [
                'Name' => 'IDs',
                'FieldName' => 'ids',
                'Type' => 'string',
                'Multiple' => true,
            ],
            'FilterFieldsKey' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filterFieldsKey',
                'Type' => 'select',
                'Required' => true,
                'Multiple' => false,
                'Options' => $fieldList,
            ],
            'FilterFieldsCondition' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filterFieldsCondition',
                'Type' => 'select',
                'Required' => true,
                'Multiple' => false,
                'Options' => $conditionList,
            ],
            'FilterFieldsValue' => [
                'Name' => GetMessage('BPGLDA_FIELDS_LABEL'),
                'FieldName' => 'filterFieldsValue',
                'Type' => 'string',
                'Required' => true
            ],
            'FilterFieldsList' => [
                'Name' => "Фильтруем по значению из списка?",
                'FieldName' => 'filterFieldsList',
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
/*
    protected static function getFilteringFieldsMap(int $documentTypeId): array
    {
        $documentType = CCrmBizProcHelper::ResolveDocumentType($documentTypeId);

        $factory = Container::getInstance()->getFactory($entityTypeId);
        $originalFieldsCollection = isset($factory) ? $factory->getFieldsCollection() : null;

        $map = [];

        $supportedFieldTypes = [
            FieldType::DOUBLE,
            FieldType::INT,
            FieldType::USER,
            FieldType::STRING,
            FieldType::BOOL,
        ];
        foreach (Crm\Automation\Helper::getDocumentFields($documentType) as $fieldId => $field)
        {
            if ($fieldId === 'OBSERVER_IDS')
            {
                $fieldId = Crm\Item::FIELD_NAME_OBSERVERS;
            }

            $isEntityField = true;
            if (isset($originalFieldsCollection))
            {
                $isEntityField = $originalFieldsCollection->hasField($factory->getCommonFieldNameByMap($fieldId));
            }
            if (
                in_array($field['Type'], $supportedFieldTypes, true)
                && !static::isInternalField($fieldId)
                && $isEntityField
            )
            {
                $map[] = $field;
            }
        }

        return $map;
    }
*/
    private function logDebug($id, $type)
    {
        if (!method_exists($this, 'getDebugInfo'))
        {
            return;
        }

        $debugInfo = $this->getDebugInfo([
            'ElementId' => $id,
            'DocumentType' => implode('@', $type),
        ]);

        unset($debugInfo['Fields']);

        $this->writeDebugInfo($debugInfo);
    }

    private function logDebugFields(array $fields, array $values)
    {
        if (!method_exists($this, 'getDebugInfo'))
        {
            return;
        }

        $debugInfo = $this->getDebugInfo($values, $fields);
        $this->writeDebugInfo($debugInfo);
    }
}