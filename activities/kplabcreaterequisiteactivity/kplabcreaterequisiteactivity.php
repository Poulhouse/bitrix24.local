<?php

use Bitrix\Bizproc\Activity\PropertiesDialog;
use Bitrix\Bizproc\FieldType;
use Bitrix\Crm;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use KPLab\Logs;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

define("Log_KplabCreateRequisiteActivity", getLocalPath('activities/kplabcreaterequisiteactivity/KplabCreateRequisiteActivity.log', '/local'));

class CBPKplabCreateRequisiteActivity extends \Bitrix\Bizproc\Activity\BaseActivity
{
	protected static $requiredModules = ['crm'];

	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			'Title' => '',
			'EntityTypeId' => 0,
			'EntityId' => '',
			'PresetId' => 0,
			'RequisiteFields' => [],
			'AddressFields' => [],

			// return
			'RequisiteId' => 0,
			'ErrorMessage' => null,
		];

		$this->SetPropertiesTypes([
			'EntityTypeId' => ['Type' => FieldType::INT],
			'EntityId' => ['Type' => FieldType::STRING],
			'PresetId' => ['Type' => FieldType::INT],
			'RequisiteFields' => ['Type' => FieldType::STRING],
			'AddressFields' => ['Type' => FieldType::STRING],
			'RequisiteId' => ['Type' => FieldType::INT],
			'ErrorMessage' => ['Type' => FieldType::STRING],
		]);
	}

	protected function reInitialize()
	{
		parent::reInitialize();
		$this->RequisiteId = 0;
		$this->ErrorMessage = null;
	}

	protected function prepareProperties(): void
	{
		parent::prepareProperties();

		$requisiteFieldsValues = [];
		if (is_array($this->RequisiteFields)) {
			foreach ($this->RequisiteFields as $fieldId => $fieldValue)
			{
				$requisiteFieldsValues[$fieldId] = $fieldValue;
			}
		}
		$this->preparedProperties['RequisiteFields'] = $requisiteFieldsValues;

		$this->writeDebugInfo($this->getDebugInfo());
	}

	protected function checkProperties(): \Bitrix\Main\ErrorCollection
	{
		$errors = parent::checkProperties();

		if ($this->EntityTypeId <= 0)
		{
			$errors->setError(new Error(Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_TYPE_ID_ERROR')));
		}

		if ($this->EntityId <= 0)
		{
			$errors->setError(new Error(Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_ID_ERROR')));
		}

		if ($this->PresetId <= 0)
		{
			$errors->setError(new Error(Loc::getMessage('BPRIOA_ACT_PROP_PRESET_ID_ERROR')));
		}

		return $errors;
	}

	protected function internalExecute(): \Bitrix\Main\ErrorCollection
	{
		$requisiteId = false;
		$errorCollection = parent::internalExecute();

		$fieldsValues = $this->externalizeDocumentFields();
		$this->logDocumentFields($fieldsValues);

		$requisite = new Crm\EntityRequisite();

		$requisiteData = [
			'ENTITY_TYPE_ID' => $this->EntityTypeId,
			'ENTITY_ID' => $this->EntityId,
			'PRESET_ID' => $this->PresetId,
			'NAME' => 'Название по-умолчанию'
		];
		$requisiteData = array_merge($requisiteData, $fieldsValues);

		$requisiteId = \CRest::call(
			"crm.requisite.add",
			array("fields" => $requisiteData)
		)['result'];

		//$requisiteId = $requisite->add($requisiteData);

		file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/requisiteId.log', "requisiteId:\n" . print_r($requisiteId) . "\n", FILE_APPEND);

		if (!$requisiteId)
		{
			$errorCollection->setError(new Error($requisite->LAST_ERROR));
			$this->ErrorMessage = $requisite->LAST_ERROR;
		}
		else
		{
			$this->RequisiteId = $requisiteId;
			$this->preparedProperties['RequisiteId'] = $requisiteId;
		}

		// Логирование значений полей
		$logData = print_r($requisiteData, true);
		file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/KplabCreateRequisiteActivity_3.log', "internalExecute:\n" . $logData . "\n", FILE_APPEND);

		return $errorCollection;
	}

	private function externalizeDocumentFields(): array
	{
		return $this->RequisiteFields;
	}

	private function logDocumentFields(array $fields)
	{
		$this->writeDebugInfo(
			$this->getDebugInfo($fields)
		);
	}

	protected static function getFileName(): string
	{
		return __FILE__;
	}

	protected static function extractPropertiesValues(PropertiesDialog $dialog, array $fieldsMap): Result
	{
		$result = new Result();

		$simpleMap = $fieldsMap;
		unset($simpleMap['RequisiteFields']);

		// Логирование начальных значений формы
		file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/InitialValues.log', "Initial values: " . print_r($dialog->getCurrentValues(), true), FILE_APPEND);

		$result = parent::extractPropertiesValues($dialog, $simpleMap);

		if ($result->isSuccess())
		{
			$currentValues = $result->getData();

			$presetId = (int)$currentValues['PresetId'];
			if (isset($fieldsMap['RequisiteFields']['Map'][$presetId]))
			{
				$requisiteFieldsMap = $fieldsMap['RequisiteFields']['Map'][$presetId];
				$requisiteFieldsValues = [];
				$errors = [];
				file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/requisiteFieldsMap.log', "requisiteFieldsMap: \n".$requisiteFieldsMap."\n", FILE_APPEND);
				foreach ($requisiteFieldsMap as $fieldId => $fieldProperties)
				{
					$fieldName = $fieldProperties['FieldName'];
					$fieldValue = $dialog->getCurrentValue($fieldName);
					$requisiteFieldsValues[$fieldName] = $fieldValue;

					// Логируем каждое значение поля
					file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/Field.log', "Field ID: $fieldId, Value: $fieldValue\n", FILE_APPEND);
				}

				$currentValues['RequisiteFields'] = $requisiteFieldsValues;
			}

			$result->setData($currentValues);
		}

		// Логирование значений полей
		$logData = print_r($result->getData(), true);
		file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/extractPropertiesValues.log', "extractPropertiesValues:\n" . $logData . "\n", FILE_APPEND);

		return $result;
	}

	public static function getTypeField($FIELD_NAME) {
		global $DB;
		$RQItemSQL = "SELECT * FROM b_user_field WHERE FIELD_NAME='{$FIELD_NAME}' ORDER BY ID ASC;";

		$resRQItemsQuery = $DB->query($RQItemSQL);
		while($resRQItem = $resRQItemsQuery->Fetch()) {
			$USER_TYPE_ID = $resRQItem['USER_TYPE_ID'];
		}

		return $USER_TYPE_ID;
	}

	public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
	{
		$entityTypes = [
			CCrmOwnerType::Contact => Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_TYPE_CONTACT'),
			CCrmOwnerType::Company => Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_TYPE_COMPANY'),
		];

		$EntityPreset = new \Bitrix\Crm\EntityPreset();
		$EntityRequisite = new \Bitrix\Crm\EntityRequisite();

		$presetList = $EntityPreset->getList([
			'filter' => ['ENTITY_TYPE_ID' => CCrmOwnerType::Requisite],
			'select' => ['ID', 'NAME'],
			'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
		]);

		$presetOptions = [];
		$requisiteFields = [];

		foreach ($presetList as $preset)
		{
			$presetOptions[$preset['ID']] = $preset['NAME'];
			$presetId = $preset['ID'];

			$fieldsList = \CRest::call("crm.requisite.preset.field.list", array("preset" => ["ID" => $preset['ID']]))['result'];
			$fieldsListTitle = $EntityRequisite->getFieldsTitles($presetId);
			foreach ($fieldsList as $fieldId => $field)
			{
				$fieldName = $field['FIELD_NAME'];

				if (!str_contains($fieldName, 'RQ')) {
					$type = self::getTypeField($fieldName);

					if ($type == 'boolean') $type = FieldType::BOOL;
					elseif ($type == 'date') $type = FieldType::DATE;
					elseif ($type == 'double') $type = FieldType::DOUBLE;
					elseif ($type == 'datetime') $type = FieldType::DATETIME;
					elseif ($type == 'disk_file') $type = FieldType::FILE;
					elseif ($type == 'disk_version') $type = FieldType::FILE;
					elseif ($type == 'integer') $type = FieldType::INT;
					elseif ($type == 'string') $type = FieldType::STRING;
					else $type = FieldType::STRING;
				} else {
					$type = FieldType::STRING;
				}

				$requisiteFields[$presetId][$fieldId] = [
					'FieldName' => "{$fieldName}",
					'Name' => $fieldsListTitle[$fieldName],
					'Type' => $type,
				];
			}
		}

		return [
			'EntityTypeId' => [
				'Name' => Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_TYPE_ID'),
				'FieldName' => 'entity_type_id',
				'Type' => FieldType::SELECT,
				'Options' => $entityTypes,
				'Required' => true,
			],
			'EntityId' => [
				'Name' => Loc::getMessage('BPRIOA_ACT_PROP_ENTITY_ID'),
				'FieldName' => 'entity_id',
				'Type' => FieldType::INT,
				'Required' => true,
			],
			'PresetId' => [
				'Name' => Loc::getMessage('BPRIOA_ACT_PROP_PRESET_ID'),
				'FieldName' => 'preset_id',
				'Type' => FieldType::SELECT,
				'Options' => $presetOptions,
				'Required' => true,
			],
			'RequisiteFields' => [
				'FieldName' => 'requisite_fields',
				'Map' => $requisiteFields,
				'Getter' => function($dialog, $property, $currentActivity, $compatible) {
					return $currentActivity['Properties']['RequisiteFields'];
				},
			],
			'AddressFields' => [
				'FieldName' => 'address_fields',
				'Map' => [
					'ACTUAL' => [
						'COUNTRY' => 'Страна',
						'CITY' => 'Город',
						'REGION' => 'Регион',
						'STREET' => 'Улица',
						'HOUSE' => 'Дом',
						'POSTAL_CODE' => 'Почтовый индекс'
					],
					'LEGAL' => [
						'COUNTRY' => 'Страна',
						'CITY' => 'Город',
						'REGION' => 'Регион',
						'STREET' => 'Улица',
						'HOUSE' => 'Дом',
						'POSTAL_CODE' => 'Почтовый индекс'
					]
				],
				'Getter' => function($dialog, $property, $currentActivity, $compatible) {
					return $currentActivity['Properties']['AddressFields'];
				},
			],
		];
	}

	/*public static function GetPropertiesDialogValues(
		$documentType,
		$activityName,
		&$workflowTemplate,
		&$workflowParameters,
		&$workflowVariables,
		$currentValues,
		&$errors
	):bool
	{
		$properties = [
			'EntityTypeId' => $currentValues['entity_type_id'],
			'EntityId' => $currentValues['entity_id'],
			'PresetId' => $currentValues['preset_id'],
			'RequisiteFields' => []
		];

		file_put_contents('/home/bitrix/www/local/activities/kplabcreaterequisiteactivity/GetPropertiesDialogValues__requisite_fields.log', "requisite_fields: \n".$currentValues['requisite_fields']."\n", FILE_APPEND);


		// Заполнить RequisiteFields значениями из формы
		foreach ($currentValues['requisite_fields'] as $fieldId => $value)
		{
			$properties['RequisiteFields'][$fieldId] = $value;
		}

		$errors = self::ValidateProperties($properties, new \CBPWorkflowTemplateUser(\CBPWorkflowTemplateUser::CurrentUser));

		if (count($errors) > 0)
		{
			return false;
		}

		$currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($workflowTemplate, $activityName);
		$currentActivity['Properties'] = $properties;

		return true;
	}*/
}
?>
