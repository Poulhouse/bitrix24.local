<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\EventManager;

class CBPKPLabJsonToArray extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            'Title' => '',
            'JsonData' => '',

            'DecodedArray' => null,
            'DecodedArrayPrintable' => null,
        ];
        $this->SetPropertiesTypes([
            'DecodedArray' => [
                'Name' => [
                    'ru' => 'Результат от декодирования JSON'
                ],
                'Type' => 'customarray'
            ],
            'DecodedArrayPrintable' => [
                'Name' => [
                    'ru' => 'Результат от декодирования JSON (Printable)'
                ],
                'Type' => 'text'
            ],
        ]);
    }

    protected function reInitialize()
    {
        parent::reInitialize();
        $this->DecodedArray = null;
        $this->DecodedArrayPrintable = null;
    }

    public function execute()
    {
        $rootActivity = $this->GetRootActivity();
        $rootActivity->SetVariable("DecodedArray", "");
        $this->DecodedArray = null;

        if (!isset($this->JsonData) || empty($this->JsonData)) {
            $rootActivity->SetVariable("DecodedArray", json_encode(["error" => "Empty JSON input"]));
            return CBPActivityExecutionStatus::Closed;
        }

        $jsonString = $this->JsonData;
        $arrayData = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $rootActivity->SetVariable("DecodedArray", json_encode(["error" => "Invalid JSON"]));
            return CBPActivityExecutionStatus::Closed;
        }
        $this->DecodedArray = $arrayData;
        $this->DecodedArrayPrintable = print_r($arrayData, true);
// Передаем массив в виде JSON, так как переменная БП не поддерживает массив напрямую
        $rootActivity->SetVariable("DecodedArray", $this->DecodedArray);

        return CBPActivityExecutionStatus::Closed;
    }

    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = ''
    )
    {
        $runtime = CBPRuntime::getRuntime();

        if (!is_array($arWorkflowParameters))
        {
            $arWorkflowParameters = [];
        }
        if (!is_array($arWorkflowVariables))
        {
            $arWorkflowVariables = [];
        }

        if (!is_array($arCurrentValues))
        {
            $arCurrentValues = ['json_data' => ''];

            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity['Properties']))
            {
                $arCurrentValues['json_data'] = $arCurrentActivity['Properties']['JsonData'] ?? '';
            }
        }

        return $runtime->executeResourceFile(
            __FILE__,
            'properties_dialog.php',
            [
                'arCurrentValues' => $arCurrentValues,
                'formName' => $formName,
            ]
        );
    }

    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    )
    {
        $arErrors = [];

        $runtime = CBPRuntime::getRuntime();

        $arProperties = ['JsonData' => $arCurrentValues['json_data']];

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = $arProperties;

        return true;
    }
}