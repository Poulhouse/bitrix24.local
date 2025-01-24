<?php namespace KPLab\OneC\Export;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/ss_sync.php');

use KPLab\Logs;
use KPLab\OneC\ContactPersons;
use KPLab\Curl;
use KPLab\OneC\Sync;

define("LOG_ONEC_EXPORT_COMPANY", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/export/company.log");

class Company extends Sync {

	public function __construct($id) {
		parent::__construct($id);
		$this->clientId = $id;
		$this->entityTypeId = 4;
		//Запуск робота Компании для проверки/заполнения Организационно правовой формы и прочих проверок роботами Битрикс
		ss_startBp($this->entityTypeId, $this->clientId, 1749);
	}

	public function init() {

		$clientData = $this->getData();

		$ss_org = $clientData["UF_CRM_COMPANY_SS_ORG"];

		if (!$this->checkOrg($ss_org)){
			$result = "Ошибка. Организация в компании заполнена не корректно.";
			echo $result;
			return $result;
		}

		// TODO если нет уида мкк то отказ
		$clientData["Тип"] = "Компания";

		global $DB;
		//Реквизиты
		$results = $DB->Query("SELECT * FROM b_crm_requisite WHERE ENTITY_ID = ".$this->clientId." AND ENTITY_TYPE_ID =".$this->entityTypeId); //todo sort DATE_MODIFY

		//region ContactPersons
		$ar_ContactPersons = [];
		$prefix = "CO_";
		$ar_ContactPersons = ContactPersons::setArray($this->clientId, $prefix);
		$clientData["contactPersonDetails"] = $ar_ContactPersons;
		//Logs\File ::AddMessage($contactPersons, "Контакт Битрикс: {$clientId} -- Контактные лица в 1С", LOG_ONEC_SYNC);
		//endregion ContactPersons

		if ($results->SelectedRowsCount()>0) {	//Реквизиты существуют
			$requisite = $results->Fetch();
			$requisiteId = $requisite["ID"];
			//
			$results = $DB->Query("SELECT * FROM b_uts_crm_requisite WHERE VALUE_ID = ".$requisiteId);
			//$tempArray = intval($results->SelectedRowsCount())>0 ? $results->Fetch() : "";
			$tempArray = $results->Fetch();
			foreach ($tempArray as $key => $tempArrayItem) {
				$requisite[$key] = $tempArrayItem;
			}
			$clientData['Реквизиты'] = $requisite;
			//AddMessage2Log($clientData, "clientData+реквизиты company");
			//Адреса
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
			//Банковские реквизиты
			$tempArray = Array();
			$results = $DB->Query("SELECT * FROM b_crm_bank_detail WHERE ENTITY_ID = ".$requisiteId);
			if (intval($results->SelectedRowsCount())>0) {
				while ($row = $results->Fetch()){array_push($tempArray, $row);}
			}
			else { $clientData["Банк"] = "";}
			$clientData["Банк"] = $tempArray;
		}
		$clientData["КонтактныеДанные"] = $this->getContactDetails();
		unset($clientData["FM"]);//Вызывает ошибку чтения json в 1С, т.к. содержит числовые имена атрибутов
		unset($clientData["SEARCH_CONTENT"]);

		foreach ($clientData["UF_CRM_COMPANY_SS_ORG"] as $clientOrg) {
			//region Представитель
			$clientData["Представитель"] = "";
			if (isset($clientData["UF_CRM_1615200179"])) {
				$clientData["Представитель"] = $this->getContactUidFromId($clientData["UF_CRM_1615200179"], $clientOrg);
				//$clientData["Представитель"] = ss_sync_export_companyPredstavitel_add ($clientData["UF_CRM_1615200179"], $clientOrg);//Контакт, Руководитель (представитель)
			}
			Logs\File::AddMessage($clientData["Представитель"],"Представитель", LOG_ONEC_EXPORT_COMPANY);
			//endregion Представитель

			//region Бенефециары
			/*
			$tempArray = Array();
			foreach ($clientData['UF_CRM_1687947495'] as $benefeciar) {
				ss_sync_export_companyPredstavitel_add ($benefeciar);//Синхронизация
				$benefeciarUID = $this->getContactMKKUidFromId($benefeciar);
				if(isset($benefeciarUID)){
					array_push($tempArray, $benefeciarUID);
				}
			}
			$clientData["Бенефициары"] = $tempArray;
			*/
			//endregion Бенефециары

			//region Признак и доля передаются стандартными реквизитами
			if ($clientOrg == 5) {
				$clientData["Бенефициар1"] = $this->getContactMKKUidFromId($clientData['UF_CRM_1702272911']);
				$clientData["Бенефициар2"] = $this->getContactMKKUidFromId($clientData['UF_CRM_1702272991']);
				$clientData["Бенефициар3"] = $this->getContactMKKUidFromId($clientData['UF_CRM_1702273016']);
				$clientData["Бенефициар4"] = $this->getContactMKKUidFromId($clientData['UF_CRM_1702273043']);
				$clientData["Бенефициар5"] = $this->getContactMKKUidFromId($clientData['UF_CRM_1702273072']);
			}
			else if ($clientOrg == 6){
				$clientData["Бенефициар1"] = $this->getContactKPKUidFromId($clientData['UF_CRM_1702272911']);
				$clientData["Бенефициар2"] = $this->getContactKPKUidFromId($clientData['UF_CRM_1702272991']);
				$clientData["Бенефициар3"] = $this->getContactKPKUidFromId($clientData['UF_CRM_1702273016']);
				$clientData["Бенефициар4"] = $this->getContactKPKUidFromId($clientData['UF_CRM_1702273043']);
				$clientData["Бенефициар5"] = $this->getContactKPKUidFromId($clientData['UF_CRM_1702273072']);
			}
			//endregion Признак и доля передаются стандартными реквизитами

			//region Филиал
			/*if ($clientData["UF_CRM_7_SS_FILIAL"]<>0) {
				$clientData["Филиал"] = $this->getCompanyMKKUidFromId($data_string["UF_CRM_7_SS_FILIAL"]) ;
			}*/
			//endregion Филиал

			//region Филиал автора
			// //TODO не выгружается из МКК
			$rsUser = \CUser::GetByID($clientData['CREATED_BY_ID'])->Fetch();
			if ($clientOrg == 5) {
				$clientData["ФилиалАвтора"] = $this->getCompanyMKKUidFromId($rsUser['UF_USR_SS_MKK_FILIAL']);
			}
			else if ($clientOrg == 6){
				$clientData["ФилиалАвтора"] = $this->getCompanyKPKUidFromId($rsUser['UF_USR_SS_KPK_FILIAL']);
			}

			Logs\File::AddMessage($clientData["ФилиалАвтора"],"ФилиалАвтора", LOG_ONEC_EXPORT_COMPANY);
			//endregion Филиал автора

			//region Автор
			if ($clientData["CREATED_BY_ID"]<>0) {
				if ($clientOrg == 5) {
					$clientData["Автор"] = $this->getUserMKKUidFromId($clientData["CREATED_BY_ID"]);
				}
				else if ($clientOrg == 6){
					$clientData["Автор"] = $this->getUserKPKUidFromId($clientData["CREATED_BY_ID"]);
				}
			}

			Logs\File::AddMessage($clientData["Автор"],"Автор", LOG_ONEC_EXPORT_COMPANY);
			//endregion Автор

			//region Группа клиентов
			$clientGroupIndicator = "";
			$idGroup = $clientData['UF_CRM_1702588306'];
			//AddMessage2Log($idGroup,"Организация = {$clientOrg} и группа");
			if ($idGroup !== null) {
				$clientGroupIndicator = \KPlab\BalancePlatform::clientGroupValue($idGroup);
			} else
			{
				unset($clientData['UF_CRM_1702588306']);
			}

			$clientData['clientGroup'] = $clientGroupIndicator;
			/*
			if($clientGroupIndicator !== "") {
				$clientData['clientGroup'] = $clientGroupIndicator;
			} else {
				unset($clientData['clientGroup']);
			}*/
			//if($clientData['clientGroup'] !== "") unset($clientData['UF_CRM_1702588306']);

			//AddMessage2Log($clientData['clientGroup'],"Организация = {$clientOrg} и группа после обработки");
			//endregion Группа клиентов

			$this->clientOrg = $clientOrg;
			$this->compatibleData = $clientData;

			$this->export();
/*
			$entityTypeId = 165;
			$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->createItem();
			$item->setCategoryId(26);//Экспорт
			$item->setStageId("DT165_26:NEW");
			$item->set("UF_CRM_14_SS_ENTITYNAME", $clientData["TITLE"]);
			$item->set("UF_CRM_14_SS_ENTITY", "CO_$clientId");
			$item->set("UF_CRM_14_SS_ORG", $clientOrg);
			$item->set("UF_CRM_14_SS_ENTITYTYPE", "Компания");
			$item->set("UF_CRM_14_SS_DATA", json_encode($clientData,JSON_UNESCAPED_UNICODE));
			$item->set("CREATED_BY", 1);
			$item->set("ASSIGNED_BY_ID", 1);

			$result = $item->save();
			if (count($result->getErrorMessages())>0) {
				echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
			}

			$itemSyncId = $item->get("ID");
*/

			/*
			echo "| ss_sync_export_company_add-2 |";
			echo "| Битрикс: Создана синхронизация Компании " . $itemSyncId . " | ";
			*/
			/*
					$objectData['ITEM_ID'] = $clientId;
					$objectData['ITEM_TYPE_ID'] = 4;
					$objectData['ITEM_TITLE'] = $clientData['TITLE'];
					$objectData['METHOD'] = "POST";
					$objectData['ORG'] = $clientOrg;
					$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/4/details/{$clientId}/";

					if($clientOrg == 5 ) {
						$url = "https://ak.sodeistvie.su/WORK/hs/ss/update";
					}
					elseif($clientOrg == 6 ) {
						$url = "https://ak.sodeistvie.su/KPK/hs/ss/update";
					}

					$data_string = json_encode($clientData,JSON_UNESCAPED_UNICODE);

					Logs\File::AddMessage(4,"entityTypeId",LOG_ONEC_SYNC);
					Logs\File::AddMessage($clientId,"itemId",LOG_ONEC_SYNC);
					Logs\File::AddMessage($objectData,"objectData",LOG_ONEC_SYNC);
					Logs\File::AddMessage($timeData,"timeData",LOG_ONEC_SYNC);
					Logs\File::AddMessage($point,"point",LOG_ONEC_SYNC);
					Logs\File::AddMessage($user.":".$password,"auth",LOG_ONEC_SYNC);

					$jsonRes = Curl::post_OneC($user,$password, $url, $data_string, $objectData, $timeData, $point);
			*/

			//Logs\File::AddMessage($itemSyncId,"itemSyncId",LOG_ONEC_SYNC);
			//ss_sync_export_start($itemSyncId);
			//ss_startBp(165, $itemSyncId, 536);
		}
		//Включить Экспорт в АК-Кредит
		$itemCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(4)->getItem($this->clientId);
		$itemCompany->set("UF_CRM_1683968549", 1);
		$result = $itemCompany->save();

		if (count($result->getErrorMessages())>0) {
			echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
		}
		return null;
	}

	public function export() {

		$clientId = $this->clientId;
		$clientOrg = $this->clientOrg;
		$clientData = $this->compatibleData;

		Logs\File::AddMessage($clientId,"clientId", LOG_ONEC_EXPORT_COMPANY);
		Logs\File::AddMessage($clientOrg,"clientOrg", LOG_ONEC_EXPORT_COMPANY);
		Logs\File::AddMessage($clientData,"clientData", LOG_ONEC_EXPORT_COMPANY);

		$timeData = Logs\TimeData::start();
		$point = "BX_1C";
		$tokenKey = "";
		$user = "bitrix";
		$password = "bitrix";

		if($clientOrg == 5) {$orgName = "МКК";}
		if($clientOrg == 6) {$orgName = "КПК";}

		$objectData['ITEM_ID'] = $clientId;
		$objectData['ITEM_TYPE_ID'] = 4;
		$objectData['ITEM_ENTITY'] = "CO_".$clientId;
		$objectData['ITEM_TITLE'] = "(" . $orgName . ") Компания: " . $clientData['TITLE'];
		$objectData['METHOD'] = "POST";
		$objectData['ORG'] = $clientOrg;
		$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/4/details/{$clientId}/";

		if($clientOrg == 5 ) {
			$url = "https://ak.sodeistvie.su/WORK/hs/ss/update";
		}
		elseif($clientOrg == 6 ) {
			$url = "https://ak.sodeistvie.su/KPK/hs/ss/update";
		}

		$data_string = json_encode($clientData,JSON_UNESCAPED_UNICODE);
		//Logs\File::AddMessage($data_string,"data_string", LOG_ONEC_EXPORT_COMPANY);

		$jsonRes = Curl::post_OneC($user,$password, $url, $data_string, $objectData, $timeData, $point);
		//Logs\File::AddMessage($jsonRes,"jsonRes", LOG_ONEC_EXPORT_COMPANY);

		if ($jsonRes['error'] == "") {
			try {
				$responseArray = json_decode($jsonRes['success'], true);
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(4)->getItem($clientId);
				$item->set("UF_CRM_COMPANY_SS_SYNC_DATE", date("d.m.Y H:i:s", strtotime("now")));
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_COMPANY_SS_AM_ID")=="") {
						$item->set("UF_CRM_COMPANY_SS_AM_ID", $responseArray['Ссылка']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_COMPANY_SS_AK_ID")=="") {
						$item->set("UF_CRM_COMPANY_SS_AK_ID", $responseArray['Ссылка']);
					}
				}
				$result = $item->save();

				if ($responseArray['Тип'] == "ЮрЛицо") {
					ss_sync_ul_update($responseArray);
				}
				else if ($responseArray['Тип'] == "ФизЛицо") {
					$itemFlId = $this->getData("CONTACT_ID")[0];//Контакт в Компании
					if ($responseArray['ПБОЮЛ'] =="Да") {
						if (!isset($itemFlId)) {
							$itemFlId = false;
						}
						//$itemFl = $item->get("CONTACT_ID");
						//ss_SocNetMessageAdd(1, 483, $itemFlId." -533");
						echo 11111;
						ss_sync_fl_update($responseArray, false, $itemFlId);
						ss_sync_ip_update($responseArray);
						echo 22222;
					}
					else if ($responseArray['ПБОЮЛ'] =="Нет") {
						ss_sync_fl_update($responseArray);
					}
				}
			}
			catch (\Exception $e) {
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(4)->getItem($clientId);
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_COMPANY_SS_AM_ID")=="") {
						$item->set("UF_CRM_COMPANY_SS_AM_ID", $jsonRes['error']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_COMPANY_SS_AK_ID")=="") {
						$item->set("UF_CRM_COMPANY_SS_AK_ID", $jsonRes['error']);
					}
				}
			}
		}

		$result = $item->save();

		return $result;
	}

}

