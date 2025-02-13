<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

define("LOG_CBPKPLabBindingCRMActivity", $_SERVER['DOCUMENT_ROOT']."/local/activities/kplabbindingcrmactivity/log.log");

use Bitrix\Crm\Service\Container;

class CBPKPLabBindingCRMActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "CrmEntityTypeId" => null, // Тип CRM-сущности (например, 2 для Сделки)
            "CrmEntityId"     => null, // ID CRM-сущности (куда сохраняем)
            "ParentField"     => null, // Выбранное поле для привязки (например, PARENT_ID_128)
            "SmartProcessId"  => null, // ID элемента смарт-процесса (значение, которое запишется)
        );
    }

    // Метод выполнения активности
    public function Execute()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm'))
        {
            return CBPActivityExecutionStatus::Closed;
        }

        $crmEntityTypeId = $this->CrmEntityTypeId;
        $crmEntityId = $this->CrmEntityId;
        $parentField = $this->ParentField;
        $smartProcessId = $this->SmartProcessId;
        $arProperties = array(
            "CrmEntityTypeId" => $crmEntityTypeId,
            "CrmEntityId"     => $crmEntityId,
            "ParentField"     => $parentField,
            "SmartProcessId"  => $smartProcessId,
        );
        \KPLab\Logs\File::AddMessage($arProperties,"arProperties", LOG_CBPKPLabBindingCRMActivity);

        // Проверка обязательных параметров
        /*if (empty($crmEntityTypeId) || empty($crmEntityId) || empty($parentField) || empty($smartProcessId))
        {
            $this->WriteToTrackingService("Не заданы обязательные параметры", 0, CBPTrackingType::Error);
            return CBPActivityExecutionStatus::Closed;
        }*/

        $factory = Container::getInstance()->getFactory($crmEntityTypeId);

		if($factory)
		{
            $item = $factory->getItem($crmEntityId);
            if(!$item) {
                $this->WriteToTrackingService("CRM-сущность с ID={$crmEntityId} не найдена", 1, CBPTrackingType::Error);
                return CBPActivityExecutionStatus::Closed;
            }

            $item->set($parentField, $smartProcessId);
            $operation = $factory->getUpdateOperation($item);
            $operation->disableAllChecks();

            $operationResult = $operation->launch();
            \KPLab\Logs\File::AddMessage($operationResult->isSuccess(),"operationResult_isSuccess", LOG_CBPKPLabBindingCRMActivity);

            if ($operationResult->isSuccess()) {
                $this->WriteToTrackingService("Привязка выполнена успешно", 1, CBPTrackingType::Error);
            } else {
                $this->WriteToTrackingService("Ошибка обновления CRM-сущности (".print_r($operationResult->getErrorMessages(),true).")", 1, CBPTrackingType::Error);
            }

        } else {
            return CBPActivityExecutionStatus::Closed;
        }

        return CBPActivityExecutionStatus::Closed;
    }


    // Формирование диалога настроек активности
    public static function GetPropertiesDialog(
        $documentType, $activityName,
        $arWorkflowTemplate,$arWorkflowParameters, $arWorkflowVariables,
        $arCurrentValues = null, $formName = "binding_crm"
    )
    {

        $parentFields = [];
        if (!is_array($arCurrentValues)) {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (!empty($arCurrentActivity["Properties"]['CrmEntityTypeId']))
            {
                $arCurrentValues['crmEntityTypeId'] = $arCurrentActivity["Properties"]['CrmEntityTypeId'];
                $entityType = intval($arCurrentActivity["Properties"]['CrmEntityTypeId']);
                switch ($entityType) {
                    case 2: // Сделка
                        $fieldsInfo = CCrmDeal::GetFieldsInfo();
                        foreach ($fieldsInfo as $fieldName => $fieldData) {
                            if (str_starts_with($fieldName, "PARENT_ID_")) {
                                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
                            }
                        }
                        break;
                    case 3: // Контакт
                        $fieldsInfo = CCrmContact::GetFieldsInfo();
                        foreach ($fieldsInfo as $fieldName => $fieldData) {
                            if (str_starts_with($fieldName, "PARENT_ID_")) {
                                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
                            }
                        }
                        break;
                    case 4: // Компания
                        $fieldsInfo = CCrmCompany::GetFieldsInfo();
                        foreach ($fieldsInfo as $fieldName => $fieldData) {
                            if (str_starts_with($fieldName, "PARENT_ID_")) {
                                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
                            }
                        }
                        break;
                    case 1: // Лид
                        $fieldsInfo = CCrmLead::GetFieldsInfo();
                        foreach ($fieldsInfo as $fieldName => $fieldData) {
                            if (str_starts_with($fieldName, "PARENT_ID_")) {
                                $parentFields[$fieldName] = $fieldData['TITLE'] ?? $fieldName;
                            }
                        }
                        break;
                }
            }
            if (!empty($arCurrentActivity["Properties"]['CrmEntityId']))
            {
                $arCurrentValues['crmEntityId'] = $arCurrentActivity["Properties"]['CrmEntityId'];
            }
            if (!empty($arCurrentActivity["Properties"]['ParentField']))
            {
                $arCurrentValues['parentField'] = $arCurrentActivity["Properties"]['ParentField'];
            }
            if (!empty($arCurrentActivity["Properties"]['SmartProcessId']))
            {
                $arCurrentValues['smartProcessId'] = $arCurrentActivity["Properties"]['SmartProcessId'];
            }
        }

        // Список типов CRM-сущностей для выбора
        $crmEntityTypes = array(
            "2" => "Сделка",
            "3" => "Контакт",
            "4" => "Компания",
            "1" => "Лид"
        );

        // Передаём данные в шаблон диалога
        $arResult = array(
            "crmEntityTypes" => $crmEntityTypes,
            "parentFields"   => $parentFields,
            "arCurrentValues"  => $arCurrentValues,
            "formName"       => $formName,
            "documentType"   => $documentType,
            "activityName"   => $activityName,
        );

        $runtime = CBPRuntime::GetRuntime();

        return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php", ['arResult' => $arResult]);
    }

    public static function ValidateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];
        if (empty($arTestProperties['CrmEntityTypeId'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'crmEntityTypeId', 'message' => 'Не выбран тип CRM-сущности.'];
        }

        if (empty($arTestProperties['CrmEntityId'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'crmEntityId', 'message' => 'Не указан ID CRM-сущности.'];
        }

        if (empty($arTestProperties['ParentField'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'parentField', 'message' => 'Не выбрано поле для привязки.'];
        }

        if (empty($arTestProperties['SmartProcessId'])) {
            $errors[] = ['code' => 'NotExist', 'parameter' => 'smartProcessId', 'message' => 'Не указан ID смарт-процесса.'];
        }

        return array_merge($errors, parent::ValidateProperties($arTestProperties, $user));
    }

    // Обработка и сохранение значений, полученных из диалога настроек активности
    public static function GetPropertiesDialogValues($documentType, $activityName,
                                                     &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables,
                                                     $arCurrentValues, &$errors)
    {
        $errors = array();

        if (empty($arCurrentValues["crmEntityTypeId"]))
            $errors[] = "Не выбран тип CRM-сущности.";

        if (empty($arCurrentValues["crmEntityId"]))
            $errors[] = "Не указан ID CRM-сущности.";

        if (empty($arCurrentValues["parentField"]))
            $errors[] = "Не выбрано поле для привязки.";

        if (empty($arCurrentValues["smartProcessId"]))
            $errors[] = "Не указан ID смарт-процесса.";

        if (count($errors) > 0)
            return false;

        $arCurrentValues["crmEntityTypeId"] = intval($arCurrentValues["crmEntityTypeId"]);
        $arCurrentValues["crmEntityId"]     = trim($arCurrentValues["crmEntityId"]);
        $arCurrentValues["parentField"]     = trim($arCurrentValues["parentField"]);
        $arCurrentValues["smartProcessId"]  = trim($arCurrentValues["smartProcessId"]);


        // Массив, который будет сохранён в свойстве активности
        $arProperties = array(
            "CrmEntityTypeId" => $arCurrentValues["crmEntityTypeId"],
            "CrmEntityId"     => $arCurrentValues["crmEntityId"],
            "ParentField"     => $arCurrentValues["parentField"],
            "SmartProcessId"  => $arCurrentValues["smartProcessId"],
        );

        $errors = self::ValidateProperties($arProperties,
            new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if ($errors)
        {
            return false;
        }

        // Обновляем свойства активности
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = $arProperties;

        // Сохранение свойств активности в шаблоне бизнес-процесса
        /*\CBPWorkflowTemplateLoader::SetActivityPropertyValues(
            $arWorkflowTemplate,
            $activityName,
            $arProperties
        );*/

        return true;
    }
}
?>
