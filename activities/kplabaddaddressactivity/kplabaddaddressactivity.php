<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Bizproc\Activity\PropertiesDialog;
use Bitrix\Bizproc\FieldType;
use Bitrix\Crm;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use KPLab\Logs;
Loc::loadMessages(__FILE__);

class CBPKPLabAddAddressActivity extends  \Bitrix\Bizproc\Activity\BaseActivity
{
	protected static $requiredModules = ['crm'];

	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			"Title" => "",
			"ElementId" => "",
			"ElementType" => "",
			"RequisiteId" => "",
			"AddressType" => "",
			"PostalCode" => "",
			"Country" => "",
			"Province" => "",
			"Region" => "",
			"City" => "",
			"Street" => "",
			"Building" => "",
			"Address2" => "",
		];

		$this->SetPropertiesTypes([
			'Title' => ['Type' => FieldType::STRING],
			'ElementId' => ['Type' => FieldType::INT],
			'ElementType' => ['Type' => FieldType::INT],
			'RequisiteId' => ['Type' => FieldType::INT],
			'AddressType' => ['Type' => FieldType::INT],
			'PostalCode' => ['Type' => FieldType::STRING],
			'Country' => ['Type' => FieldType::STRING],
			'Province' => ['Type' => FieldType::STRING],
			'Region' => ['Type' => FieldType::STRING],
			'City' => ['Type' => FieldType::STRING],
			'Street' => ['Type' => FieldType::STRING],
			'Building' => ['Type' => FieldType::STRING],
			'Address2' => ['Type' => FieldType::STRING],
		]);
	}

	protected function prepareProperties(): void
	{
		parent::prepareProperties();

		$this->writeDebugInfo($this->getDebugInfo());
	}

	protected function internalExecute(): \Bitrix\Main\ErrorCollection
	{
		$errorCollection = parent::internalExecute();

		$elementId = $this->ElementId;
		$elementType = $this->ElementType;
		$addressType = $this->AddressType;
		$requisiteId = $this->RequisiteId;

		$fieldsToUpdate = [
			'TYPE_ID' => $addressType,
			'ENTITY_TYPE_ID' => $elementType,
			'ENTITY_ID' => $elementId,
			'STREET' => $this->Street,
			'BUILDING' => $this->Building,
			'POSTAL_CODE' => $this->PostalCode,
			'COUNTRY' => $this->Country,
			'PROVINCE' => $this->Province,
			'REGION' => $this->Region,
			'CITY' => $this->City,
			'ADDRESS_2' => $this->Address2,
		];
		// Логирование значений полей
		$logData = print_r($fieldsToUpdate, true);
		file_put_contents('/home/bitrix/www/local/activities/kplabaddaddressactivity/internalExecute.log', "internalExecute:\n" . $logData . "\n", FILE_APPEND);


		$Address = \CRest::call('crm.address.add',['fields' =>$fieldsToUpdate]);

		// Логирование значений полей
		$logData = print_r($fieldsToUpdate, true);
		file_put_contents('/home/bitrix/www/local/activities/kplabaddaddressactivity/internalExecute.log', "internalExecute:\n" . $logData . "\n", FILE_APPEND);


		if (isset($Address['error_description']))
		{
			$errorCollection->setError(new Error($Address['error_description']));
		}
		else
		{
			foreach ($fieldsToUpdate as $field => $value) {
				MyClass::addressUpdate($elementId, $elementType, $value, $field, $addressType);
			}
		}

		return $errorCollection;
	}

	protected function checkProperties(): \Bitrix\Main\ErrorCollection
	{
		$errors = parent::checkProperties();

		if ($this->ElementId <= 0)
		{
			$errors->setError(new Error("Нет элемента"));
		}

		if ($this->RequisiteId <= 0)
		{
			$errors->setError(new Error("Нет реквизита"));
		}

		return $errors;
	}

	protected static function extractPropertiesValues(PropertiesDialog $dialog, array $fieldsMap): Result
	{
		$result = new Result();

		$simpleMap = $fieldsMap;

		$result = parent::extractPropertiesValues($dialog, $simpleMap);

		if ($result->isSuccess())
		{
			$currentValues = $result->getData();

			$result->setData($currentValues);
		}

		// Логирование значений полей
		$logData = print_r($result->getData(), true);
		file_put_contents('/home/bitrix/www/local/activities/kplabaddaddressactivity/extractPropertiesValues.log', "extractPropertiesValues:\n" . $logData . "\n", FILE_APPEND);

		return $result;
	}

	protected static function getFileName(): string
	{
		return __FILE__;
	}

	public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
	{
		$addressTypeResult = \CRest::call('crm.enum.addresstype', []);
		$addressTypes = [];

		if (isset($addressTypeResult['result']) && is_array($addressTypeResult['result'])) {
			foreach ($addressTypeResult['result'] as $type) {
				$addressTypes[$type['ID']] = $type['NAME'];
			}
		}

		return [
			'ElementId' => [
				'Name' => Loc::getMessage('CRM_ELEMENT_ID'),
				'FieldName' => 'element_id',
				'Type' => 'string',
				'Required' => true
			],
			'ElementType' => [
				'Name' => Loc::getMessage('CRM_ELEMENT_TYPE'),
				'FieldName' => 'element_type',
				'Type' => 'select',
				'Options' => [
					CCrmOwnerType::Contact => Loc::getMessage('CRM_ELEMENT_TYPE_CONTACT'),
					CCrmOwnerType::Company => Loc::getMessage('CRM_ELEMENT_TYPE_COMPANY')
				],
				'Required' => true
			],
			'RequisiteId' => [
				'Name' => Loc::getMessage('CRM_REQUISITE_ID'),
				'FieldName' => 'requisite_id',
				'Type' => 'string',
				'Required' => true
			],
			'AddressType' => [
				'Name' => Loc::getMessage('CRM_ADDRESS_TYPE'),
				'FieldName' => 'address_type',
				'Type' => 'select',
				'Options' => $addressTypes, //array_column(\CCrmAddress::GetTypeInfos(), 'DESCRIPTION', 'ID'),
				'Required' => true
			],
			'PostalCode' => [
				'Name' => Loc::getMessage('POSTAL_CODE'),
				'FieldName' => 'postal_code',
				'Type' => 'string',
				'Required' => true
			],
			'Country' => [
				'Name' => Loc::getMessage('COUNTRY'),
				'FieldName' => 'country',
				'Type' => 'string',
				'Required' => true
			],
			'Province' => [
				'Name' => Loc::getMessage('PROVINCE'),
				'FieldName' => 'province',
				'Type' => 'string',
				'Required' => true
			],
			'Region' => [
				'Name' => Loc::getMessage('REGION'),
				'FieldName' => 'region',
				'Type' => 'string',
				'Required' => false
			],
			'City' => [
				'Name' => Loc::getMessage('CITY'),
				'FieldName' => 'city',
				'Type' => 'string',
				'Required' => true
			],
			'Street' => [
				'Name' => Loc::getMessage('STREET'),
				'FieldName' => 'street',
				'Type' => 'string',
				'Required' => true
			],
			'Building' => [
				'Name' => Loc::getMessage('BUILDING'),
				'FieldName' => 'building',
				'Type' => 'string',
				'Required' => true
			],
			'Address2' => [
				'Name' => Loc::getMessage('ADDRESS_2'),
				'FieldName' => 'address_2',
				'Type' => 'string',
				'Required' => false
			]
		];
	}
}
