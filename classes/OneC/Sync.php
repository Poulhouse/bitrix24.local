<?php namespace KPLab\OneC;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/ss_sync.php');

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use KPLab\Logs;

define("LOG_ONEC_SYNC_TWO", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/sync2.log");
class Sync {
	protected $id;
	public $clientId;
	public $entityTypeId;
	public $compatibleData;
	public $org;
	public $clientOrg;
	public $data;

	public function __construct($id, $data = null) {
		$this->id = $id;
	}

	public function getData($key = false) {
		try {
			$result = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId)->getItem($this->clientId)->getCompatibleData();
			$this->compatibleData = $result;
			if ($key) {
				$result = $result[$key];
			}
		} catch (\Exception $e) {
			$result = $e;
		}

		return $result;
		//return $this->compatibleData = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId)->getItem($this->clientId)->getCompatibleData();
	}

	//проверка заполнения организации
	public function checkOrg($org) {
		if (is_array($org)){
			if (in_array(5, $org) || in_array(6, $org)) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			if ($org == 5 || $org == 6) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
	}

	//Возврат массива контактных данных
	public function getContactDetails () {
		$entityTypeId = $this->entityTypeId;
		$id = $this->clientId;

		$resultArray = Array();
		if ($entityTypeId == 3) {
			$entityTypeId = 'CONTACT';
		}
		else if ($entityTypeId == 4) {
			$entityTypeId = 'COMPANY';
		}
		else
			return $resultArray;

		global $DB;
		$limit = 3;//Выбираем только 3, если больше = хлам, слишком много - не засинхронится

		$resultsQuery = $DB->Query("SELECT * FROM b_crm_field_multi WHERE 
			TYPE_ID LIKE 'PHONE'
			AND ENTITY_ID LIKE '$entityTypeId' 
			AND ELEMENT_ID = '$id'
		");
		//( TYPE_ID LIKE 'PHONE' OR TYPE_ID LIKE 'EMAIL' )
		if (intval($resultsQuery->SelectedRowsCount())>0) {
			if (intval($resultsQuery->SelectedRowsCount())>$limit ) {
				for ($i = 0; $i < $limit; $i++) {
					$row = $resultsQuery->Fetch();
					array_push($resultArray, $row);
				}
			}
			else {
				while ($row = $resultsQuery->Fetch()){
					array_push($resultArray, $row);
				}
			}
		}

		$resultsQuery = $DB->Query("SELECT * FROM b_crm_field_multi WHERE 
			TYPE_ID LIKE 'EMAIL'
			AND ENTITY_ID LIKE '$entityTypeId' 
			AND ELEMENT_ID = '$id'
		");
		if (intval($resultsQuery->SelectedRowsCount())>0) {
			if (intval($resultsQuery->SelectedRowsCount())>$limit ) {
				for ($i = 0; $i < $limit; $i++) {
					$row = $resultsQuery->Fetch();
					array_push($resultArray, $row);
				}
			}
			else {
				while ($row = $resultsQuery->Fetch()){
					array_push($resultArray, $row);
				}
			}
		}

		return $resultArray;
	}

	//region Old
	public function getContactMKKUidFromId($id) {
		if (strripos($id, "CO_") === 0 ) {
			$id = mb_substr($id ,3);
		}
		if (strripos($id, "C_") === 0 ) {
			$id = mb_substr($id ,2);
		}
		//
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItems(array(
			'select' => array("UF_CRM_CONTACT_SS_FL_AM_ID"),'filter' => array
			(
				array('ID' => $id)
			)));
		if (isset($items[0]['UF_CRM_CONTACT_SS_FL_AM_ID']))
		{
			return $items[0]['UF_CRM_CONTACT_SS_FL_AM_ID'];
		}
		return NULL;
	}

	public function getContactKPKUidFromId($id) {
		if (strripos($id, "CO_") === 0 ) {
			$id = mb_substr($id ,3);
		}
		if (strripos($id, "C_") === 0 ) {
			$id = mb_substr($id ,2);
		}
		//
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItems(array(
			'select' => array("UF_CRM_CONTACT_SS_FL_AK_ID"),'filter' => array
			(

				array('ID' => $id)

			)));
		if (isset($items[0]['UF_CRM_CONTACT_SS_FL_AK_ID']))
		{
			return $items[0]['UF_CRM_CONTACT_SS_FL_AK_ID'];
		}
		return NULL;
	}

	public function getCompanyMKKUidFromId($id) {
		if (strripos($id, "CO_") === 0 ) {
			$id = mb_substr($id ,3);
		}
		if (strripos($id, "C_") === 0 ) {
			$id = mb_substr($id ,2);
		}
		//
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(4)->getItems(array(
			'select' => array("UF_CRM_COMPANY_SS_AM_ID"),'filter' => array
			(
				array('ID' => $id)
			)));
		if (isset($items[0]['UF_CRM_COMPANY_SS_AM_ID']))
		{
			return $items[0]['UF_CRM_COMPANY_SS_AM_ID'];
		}
		return NULL;
	}

	public function getCompanyKPKUidFromId($id) {
		if (strripos($id, "CO_") === 0 ) {
			$id = mb_substr($id ,3);
		}
		if (strripos($id, "C_") === 0 ) {
			$id = mb_substr($id ,2);
		}
		//
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(4)->getItems(array(
			'select' => array("UF_CRM_COMPANY_SS_AK_ID"),'filter' => array
			(
				array('ID' => $id)
			)));
		if (isset($items[0]['UF_CRM_COMPANY_SS_AK_ID']))
		{
			return $items[0]['UF_CRM_COMPANY_SS_AK_ID'];
		}
		return NULL;
	}

	public function getUserMKKUidFromId($id) {
		$dataUser = \CUser::GetByID($id)->Fetch();
		if (isset($dataUser['UF_USER_SS_USER_AM_ID']))
		{
			return $dataUser['UF_USER_SS_USER_AM_ID'];
		}
		return NULL;
	}

	public function getUserKPKUidFromId($id) {
		$dataUser = \CUser::GetByID($id)->Fetch();
		if (isset($dataUser['UF_USER_SS_USER_AK_ID']))
		{
			return $dataUser['UF_USER_SS_USER_AK_ID'];
		}
		return NULL;
	}
	//endregion Old

	public function getContactUidFromId($id, $org) {
		if (strripos($id, "C_") === 0 ) {
			$id = mb_substr($id ,2);
		}
		if ($org == 5) {
			$key = "UF_CRM_CONTACT_SS_FL_AM_ID";
		}
		else if ($org == 6) {
			$key = "UF_CRM_CONTACT_SS_FL_AK_ID";
		}
		//
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItems(array(
			'select' => array($key),'filter' => array
			(
				array('ID' => $id)
			)));
		if (isset($items[0][$key])) {
			return $items[0][$key];
		}
		return NULL;
	}

	public function getContactIdFromUid($uid) {
		$items = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItems(array(
			'select' => array("ID"),'filter' => array
			(
				"LOGIC" => "OR",
				array('UF_CRM_CONTACT_SS_FL_AM_ID' => $uid),
				array('UF_CRM_CONTACT_SS_FL_AK_ID' => $uid)
			)));
		if (isset($items[0]['ID']))
		{
			return $items[0]['ID'];
		}
		return NULL;
	}

	function getUserIdFromUID($uid){
		if (!$uid)
			return 0;

		$filter = Array("UF_USER_SS_USER_AM_ID" => $uid);
		$id = \CUser::GetList( ($by = "ID"), ($order = "desc"), $filter)->Fetch();
		if ($id['ID'])
			return $id['ID'];

		$filter = Array("UF_USER_SS_USER_AK_ID" => $uid);
		$id = \CUser::GetList( ($by = "ID"), ($order = "desc"), $filter)->Fetch();
		if ($id['ID'])
			return $id['ID'];

		$filter = Array("UF_USER_SS_USER_AS_ID" => $uid);
		$id = \CUser::GetList( ($by = "ID"), ($order = "desc"), $filter)->Fetch();
		if ($id['ID'])
			return $id['ID'];

		return 0;
	}


}



