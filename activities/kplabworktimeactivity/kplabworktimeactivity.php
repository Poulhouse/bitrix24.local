<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Bizproc\FieldType,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Page\Asset;


class CBPKPLabWorkTimeActivity extends CBPActivity
{
	/**
	 * Инициализирует действие.
	 *
	 * @param $name
	 */
	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = array(
			'DateFrom' => '', // от
			'DateTo' => '', // до
			'MyReturn' => array(
				'Minutes' => array(
					'Name' => 'Рабочие минуты',
					'Type' => \Bitrix\Bizproc\FieldType::TEXT,
				),
			)
		);

		$this->SetPropertiesTypes(array(
			'DateFrom' => array('Type' => FieldType::DATETIME), // от
			'DateTo' => array('Type' => FieldType::DATETIME) // до
		));
	}

	/**
	 * Начинает выполнение действия.
	 *
	 * @return int Константа CBPActivityExecutionStatus::*.
	 * @throws Exception
	 */
	public function Execute()
	{

	}

	/**
	 * Обработчик ошибки выполнения БП
	 * (вызывается, если ошибка произошла во время выполнения данного действия).
	 *
	 * @param Exception $exception
	 * @return int Константа CBPActivityExecutionStatus::*.
	 * @throws Exception
	 */
	public function HandleFault (Exception $exception)
	{

	}

	/**
	 * Обработчик остановки БП (если остановка произошла во время выполнения
	 * данного действия).
	 *
	 * @return int Константа CBPActivityExecutionStatus::*.
	 * @throws Exception
	 */
	public function Cancel ()
	{

	}

	public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
	{
		if(!is_array($arCurrentValues)) {
			$arCurrentValues = array(
				'DateFrom' => '',
				'DateTo' => '',
				'MyReturn' => array(
					'Minutes' => array(
						'Name' => 'Рабочие минуты',
						'Type' => \Bitrix\Bizproc\FieldType::TEXT,
					),
				)
			);

			$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
				$arWorkflowTemplate,
				$activityName
			);

			if(is_array($arCurrentActivity['Properties'])) {
				$arCurrentValues = array_merge($arCurrentValues, $arCurrentActivity['Properties']);
				$arCurrentValues['Responsible'] = CBPHelper::UsersArrayToString(
					$arCurrentValues['Responsible'],
					$arWorkflowTemplate,
					$documentType
				);
			}
		}


		if (!CModule::IncludeModule("socialnetwork"))
			return false;

		$runtime = CBPRuntime::GetRuntime();
		return $runtime->ExecuteResourceFile(
			__FILE__,
			"properties_dialog.php",
			array(
				"arCurrentValues" => $arCurrentValues,
				"formName" => $formName,
			)
		);
	}

	public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
	{
		if (empty($arCurrentValues['DateFrom']) || empty($arCurrentValues['DateTo']))
		{
			$arErrors[] = array(
				'code' => 'Empty',
				'message' => "Не заполнены даты"
			);
		}

		$arProperties = array(
			// ...
			//'DateFrom' => $arCurrentValues['DateFrom'],
			'Responsible' => CBPHelper::UsersStringToArray(
				$arCurrentValues['Responsible'],
				$documentType,
				$arErrors
			)
			// ...
		);

		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
			$arWorkflowTemplate,
			$activityName
		);

		$arCurrentActivity["Properties"] = $arProperties;

		return true;
	}
}
?>