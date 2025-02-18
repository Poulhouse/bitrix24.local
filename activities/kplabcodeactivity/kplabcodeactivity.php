<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

class CBPKPLabCodeActivity extends CBPActivity
{
	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			'Title' => '',
			'ExecuteCode' => '',

            'ResultPHP' => null
		];
        $this->SetPropertiesTypes([
            'ResultPHP' => [
                'Name' => [
                    'ru' => 'Результат от выполнения PHP',
                    'en' => 'Результат от выполнения PHP'
                ],
                'Type' => 'text',
                'Multiple' => 'N',
            ]
        ]);
	}

    protected function reInitialize()
    {
        parent::reInitialize();
        $this->ResultPHP = null;
    }

    public function execute()
    {
        $this->ResultPHP = null;

        if ($this->ExecuteCode <> '')
        {
            // Очищаем буфер вывода
            ob_start();

            try {
                // Выполняем PHP код, переданный в параметре ExecuteCode
                eval($this->ExecuteCode);

                // Получаем результат выполнения кода
                $result = ob_get_contents();
            } catch (Exception $e) {
                // В случае ошибки, сохраняем ошибку
                $result = 'Ошибка выполнения кода: ' . $e->getMessage();
            }

            // Завершаем захват вывода
            ob_end_clean();
        }

        // Сохраняем результат выполнения в свойство
        $this->ResultPHP = $result;

        return CBPActivityExecutionStatus::Closed;
    }


    private function isCodeSafe($code)
    {
        // Пример проверки: можно добавить свои критерии безопасности
        return !str_contains($code, 'shell_exec');
    }

	public static function validateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null)
	{
		$arErrors = [];

		if ($user == null || !$user->isAdmin())
		{
			$arErrors[] = [
				'code' => 'perm',
				'message' => Loc::getMessage('BPCA_NO_PERMS'),
			];
		}

		if (empty($arTestProperties['ExecuteCode']))
		{
			$arErrors[] = [
				'code' => 'emptyCode',
				'message' => Loc::getMessage('BPCA_EMPTY_CODE'),
			];
		}

		return array_merge($arErrors, parent::validateProperties($arTestProperties, $user));
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
			$arCurrentValues = ['execute_code' => ''];

			$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
			if (is_array($arCurrentActivity['Properties']))
			{
				$arCurrentValues['execute_code'] = $arCurrentActivity['Properties']['ExecuteCode'] ?? '';
			}
		}

        // Подключаем JS для редактора кода
        \Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/fileman/code_editor/code-editor.js');
        \Bitrix\Main\Page\Asset::getInstance()->addString('<link href="/bitrix/js/fileman/code_editor/code-editor.css" type="text/css" rel="stylesheet">');


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

		$arProperties = ['ExecuteCode' => $arCurrentValues['execute_code']];

		$arErrors = self::validateProperties(
			$arProperties,
			new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser)
		);
		if (count($arErrors) > 0)
		{
			return false;
		}

		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
		$arCurrentActivity['Properties'] = $arProperties;

		return true;
	}
}