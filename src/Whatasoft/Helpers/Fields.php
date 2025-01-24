<?php

namespace Whatasoft\Helpers;

use \Whatasoft\IBlock\Fields\Manager;

class Fields
{
	protected $arContactFields = [];
	
	protected $arDealFields = [];
	
	protected $arContactEnumFieldVariants = [];
	
	protected $arDealEnumFieldVariants = [];
	
	protected $fieldManager;
	
	public function __construct(array $arContactFields, array $arDealFields)
	{
		$this->arContactFields = $arContactFields;
		$this->arDealFields = $arDealFields;
		$this->fieldManager = new Manager();
	}
	
	protected function initContactFields(array $arContactFields)
	{
		$allContactFields = $this->fieldManager->getCrmContactFields();
		$allContactUserFields = $this->getCrmContactUserFields();
	}
	
	protected function initDealFields()
	{
		
	}
	
	protected function getCrmContactUserFields()
	{
		return $this->fieldManager->getUserFieldsAsArray('CRM_CONTACT');
	}
	
	protected function getCrmDealUserFields()
	{
		return $this->fieldManager->getUserFieldsAsArray('CRM_DEAL');
	}
	
}