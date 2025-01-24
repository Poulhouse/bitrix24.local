<?php namespace KPLab\OneC\Import;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'] .'/services_sodeistvie/lib/ss_sync.php');

use KPLab\Logs;
use KPLab\OneC\ContactPersons;
use KPLab\Curl;
use KPLab\OneC\Sync;

define("LOG_ONEC_EXPORT_CONTACT", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/export/contact.log");

class Contact extends Sync {

	public function __construct($data, $idS = false, $id = false) {
		parent::__construct($id, $data);
		
		if (!is_array($data))
			$this->data = \Bitrix\Main\Web\Json::decode($data);
		else
			$this->data = $data;

		$this->clientId = $id;
		$this->entityTypeId = 3;
	}

	public function Update($idS = false, $id = false) {

		$data = $this->data;

		$avtorId = $this->getUserIdFromUID($data['Автор']);

		if (!$id) {
			if ($data['Организация'] == 5)
				$id = $this->getContactIdFromUid($data['Ссылка']);

			if ($data['Организация'] == 6)
				$id = $this->getContactIdFromUid($data['Ссылка']);
		}

		if (!$id)
			$id = ss_sync_get_contact ($data, true);

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId);

		if ($id){
			$item = $factory -> getItem($id);
			$resultMsg =  "Контакт ".$item->getId()." | ";
			$newId = false;
		} else {
			$item = $factory->createItem();
			$item->set("CREATED_BY_ID", $avtorId);
			$item->set("ASSIGNED_BY_ID", $avtorId);
			$item->set("MODIFY_BY_ID", 'CLIENT');
			$item->set("TYPE_ID", 'CLIENT');
			$resultMsg =  "Создан новый контакт | ";
			$newId = true;
		}

		if (isset ($data['Филиал'])){
			$item->set("UF_CRM_643162AC3EF1D", ss_sync_get_company ($data['Филиал']));//Филиал
		}

		if (isset ($data['ЛимитСуммыВыдачиЗаймов'])){
			if ($data['Организация'] == 5) $item->set("UF_CRM_1696595220787", str_replace(",", ".", $data['ЛимитСуммыВыдачиЗаймов']).'|RUB');
			if ($data['Организация'] == 6) $item->set("UF_CRM_1696595296172", str_replace(",", ".",$data['ЛимитСуммыВыдачиЗаймов']).'|RUB');
		}

		$item->set("OPENED", 'Y');//да
		$item->set("PHOTO", 91538);
		$item->set("NAME", $data['Имя']);
		$item->set("SECOND_NAME", $data['Отчество']);
		$item->set("LAST_NAME", $data['Фамилия']);
		$item->set("FULL_NAME", $data['Имя'].' '.$data['Фамилия']);
		//$item->set("BIRTHDATE", $data['ДатаРождения']);
		//$item->set("UF_CRM_1594879924052", $data['МестоРождения']);

		$item->set("UF_CRM_1594880425373", $data['ИНН']);
		$item->set("UF_CRM_1594890896527", $data['СНИЛС']);
		$item->set("COMMENTS", $data['Заметки']);
		$item->set("UF_CRM_CONTACT_SS_SYNC_DATE", date("d.m.Y H:i:s"));

		if ($data['Организация'] == 5) $item->set("UF_CRM_CONTACT_SS_FL_AM_ID", $data['Ссылка']);
		if ($data['Организация'] == 6) $item->set("UF_CRM_CONTACT_SS_FL_AK_ID", $data['Ссылка']);
		if ($data['Пол'] == "Мужской") $item->set("UF_CRM_1554807518190", 29); else $item->set("UF_CRM_1554807518190", 30);
		//

		$value = $item ->get('UF_CRM_CONTACT_SS_ORG');
		if (!isset($value[0]))
			$value = array($data['Организация']);
		else
		{
			array_push($value, $data['Организация']);
			$value = array_unique($value);
		}
		$item -> set('UF_CRM_CONTACT_SS_ORG',$value);

		//region Контактные лица NEW

		if (array_key_exists('contactPersonDetails', $data))
		{
			ContactPersons::getDetails($item->getId(), $data['contactPersonDetails'], "C_");
		}

		// endregion

		//region Контактные лица
		if (array_key_exists('КонтактныеЛица', $data)) {

			$contactPersonArray = explode(";", $data['КонтактныеЛица']);
			if (!empty($contactPersonArray)) {
				if (isset($contactPersonArray[0])) {
					$contactPersonArray1 = explode("|", $contactPersonArray[0]);

					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("друг/подруга")) $contactStatus = 15201;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("знакомая/знакомый")) $contactStatus = 15202;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Зять/Невестка")) $contactStatus = 15203;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("мать/отец")) $contactStatus = 15204;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("муж/жена")) $contactStatus = 15205;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Работодатель")) $contactStatus = 15206;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("родственник/родственница")) $contactStatus = 15207;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Свекр/Свекровь")) $contactStatus = 15208;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("сестра/брат")) $contactStatus = 15209;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Сын/Дочь")) $contactStatus = 15210;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("теща/тесть")) $contactStatus = 15211;

					$item->set("UF_CRM_1693309804939", $contactPersonArray1[0]);//ФИО
					$item->set("UF_CRM_1693310993610", $contactPersonArray1[3]);//ДР
					$item->set("UF_CRM_1693310108142", $contactStatus);//Статус
					$item->set("UF_CRM_1693310745041", $contactPersonArray1[2]);//Телефон
					//$item->set("UF_CRM_1693971189365", $contactPersonArray1[0]);//Почта нет в ак
				}
				if (isset($contactPersonArray[1])) {
					$contactPersonArray1 = explode("|", $contactPersonArray[1]);

					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("друг/подруга")) $contactStatus = 15223;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("знакомая/знакомый")) $contactStatus = 15224;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Зять/Невестка")) $contactStatus = 15225;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("мать/отец")) $contactStatus = 15226;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("муж/жена")) $contactStatus = 15227;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Работодатель")) $contactStatus = 15228;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("родственник/родственница")) $contactStatus = 15229;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Свекр/Свекровь")) $contactStatus = 15230;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("сестра/брат")) $contactStatus = 15231;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("Сын/Дочь")) $contactStatus = 15232;
					if (mb_strtoupper ($contactPersonArray1[1]) == mb_strtoupper ("теща/тесть")) $contactStatus = 15233;

					$item->set("UF_CRM_1693373373129", $contactPersonArray1[0]);//ФИО
					$item->set("UF_CRM_1693373762278", $contactPersonArray1[3]);//ДР
					$item->set("UF_CRM_1693373656527", $contactStatus);//Статус
					$item->set("UF_CRM_1693373721295", $contactPersonArray1[2]);//Телефон
					//$item->set("UF_CRM_1693971327249", $contactPersonArray1[0]);//Почта
					//$item->set("", $contactPersonArray1[4]);//Коммент
					//$item->set("", $contactPersonArray1[5]);//Пенс
				}
			}
		}
		//endregion

		$result = $item->save();

		if (count($result->getErrorMessages())>0) {
			echo '<pre>'; var_dump($result->getErrorMessages()); echo '</pre>';
		}

		//else { echo "Контакт записан" ."<br>";}
		$id = $item->getId();
		//echo '<pre>'; print_r($item->getFromCompatibleData()); echo '</pre>';

		//Реквизиты
		require_once ($_SERVER['DOCUMENT_ROOT'] .'/crest/crest.php');
		$entityRequisite = new \Bitrix\Crm\EntityRequisite;
		$itemRequisite = $entityRequisite->getList
		([
			"select"=>array("*","UF_*"),
			"filter"=>array("ENTITY_ID"=>$id,"ENTITY_TYPE_ID"=>$this->entityTypeId, "ACTIVE"=>"Y"),
			"order"=>array("SORT"=>"desc","ID"=>"desc")
		])->fetch();//fetchAll();

		//AddMessage2Log($data,"1C FL");

		$itemRequisite ['NAME']	=	$data['Фамилия'].' '.$data['Имя'].' '.$data['Отчество'];
		$itemRequisite ['RQ_INN']	=	str_replace(" ", "",$data['ИНН']);
		$itemRequisite ['RQ_FIRST_NAME']	=	$data['Имя'];
		$itemRequisite ['RQ_LAST_NAME']	=	$data['Фамилия'];
		$itemRequisite ['RQ_SECOND_NAME']	=	$data['Отчество'];
		$itemRequisite ['RQ_COMPANY_REG_DATE']	=	$data['Отчество'];
		$itemRequisite ['UF_CRM_1684493639']	=	$data['ДатаРождения'];
		$itemRequisite ['UF_CRM_1647929611']	=	$data['МестоРождения'];

		if ($data['ПБОЮЛ'] == "Да"){
			if ($data['Организация'] == 5){
				$itemRequisite ['UF_CRM_1697782220']	=	1;
			}
			else if ($data['Организация'] == 6){
				$itemRequisite ['UF_CRM_1697782233']	=	1;
			}
		}
		else {
			if ($data['Организация'] == 5){
				$itemRequisite ['UF_CRM_1697782220']	=	0;
			}
			else if ($data['Организация'] == 6){
				$itemRequisite ['UF_CRM_1697782233']	=	0;
			}
		}

		//$item->set("BIRTHDATE", $data['ДатаРождения']);
		//$item->set("UF_CRM_1594879924052", $data['МестоРождения']);
		//"ДокументУдЛичность": "Паспорт гражданина Российской Федерации, 65 12, 500795, 25.09.2012, Отделом УФМС России по Свердловской области в городе Серове, 660-073",
		$identDocArray = explode(', ', $data['ДокументУдЛичность']);
		$itemRequisite ['RQ_IDENT_DOC']	=	$identDocArray[0];
		$itemRequisite ['RQ_IDENT_DOC_SER']	=	str_replace(" ", "",$identDocArray[1]);
		$itemRequisite ['RQ_IDENT_DOC_NUM']	=	$identDocArray[2];
		$itemRequisite ['RQ_IDENT_DOC_DATE']	=	$identDocArray[3];
		$itemRequisite ['RQ_IDENT_DOC_ISSUED_BY']	=	$identDocArray[4];
		$itemRequisite ['RQ_IDENT_DOC_DEP_CODE']	=	$identDocArray[5];
		$itemRequisite ['ADDRESS_ONLY']	=	'N';//дб
		if ($itemRequisite['ID'])
			$entityRequisite->Update($itemRequisite["ID"],$itemRequisite);
		else {
			$presetId = null;
			$itemRequisite ['SORT']	=	600;
			$itemRequisite ['ENTITY_TYPE_ID']	=	$this->entityTypeId;
			$itemRequisite ['PRESET_ID']	=	$presetId;
			$itemRequisite ['ENTITY_ID']	=	$id;
			$itemRequisite ['ACTIVE']	=	'Y';
			$itemRequisite ['CREATED_BY_ID']	=	ss_sync_getUserIdFromUID($data['Автор']);
			$entityRequisite->Add($itemRequisite);
		}

		ss_sync_set_address ($this->entityTypeId, $id, $data, 1);

		//Телефоны, АдресЭлектроннойПочты
		if ($newId){
			$fieldMulti = new \CCrmFieldMulti();
			foreach ($data["ТелефоныБ"] as $dataItem) {
				$fieldMulti->Add([
					'ELEMENT_ID' => $id,
					'ENTITY_ID' => "CONTACT",
					'TYPE_ID' => "PHONE",
					'VALUE' => $dataItem["VALUE"],
					'VALUE_TYPE' => $dataItem["VALUE_TYPE"]
				]);
			}
			$fieldMulti = new \CCrmFieldMulti();
			foreach ($data["АдресЭлектроннойПочты"] as $dataItem) {
				$fieldMulti->Add([
					'ELEMENT_ID' => $id,
					'ENTITY_ID' => "CONTACT",
					'TYPE_ID' => "EMAIL",
					'VALUE' => $dataItem["VALUE"],
					'VALUE_TYPE' => $dataItem["VALUE_TYPE"]
				]);
			}
			$fieldMulti = new \CCrmFieldMulti();
			foreach ($data["АдресСайта"] as $dataItem) {
				$fieldMulti->Add([
					'ELEMENT_ID' => $id,
					'ENTITY_ID' => "CONTACT",
					'TYPE_ID' => "WEB",
					'VALUE' => $dataItem["VALUE"],
					'VALUE_TYPE' => $dataItem["VALUE_TYPE"]
				]);
			}
		}
		else {
			\CRest::call ( 'crm.contact.update',	['ID' => $id,
				'fields' =>
					[
						'PHONE' => $data["Телефоны"],
						'EMAIL' => $data["АдресЭлектроннойПочты"],
						'WEB' => $data["АдресСайта"],

					]]);
		}

		ss_sync_update_bankDetails($this->entityTypeId, $id, $data);

		//Связи
		//$parent = new Bitrix\Crm\ItemIdentifier(4, $data['Организация'] );//TODO
		//$child = new Bitrix\Crm\ItemIdentifier(3, $id);
		//$result = Bitrix\Crm\Service\Container::getInstance()->getRelationManager()->bindItems($parent, $child);
		$resultMsg =  'Битрикс: '.$resultMsg.'Обновлен контакт '.$id.' | '. $data['Наименование'].' | ';
		echo $resultMsg;
		if ($idS) {
			ss_setSyncFieldValue($idS, 'C_'.$id, $resultMsg);
		}
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
						$item->set("UF_CRM_CONTACT_SS_FL_AM_ID", $jsonRes['error']);
					}
				}
				elseif($clientOrg == 6 ) {
					if ($item->get("UF_CRM_CONTACT_SS_FL_AK_ID")=="") {
						$item->set("UF_CRM_CONTACT_SS_FL_AK_ID", $jsonRes['error']);
					}
				}
			}
		}

		$result = $item->save();

		return $result;
	}

}

