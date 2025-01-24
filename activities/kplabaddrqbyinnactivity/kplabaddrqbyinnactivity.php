<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
define("LOG_KPLabAddRqByINNACTIVITY", $_SERVER['DOCUMENT_ROOT']."/local/activities/kplabaddrqbyinnactivity/log.log");
class CBPKPLabAddRqByINNActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
    }

    public function Execute()
    {
        // Получаем ИНН и ID компании из сохраненных свойств
        $inn = $this->INN;
        $companyId = $this->CompanyId;

        if (empty($inn) || empty($companyId)) {
            \KPLab\Logs\File::AddMessage("ИНН или ID компании не указаны","", LOG_KPLabAddRqByINNACTIVITY);
        }

        $arParams = [
            'fields' => [
                'ENTITY_TYPE_ID' => 4,  // ID типа сущности (4 = компания)
                'ENTITY_ID' => $companyId,
                'PRESET_ID' => 1,  // Пресет реквизитов (например, Россия)
                'RQ_INN' => $inn,  // ИНН
            ]
        ];

        \KPLab\Logs\File::AddMessage($arParams,"arParams", LOG_KPLabAddRqByINNACTIVITY);

        // Логика добавления реквизита по ИНН (вызывается API Bitrix24)
        $response = \CRest::call('crm.requisite.add', $arParams)['result'];

        \KPLab\Logs\File::AddMessage($response,"response", LOG_KPLabAddRqByINNACTIVITY);

        //$response = file_get_contents($queryUrl . '?' . $queryData);
        $result = json_decode($response, true);

        if (!empty($result['result'])) {
            $requisiteId = $result['result'];
            $this->RequisiteId = $requisiteId;
        } else {
            throw new Exception("Ошибка при добавлении реквизита: " . $result['error_description']);
        }

        return CBPActivityExecutionStatus::Closed;
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
    {
        if (!is_array($arCurrentValues)) {
            $arCurrentValues = array(
                'INN' => '',
                'CompanyId' => '',
            );

            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity['Properties'])) {
                $arCurrentValues['INN'] = $arCurrentActivity['Properties']['INN'];
                $arCurrentValues['CompanyId'] = $arCurrentActivity['Properties']['CompanyId'];
            }
        }

        $runtime = CBPRuntime::GetRuntime();

        return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "formName" => $formName
            ));
    }

    public static function ValidateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];
        if (empty($arTestProperties['INN'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'INN', 'message' => 'ИНН не указан.'];
        }

        if (empty($arTestProperties['CompanyId'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'CompanyId', 'message' => 'Компания не указана.'];
        }

        return array_merge($errors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialogValues($documentType, $activityName,
                                                     &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables,
                                                     $arCurrentValues, &$arErrors)
    {
        $arErrors = array();

        // Проверка заполнения поля ИНН
        if (strlen($arCurrentValues["INN"]) <= 0) {
            $arErrors[] = array(
                "code" => "emptyINN",
                "message" => "ИНН не указан!",
            );
        }

        // Проверка заполнения поля ID компании
        if (strlen($arCurrentValues["CompanyId"]) <= 0) {
            $arErrors[] = array(
                "code" => "emptyCompanyId",
                "message" => "ID компании не указан!",
            );
        }

        // Если есть ошибки, вернуть false
        if (count($arErrors) > 0) {
            return false;
        }

        // Сохраняем введенные значения в свойства активности
        $arProperties = array(
            'INN' => $arCurrentValues['INN'],
            'CompanyId' => $arCurrentValues['CompanyId'],
        );

        // Обновляем свойства активности
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = $arProperties;

        return true;
    }
}
?>
