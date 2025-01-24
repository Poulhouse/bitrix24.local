<?php namespace KPLab\OneC\Export;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/ss_sync.php');

use KPLab\Logs;
use KPLab\OneC\ContactPersons;
use KPLab\Curl;
use KPLab\OneC\Sync;

define("LOG_ONEC_EXPORT_ZAYAVKA", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/export/zayavka.log");

class Zayavka extends Sync {

	public function __construct($id) {
		parent::__construct($id);
		$this->clientId = $id;
		$this->entityTypeId = 131;
	}

	public function init() {
		$clientData = $this->getData();
		//Logs\File::AddMessage($clientData,"clientData", LOG_ONEC_EXPORT_ZAYAVKA);

		$clientOrg = $clientData["UF_CRM_7_SS_ORG"];

		if (!$this->checkOrg($clientOrg)){
			$result = "Ошибка. Организация в компании заполнена не корректно.";
			echo $result;
			return $result;
		}

		$clientData["Тип"] = "Заявка";

		//region Клиент
		if ($clientData["CONTACT_ID"]<>0 || $clientData["CONTACT_ID"]="") {
			if($clientOrg == 5 ) {
				$clientData["Заемщик"] = $this->getContactMKKUidFromId($clientData["CONTACT_ID"]);
			}
			elseif($clientOrg == 6 ) {
				$clientData["Заемщик"] = $this->getContactKPKUidFromId($clientData["CONTACT_ID"]);
			}
			$objContact = new Contact($clientData["CONTACT_ID"]);
			$objContact->init();
			//ss_sync_export_add(3, $clientData["CONTACT_ID"]);
		}
		else if ($clientData["COMPANY_ID"]<>0) {
			if($clientOrg == 5 ) {
				$clientData["Заемщик"] = $this->getCompanyMKKUidFromId($clientData["COMPANY_ID"]);
			}
			elseif($clientOrg == 6 ) {
				$clientData["Заемщик"] = $this->getCompanyKPKUidFromId($clientData["COMPANY_ID"]);
			}
			$objCompany = new Company($clientData["COMPANY_ID"]);
			$objCompany->init();
			//ss_sync_export_add(4, $clientData["COMPANY_ID"]);
		}
		//endregion Клиент

		//region Филиал
		if ($clientData["UF_CRM_7_SS_FILIAL"]<>0) {
			if($clientOrg == 5 ) {
				$clientData["Филиал"] = $this->getCompanyMKKUidFromId($clientData["UF_CRM_7_SS_FILIAL"]);
			}
			elseif($clientOrg == 6 ) {
				$clientData["Филиал"] = $this->getCompanyKPKUidFromId($clientData["UF_CRM_7_SS_FILIAL"]);
			}
		}
		//endregion Филиал

		//region Автор
		if ($clientData["CREATED_BY"]<>0) {
			if($clientOrg == 5 ) {
				$clientData["Автор"] = $this->getUserMKKUidFromId($clientData["CREATED_BY"]);
			}
			elseif($clientOrg == 6 ) {
				$clientData["Автор"] = $this->getUserKPKUidFromId($clientData["CREATED_BY"]);
			}
		} else {

			if($clientOrg == 5 ) {
				$clientData["Автор"] = $this->getUserMKKUidFromId(2340);//bitrix
			}
			elseif($clientOrg == 6 ) {
				$clientData["Автор"] = $this->getUserKPKUidFromId(2340);//bitrix
			}
		}
		//endregion Автор

		//region Поручители 6 шт
		$tempArray = Array();

		$poruchitelArray = [
			$clientData["UF_CRM_7_1684146978"],
			$clientData["UF_CRM_7_1684147065"],
			$clientData["UF_CRM_7_1693181489"],
			$clientData["UF_CRM_7_1693181506"],
			$clientData["UF_CRM_7_1693181523"],
			$clientData["UF_CRM_7_1693181540"]
		];

		foreach ($poruchitelArray as $poruchitel) {
			if (strripos($poruchitel, "CO_") === 0 && strlen($poruchitel)>3) { //мб такое значение -  ["UF_CRM_7_1684146978"]=> string(3) "CO_"
				$poruchitelId = mb_substr($poruchitel,3);
				$objCompany = new Company($poruchitelId);
				$objCompany->init();
				//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1684146978"],3));
				//$data_string["Поручители"]["Поручитель1"] = ss_sync_getCompanyMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684146978"],3));
				if($clientOrg == 5 ) {
					array_push($tempArray, $this->getCompanyMKKUidFromId($poruchitelId));
				}
				elseif($clientOrg == 6 ) {
					array_push($tempArray, $this->getCompanyKPKUidFromId($poruchitelId));
				}
			} elseif (strripos($poruchitel, "C_") === 0 && strlen($poruchitel)>2) {
				$poruchitelId = mb_substr($poruchitel,2);
				$objContact = new Contact($poruchitelId);
				$objContact->init();
				//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1684146978"],2));
				//$data_string["Поручители"]["Поручитель1"] = ss_sync_getContactMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684146978"],2));
				if($clientOrg == 5 ) {
					array_push($tempArray, $this->getContactMKKUidFromId($poruchitelId));
				}
				elseif($clientOrg == 6 ) {
					array_push($tempArray, $this->getContactKPKUidFromId($poruchitelId));
				}
			}
		}

		/*
		//1
		if (strripos($clientData["UF_CRM_7_1684146978"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1684146978"])>3) { //мб такое значение -  ["UF_CRM_7_1684146978"]=> string(3) "CO_"
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1684146978"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1684146978"],3));
			//$data_string["Поручители"]["Поручитель1"] = ss_sync_getCompanyMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684146978"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1684146978"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1684146978"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1684146978"], "C_") === 0 && strlen($clientData["UF_CRM_7_1684146978"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1684146978"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1684146978"],2));
			//$data_string["Поручители"]["Поручитель1"] = ss_sync_getContactMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684146978"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1684146978"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1684146978"],2)));
			}
		}
		//$data_string["Поручители"]["Поручитель1Процент"] = $data_string["UF_CRM_7_1684147268"];

		//2
		if (strripos($clientData["UF_CRM_7_1684147065"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1684147065"])>3) {
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1684147065"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1684147065"],3));
			//$data_string["Поручители"]["Поручитель2"] = ss_sync_getCompanyMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684147065"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1684147065"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1684147065"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1684147065"], "C_") === 0 && strlen($clientData["UF_CRM_7_1684147065"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1684147065"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1684147065"],2));
			//$data_string["Поручители"]["Поручитель2"] = ss_sync_getContactMKKUidFromId(mb_substr($data_string["UF_CRM_7_1684147065"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1684147065"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1684147065"],2)));
			}
		}

		//3
		if (strripos($clientData["UF_CRM_7_1693181489"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1693181489"])>3) {
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1693181489"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1693181489"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181489"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181489"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1693181489"], "C_") === 0 && strlen($clientData["UF_CRM_7_1693181489"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1693181489"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1693181489"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181489"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181489"],2)));
			}
		}

		//4
		if (strripos($clientData["UF_CRM_7_1693181506"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1693181506"])>3) {
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1693181506"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1693181506"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181506"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181506"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1693181506"], "C_") === 0 && strlen($clientData["UF_CRM_7_1693181506"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1693181506"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1693181506"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181506"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181506"],2)));
			}
		}

		//5
		if (strripos($clientData["UF_CRM_7_1693181523"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1693181523"])>3) {
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1693181523"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1693181523"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181523"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181523"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1693181523"], "C_") === 0 && strlen($clientData["UF_CRM_7_1693181523"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1693181523"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1693181523"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181523"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181523"],2)));
			}
		}

		//6
		if (strripos($clientData["UF_CRM_7_1693181540"], "CO_") === 0 && strlen($clientData["UF_CRM_7_1693181540"])>3) {
			$objCompany = new Company(mb_substr($clientData["UF_CRM_7_1693181540"],3));
			$objCompany->init();
			//ss_sync_export_add(4, mb_substr($clientData["UF_CRM_7_1693181540"],3));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getCompanyMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181540"],3)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getCompanyKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181540"],3)));
			}
		}
		if (strripos($clientData["UF_CRM_7_1693181540"], "C_") === 0 && strlen($clientData["UF_CRM_7_1693181540"])>2) {
			$objContact = new Contact(mb_substr($clientData["UF_CRM_7_1693181540"],2));
			$objContact->init();
			//ss_sync_export_add(3, mb_substr($clientData["UF_CRM_7_1693181540"],2));
			if($clientOrg == 5 ) {
				array_push($tempArray, $this->getContactMKKUidFromId(mb_substr($clientData["UF_CRM_7_1693181540"],2)));
			}
			elseif($clientOrg == 6 ) {
				array_push($tempArray, $this->getContactKPKUidFromId(mb_substr($clientData["UF_CRM_7_1693181540"],2)));
			}
		}
		*/

		//$clientData["Поручители"]["Поручитель2Процент"] = $clientData["UF_CRM_7_1684147317"];
		$clientData["Поручители"] = $tempArray;
		//endregion Поручители 6 шт

		//region Документы заемщика
		$filesArray = ss_files_getFilesArray($clientData['UF_CRM_7_CLIENTDOC']);
		$clientData["Документы"] = $filesArray;
		//endregion Документы заемщика

		//region Документы по проверке
		$filesArray = ss_files_getFilesArray($clientData['UF_CRM_7_1695709635']);
		$clientData["ДокументыПроверка"] = $filesArray;
		//endregion Документы по проверке

		/*
		 * $entityTypeId = 165;
		$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->createItem();
		$item->setCategoryId(26);
		$item->setStageId("DT165_26:NEW");
		$item->set("UF_CRM_14_SS_ENTITYNAME", $data_string["TITLE"]);
		$item->set("UF_CRM_14_SS_ENTITY", "T83_$itemId");
		$item->set("UF_CRM_14_SS_ORG", $data_string["UF_CRM_7_SS_ORG"]);
		$item->set("UF_CRM_14_SS_ENTITYTYPE", "Заявка");
		$item->set("UF_CRM_14_SS_DATA", json_encode($data_string,JSON_UNESCAPED_UNICODE));
		$item->set("CREATED_BY", 1);
		$item->set("ASSIGNED_BY_ID", 1);
		$result = $item->save();
		if (count($result->getErrorMessages())>0) {
			echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
		}
		$itemId = $item->get("ID");
		ss_startBp(165, $itemId, 536);
		*/

		$this->clientOrg = $clientOrg;
		$this->compatibleData = $clientData;

		echo "Создана синхронизация Заявки";

		$this->export();

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
		$objectData['ITEM_TYPE_ID'] = 131;
		$objectData['ITEM_ENTITY'] = "T83_".$clientId;
		$objectData['ITEM_TITLE'] = "(" . $orgName . ") Заявка: " . $clientData['TITLE'];
		$objectData['METHOD'] = "POST";
		$objectData['ORG'] = $clientOrg;
		$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/131/details/{$clientId}/";

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
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(131)->getItem($clientId);
				$item->set("UF_CRM_7_SS_SYNC_DATE", date("d.m.Y H:i:s", strtotime("now")));
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_7_SS_AM_ID")=="") {
						$item->set("UF_CRM_7_SS_AM_ID", $responseArray['Ссылка']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_7_SS_AK_ID")=="") {
						$item->set("UF_CRM_7_SS_AK_ID", $responseArray['Ссылка']);
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
				$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory(131)->getItem($clientId);
				$item->set("UF_CRM_7_SS_SYNC_DATE", date("d.m.Y H:i:s", strtotime("now")));
				if($clientOrg == 5 ) {
					if ($item->get("UF_CRM_7_SS_AM_ID")=="") {
						$item->set("UF_CRM_7_SS_AM_ID", $jsonRes['success']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_7_SS_AK_ID")=="") {
						$item->set("UF_CRM_7_SS_AK_ID", $jsonRes['success']);
					}
				}
			}
		}

		$result = $item->save();

		return $result;
	}

}

