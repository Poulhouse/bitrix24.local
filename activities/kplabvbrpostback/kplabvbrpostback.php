<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Bizproc\Activity\PropertiesDialog;
use \Bitrix\Bizproc\FieldType;
use \KPLab\VBR\PostBack;

class CBPKPLabVBRPostBack extends CBPActivity {
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = array(
            "Title" => "",
            "ClickId" => "",

            // return properties
            "HeaderResponse" =>  null
        );

        $this->SetPropertiesTypes([
            'HeaderResponse' => ['Type' => 'string'],
        ]);
    }

    protected function ReInitialize()
    {
        parent::ReInitialize();

        $this->HeaderResponse = null;
    }

    // Исполняющийся метод действия
    public function Execute()
    {
        $documentId = $this->GetDocumentId();
        $documentType = $this->GetDocumentType();
        $rootActivity = $this->GetRootActivity();

        $trackingService = $rootActivity->workflow->GetService("TrackingService");

        $report = "";
        $arReport = $trackingService->LoadReport($rootActivity->GetWorkflowInstanceId(), 50);
        foreach ($arReport as $value)
            $report .= $value["MODIFIED"]."\n".$value["ACTION_NOTE"]."\n\n";

        $_documentId = json_encode($documentId);
        if($documentId[1] == "CCrmDocumentLead") $_DocumentId = mb_substr($documentId[2], 5);
        if($documentId[1] == "CCrmDocumentDeal") $_DocumentId = mb_substr($documentId[2], 5);
        $this->WriteToTrackingService("_DocumentId: {$_DocumentId}", 0, CBPTrackingType::Report);

        $PostBack = new PostBack($_DocumentId);
        $PostBackProp = $PostBack->setClientId($this->ClickId);

        $_ClickId = $PostBackProp->CLICKID;
        $this->WriteToTrackingService("Установленный ClickID: {$_ClickId}", 0, CBPTrackingType::Report);

        $responsePostBack = $PostBack->request();
        $_ResponsePostBack = json_encode($responsePostBack);
        $this->WriteToTrackingService("_ResponsePostBack: {$_ResponsePostBack}", 0, CBPTrackingType::Report);
        $this->HeaderResponse = $_ResponsePostBack["header"];

        // Возвратим исполняющей системе указание, что действие завершено
        return CBPActivityExecutionStatus::Closed;
    }


    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();

        if ((!array_key_exists("click_id", $arTestProperties) || count($arTestProperties["click_id"])) <= 0)
            $arErrors[] = array(
                "code" => "NotExist",
                "parameter" => "click_id",
                "message" => "Значение click_id Пустое!"
            );

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialog($documentType, $activityName,
                                               $arWorkflowTemplate,$arWorkflowParameters, $arWorkflowVariables,
                                               $arCurrentValues = null, $formName = "")
    {
        if (!is_array($arCurrentValues)) {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (!empty($arCurrentActivity["Properties"]['ClickId']))
            {
                $arCurrentValues['clickId'] = $arCurrentActivity["Properties"]['ClickId'];
            }
        }

        $runtime = CBPRuntime::GetRuntime();

        return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "formName" => $formName
            ));
    }

    public static function GetPropertiesDialogValues($documentType, $activityName,
                                                     &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables,
                                                     $arCurrentValues, &$arErrors)
    {
        $arErrors = array();

        $runtime = CBPRuntime::GetRuntime();

        if (is_array($arCurrentValues) && count($arCurrentValues)>0) {

            if (strlen($arCurrentValues["clickId"]) <= 0) {
                $arErrors[] = array(
                    "code" => "emptyCode",
                    "message" => "пустое значение КликАйди!",
                );
                return false;
            }

            $arProperties = array(
                'ClickId' => $arCurrentValues['clickId']
            );
        }

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
            $arWorkflowTemplate,
            $activityName
        );
        $arCurrentActivity['Properties'] = $arProperties;

        return true;
    }
}