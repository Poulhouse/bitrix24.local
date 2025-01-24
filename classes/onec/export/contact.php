<?php namespace KPLab\OneC\Export;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/ss_sync.php');

use KPLab\Logs;
use KPLab\OneC\ContactPersons;
use KPLab\Curl;
use KPLab\OneC\Sync;

define("LOG_ONEC_EXPORT_CONTACT", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/export/contact.log");

class Contact extends Sync {

	public function __construct($id) {
		parent::__construct($id);
		$this->clientId = $id;
		$this->entityTypeId = 3;
	}

	public function init() {
		$clientData = $this->getData();

		$ss_org = $clientData["UF_CRM_CONTACT_SS_ORG"];

		if (!$this->checkOrg($ss_org)){
			$result = "Ошибка. Организация в компании заполнена не корректно.";
			echo $result;
			return $result;
		}

		$clientData["Тип"] = "Контакт";

		//region Реквизиты
		global $DB;
		$results = $DB->Query("SELECT * FROM b_crm_requisite WHERE ENTITY_ID = ".$this->clientId." AND ENTITY_TYPE_ID =".$this->entityTypeId); //todo sort DATE_MODIFY
		$requisite = intval($results->SelectedRowsCount())>0 ? $results->Fetch() : "";
		$requisiteId = $requisite["ID"];
		$results = $DB->Query("SELECT * FROM b_uts_crm_requisite WHERE VALUE_ID =".$requisiteId);
		$tempArray = intval($results->SelectedRowsCount())>0 ? $results->Fetch() : "";
		foreach ($tempArray as $key => $tempArrayItem) {
			$requisite[$key] = $tempArrayItem;
		}
		$clientData['Реквизиты'] = $requisite;
		//endregion Реквизиты

		//region ContactPersons
		$ar_ContactPersons = [];
		$prefix = "C_";
		$ar_ContactPersons = ContactPersons::setArray($this->clientId, $prefix);
		$clientData["contactPersonDetails"] = $ar_ContactPersons;
		//endregion ContactPersons

		$clientData["КонтактныеДанные"] = $this->getContactDetails();

		//region Адреса
		$tempArray = array();
		$results = $DB->Query(
			"SELECT b_crm_addr.*,b_location_addr_fld.VALUE as HOUSE FROM b_crm_addr,b_location_addr_fld 
			WHERE b_crm_addr.ENTITY_ID = " . $requisiteId . " 
			AND b_crm_addr.ANCHOR_TYPE_ID = " . $this->entityTypeId . "
			AND b_location_addr_fld.ADDRESS_ID = b_crm_addr.LOC_ADDR_ID 
			AND b_location_addr_fld.TYPE = 400"
		);
		if (intval($results->SelectedRowsCount())>0) {
			while ($row = $results->Fetch()){
				array_push($tempArray, $row);
			}
			$clientData["Адреса"] = $tempArray;
		}
		else $clientData["Адреса"] = "";
		//endregion Адреса

		//region Банковские реквизиты
		$tempArray = Array();
		$results = $DB->Query("SELECT * FROM b_crm_bank_detail WHERE ENTITY_ID = ".$requisiteId);
		if (intval($results->SelectedRowsCount())>0) {
			while ($row = $results->Fetch()){
				array_push($tempArray, $row);
			}
		} else {
			$clientData["Банк"] = "";
		}
		$clientData["Банк"] = $tempArray;
		//endregion Банковские реквизиты

		unset($clientData["FM"]);//Вызывает ошибку чтения json в 1С, т.к. содержит числовые имена атрибутов
		unset($clientData["SEARCH_CONTENT"]);

		foreach ($clientData["UF_CRM_CONTACT_SS_ORG"] as $clientOrg) {
			//region Филиал автора
			//TODO не выгружается из МКК
			$rsUser = \CUser::GetByID($clientData['CREATED_BY_ID'])->Fetch();
			if ($clientOrg == 5) {
				$clientData["ФилиалАвтора"] = $this->getCompanyMKKUidFromId($rsUser['UF_USR_SS_MKK_FILIAL']);
			}
			else if ($clientOrg == 6){
				$clientData["ФилиалАвтора"] = $this->getCompanyKPKUidFromId($rsUser['UF_USR_SS_MKK_FILIAL']);
			}
			//endregion Филиал автора

			//region Автор
			$clientData["Автор"] = null;
			if ($clientData["CREATED_BY_ID"]<>0) {
				if ($clientOrg == 5) {
					$clientData["Автор"] = $this->getUserMKKUidFromId($clientData["CREATED_BY_ID"]);
				}
				else if ($clientOrg == 6){
					$clientData["Автор"] = $this->getUserKPKUidFromId($clientData["CREATED_BY_ID"]);
				}
			}
			//endregion Автор

			$this->clientOrg = $clientOrg;
			$this->compatibleData = $clientData;

			echo "Создана синхронизация Контакта";

			$this->export();

			/*
			$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->createItem();
			$item->setCategoryId(26);
			$item->setStageId("DT165_26:NEW");
			$item->set("UF_CRM_14_SS_ENTITYNAME", $clientData["FULL_NAME"]);
			$item->set("UF_CRM_14_SS_ENTITY", "C_$this->clientId");
			$item->set("UF_CRM_14_SS_ORG", $clientOrg);
			$item->set("UF_CRM_14_SS_ENTITYTYPE", "Контакт");
			$item->set("UF_CRM_14_SS_DATA", json_encode($clientData,JSON_UNESCAPED_UNICODE));
			$item->set("CREATED_BY", 1);
			$item->set("ASSIGNED_BY_ID", 1);
			$result = $item->save();
			if (count($result->getErrorMessages())>0) {
				echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
			}
			$itemId = $item->get("ID");
			ss_startBp(165, $itemId, 536);
			echo "Создана синхронизация Контакта $itemId";
			*/
		}

		//Включить Экспорт в АК-Кредит
		$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId)->getItem($this->clientId);
		$item->set("UF_CRM_1669808700107", 1);
		$result = $item->save();
		if (count($result->getErrorMessages())>0) {
			echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
		}
		return null;
	}

	public function export() {

		$clientId = $this->clientId;
		$clientOrg = $this->clientOrg;
		$clientData = $this->compatibleData;

		$timeData = Logs\TimeData::start();
		$point = "BX_1C";
		$user = "bitrix";
		$password = "bitrix";

		if($clientOrg == 5) {$orgName = "МКК";}
		if($clientOrg == 6) {$orgName = "КПК";}

		$objectData['ITEM_ID'] = $clientId;
		$objectData['ITEM_TYPE_ID'] = 3;
		$objectData['ITEM_ENTITY'] = "C_".$clientId;
		$objectData['ITEM_TITLE'] = "(" . $orgName . ") Контакт: " . $clientData['FULL_NAME'];
		$objectData['METHOD'] = "POST";
		$objectData['ORG'] = $clientOrg;
		$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/3/details/{$clientId}/";

		if($clientOrg == 5 ) {
			$url = "https://ak.sodeistvie.su/WORK/hs/ss/update";
		}
		elseif($clientOrg == 6 ) {
			$url = "https://ak.sodeistvie.su/KPK/hs/ss/update";
		}

		$data_string = json_encode($clientData,JSON_UNESCAPED_UNICODE);
		//Logs\File::AddMessage($data_string,"data_string", LOG_ONEC_EXPORT_CONTACT);

		$jsonRes = Curl::post_OneC($user,$password, $url, $data_string, $objectData, $timeData, $point);
		//Logs\File::AddMessage($jsonRes,"jsonRes", LOG_ONEC_EXPORT_CONTACT);

		if ($jsonRes['error'] == "") {
			try {
				$responseArray = json_decode($jsonRes['success'], true);
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItem($clientId);
				$item->set("UF_CRM_CONTACT_SS_SYNC_DATE", date("d.m.Y H:i:s", strtotime("now")));
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_CONTACT_SS_FL_AM_ID")=="") {
						$item->set("UF_CRM_CONTACT_SS_FL_AM_ID", $responseArray['Ссылка']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_CONTACT_SS_FL_AK_ID")=="") {
						$item->set("UF_CRM_CONTACT_SS_FL_AK_ID", $responseArray['Ссылка']);
					}
				}

				//$result = $item->save();
				/*if ($responseArray['ПБОЮЛ'] =="Да") {
					ss_sync_fl_update($responseArray);
					ss_sync_ip_update($responseArray);
				}
				else {
					ss_sync_fl_update($responseArray);
				}*/
			}
			catch (\Exception $e) {
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(3)->getItem($clientId);
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_CONTACT_SS_FL_AM_ID")=="") {
						$item->set("UF_CRM_CONTACT_SS_FL_AM_ID", $jsonRes['success']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_CONTACT_SS_FL_AK_ID")=="") {
						$item->set("UF_CRM_CONTACT_SS_FL_AK_ID", $jsonRes['success']);
					}
				}
			}
		}

		$result = $item->save();

		return $result;
	}

}

