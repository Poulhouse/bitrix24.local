<?php namespace KPLab;

use Bitrix\Crm\EntityRequisite;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;

define("LOG_BP", $_SERVER['DOCUMENT_ROOT']."/local/classes/balanceplatform/balanceplatform.log");
define("TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");
define('API_KEY','4d0e4072-889b-42cd-950c-af8d58221114');

\Bitrix\Main\Loader::includeModule('kplab.api.v2');
\Bitrix\Main\Loader::includeModule('rest');
\Bitrix\Main\Loader ::IncludeModule('crm');

class BalancePlatform {
	private static function getCompanyData($companyId)
	{
		return \CRest::call('crm.company.get', ['id' => $companyId])['result'];
	}
	private static function getClientGroupIndicator($companyData)
	{
		return $companyData['UF_CRM_1702588306']
			? self::clientGroupValue($companyData['UF_CRM_1702588306'])
			: '';
	}
	private static function getItemData(int $entityTypeId, int $elementID)
	{
		return \CRest::call('crm.item.get', ['entityTypeId' => $entityTypeId, 'id' => $elementID])['result']['item'];
	}
	private static function checkForErrors($data)
	{
		/*
		 * Эта функция позволяет централизованно проверять наличие ошибок в данных и немедленно возвращать результат, если они есть.
		 */
		return $data['errorFields'] ?? null;
	}
	private static function getPassportInfo($contactsIdList, $organization, $isFirstRequest): array
	{
		return self::getPassportInfoById($contactsIdList, $organization, $isFirstRequest);
	}
	private static function getRegistrationData($contactsIdList, $organization, $kladr): array
	{
		return self::getRegistrationDataById($contactsIdList, $organization, $kladr);
	}
	private static function mergeData($passportInfo, $regData): array
	{
		/*
		 * Централизованное объединение данных, что улучшает читаемость и упрощает управление результатами.
		 */
		$mergedData = [];
		foreach ($passportInfo as $i => $p) {
			$mergedData[] = array_merge($p, $regData[$i]);
		}
		return $mergedData;
	}
	private static function initializeResult($companyId, $contactsIdList): array
	{
		$companyData = self::getCompanyData($companyId);

		// Логируем результат
		Logs\File::AddMessage($contactsIdList, "contactsIdList", LOG_BP);

		/*
	     * Централизованное создание и инициализация результирующего массива.
	     * Это также делает код более гибким, если нужно будет добавить дополнительные параметры в результат.
		 */
		$result = [];
		$result['sellerInn'] = $contactsIdList['inn'];
		$result["bkiReportConsentDateLE"] = $companyData['UF_CRM_1700138022'];
		$result["bkiReportDealDateLE"] = $companyData['UF_CRM_1700138022'];
		$result['organization'] = ($contactsIdList['not'] !== "organization");
		$result['isBitrixTest'] = false;
		$result['BitrixIsReadyForOOO'] = ($contactsIdList['BitrixIsReadyForOOO'] === true);

		return $result;
	}
	public static function getCompanyIdBySmart($entityTypeId, $elementID) {
		if($entityTypeId == 134) {
			$resultType134Item = self::getItemData($entityTypeId,$elementID);
			return $resultType134Item['companyId'];
		} elseif($entityTypeId == 149) {
			$resultType149Item = self::getItemData($entityTypeId,$elementID);
			return $resultType149Item['companyId'];
		} else {
			return NULL;
		}
	}
	public static function getItemForPostById(int $entityTypeId, int $id): ?array {
		$result = [];
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($id);
		if ($item)
		{
			$result['ITEM_ID'] = $id;
			$result['ITEM_TYPE_ID'] = $entityTypeId;
			$result['ITEM_TITLE'] = $item->getData()['TITLE'];
			$result['METHOD'] = "POST";
			$result['TASK_ID'] = $item->getData()['UF_SE_TASK_ID'];
			$result['INIT_OBJECT_URL'] = "https://crm.sodeistvie.su/crm/type/{$entityTypeId}/details/{$id}/";
			return $result;
		}

		return null;
	}
	public static function clientGroupValue($ID)
	{
		$IBLOCK_ID = 169;
		$arSelect = Array("*");
		$arFilter = array(
			"IBLOCK_ID" => $IBLOCK_ID,
			"=ID" => $ID
		);
		$res = \CIBlockElement::GetList(Array(), $arFilter, false, Array(), $arSelect);
		while($ob = $res->GetNextElement())
		{
			$fields = $ob->GetFields();
			$properties = $ob->GetProperties();
			$clientGroupIndicator = $fields['NAME'];
		}
		return $clientGroupIndicator;
	}
	public static function getUsernameByID($userId): string
	{
		$rsUser = \CUser::GetByID($userId);
		$arUser = $rsUser->Fetch();
		return $arUser['LAST_NAME'] . " " . $arUser['NAME'];
	}

	//region validateFields
	private static function isEmpty($value): bool
	{
		return $value === null || $value === '';
	}
	private static function validatePerson($person): bool
	{
		$requiredFields = [
			'name', 'surname', 'patronymic', 'birthday',
			'birthPlace', 'creditAmount', 'bkiReportConsentDate',
			'bkiReportDealDate', 'passportIssuer', 'passportNumber',
			'passportIssuedAt', 'bkiReportStateCode', 'bkiReportBirthPlaceCode',
			'registrationAddressFias.city', 'registrationAddressFias.house',
			'registrationAddressFias.region', 'registrationAddressFias.street',
			'registrationAddressFias.regionKladrId', 'registrationAddressFias.registrationType'
		];

		foreach ($requiredFields as $field) {
			if (self::isEmpty(self::getFieldValue($person, $field))) {
				return false;
			}
		}

		return true;
	}
	private static function getFieldValue($array, $fieldPath) {
		$pathParts = explode('.', $fieldPath);
		foreach ($pathParts as $part) {
			if (!isset($array[$part])) {
				return null;
			}
			$array = $array[$part];
		}
		return $array;
	}
	public static function validateFields($data): array
	{
		$VF = ['status' => true];

		$data = json_decode($data, true);

		if (self::isEmpty($data['sellerInn'])) {
			$VF['status'] = false;
		}

		if (isset($data['payload'])) {
			foreach ($data['payload'] as $person) {
				if (self::validatePerson($person) === false) {
					$VF['status'] = false;
				}
			}
		}

		if ($VF['status'] === false) {
			$VF['fields'] = $data;
		}

		return $VF;
	}
	//endregion validateFields

	//region getContactListByCompanyId
	private static function validateCompanyData($companyData): ?array
	{
		global $DB;

		$ENTITY_ID = $companyData['ID'];
		$RQ_FIELD_NAME = "RQ_INN";
		$rqInn = '';

		$reqSQL = "SELECT * FROM b_crm_dp_rq_mcd WHERE ENTITY_ID={$ENTITY_ID} AND ENTITY_TYPE_ID=4 AND RQ_FIELD_NAME='{$RQ_FIELD_NAME}';";
		$rsCompanySQL = $DB->query($reqSQL);

		while ($rqSQL = $rsCompanySQL->Fetch()) {
			$rqInn = $rqSQL['VALUE'];
		}

		if ($rqInn == '') {
			$errorData['errorFields'][$companyData['TITLE']] = "Реквизит ИНН - пустое | ";
			return $errorData;
		}

		return null;
	}
	private static function isOrganization($companyData): bool
	{
		return $companyData['UF_CRM_1684145100226'] == 11076;
	}
	private static function getInn($companyData, $DB = null)
	{
		global $DB;

		$ENTITY_ID = $companyData['ID'];
		$RQ_FIELD_NAME = "RQ_INN";
		$rqInn = '';

		$reqSQL = "SELECT * FROM b_crm_dp_rq_mcd WHERE ENTITY_ID={$ENTITY_ID} AND ENTITY_TYPE_ID=4 AND RQ_FIELD_NAME='{$RQ_FIELD_NAME}';";
		$rsCompanySQL = $DB->query($reqSQL);

		while ($rqSQL = $rsCompanySQL->Fetch()) {
			$rqInn = $rqSQL['VALUE'];
		}

		return $rqInn;
	}
	private static function getOrganizationContactList($companyData, $DB): array
	{
		$contacts = [];

		// Получение руководителя
		if (!empty($companyData['UF_CRM_1615200179'])) {
			if (str_contains($companyData['UF_CRM_1615200179'], "CO_")) {
				//nothing to do
			} elseif(str_contains($companyData['UF_CRM_1615200179'], "C_")) {
				$contacts['ids'][0]['id'] = str_replace('C_', '', $companyData['UF_CRM_1615200179']);
			} else {
				$contacts['ids'][0]['id'] = $companyData['UF_CRM_1615200179'];
			}
			$contacts['ids'][0]['type'] = 'director'; // Указываем тип контакта как 'director'
		}

		// Получение данных по бенефициарам
		foreach ($companyData['UF_CRM_1687947495'] as $personId) {
			$i = 1;
			if (str_contains($personId, "CO_")) {
				continue;
			} elseif(str_contains($personId, "C_")) {
				$contacts['ids'][$i+1]['id'] = str_replace('C_', '', $personId);
			} else {
				$contacts['ids'][$i+1]['id'] = $personId;
			}
			$contacts['ids'][$i+1]['type'] = 'beneficiary'; // Указываем тип контакта как 'beneficiary'

			$i++;
		}

		$contacts['not'] = '';
		$contacts['creditAmount'] = $companyData['UF_CRM_1694331571'] ?? '';
		$contacts['bkiConsentDate'] = $companyData['UF_CRM_1700138022'] ?? '';
		$contacts['clientGroupIndicator'] = self::getClientGroupIndicator($companyData);
		$contacts['BitrixIsReadyForOOO'] = true;
		$contacts['inn'] = self::getInn($companyData, $DB);
		$contacts['fullName'] = $companyData['UF_CRM_1595595411835'] ?? '';

		return $contacts;
	}
	private static function getIndividualContactList($companyData): array
	{
		return [
			'not' => 'organization',
			'ids' => $companyData['ID'],
			'types' => 'director', // Указываем тип контакта как 'director'
			'creditAmount' => $companyData['UF_CRM_1694331571'] ?? '',
			'bkiConsentDate' => $companyData['UF_CRM_1700138022'] ?? '',
			'clientGroupIndicator' => self::getClientGroupIndicator($companyData),
			'inn' => self::getInn($companyData),
			'fullName' => $companyData['UF_CRM_1595595411835'] ?? ''
		];
	}
	public static function getContactListByCompanyId($companyId): array
	{
		global $DB;

		$companyData = self::getCompanyData($companyId);
		if ($errorResponse = self::validateCompanyData($companyData)) {
			return $errorResponse;
		}

		$isOrganization = self::isOrganization($companyData);
		return $isOrganization
			? self::getOrganizationContactList($companyData, $DB)
			: self::getIndividualContactList($companyData);
	}
	//endregion getContactListByCompanyId

	//region getPassportInfoById
	private static function getCompanyRequisiteData($id) {
		$req = new \Bitrix\Crm\EntityRequisite();
		$rsCompany = $req->getList([
			'filter' => ['ENTITY_ID' => $id, 'ENTITY_TYPE_ID' => '4'],
			'select' => ['*', 'UF_CRM_1684493639', 'UF_CRM_1647929611']
		]);
		return $rsCompany->fetch();
	}
	private static function getContactRequisiteData($id) {
		$req = new \Bitrix\Crm\EntityRequisite();
		$rsCompany = $req->getList([
			'filter' => ['ENTITY_ID' => $id, 'ENTITY_TYPE_ID' => '3'],
			'select' => ['*', 'UF_CRM_1684493639', 'UF_CRM_1647929611']
		]);
		return $rsCompany->fetch();
	}
	private static function validateRequisiteData($requisite, $contactsIdList, $isFirstRequest, $isMultiple): string
	{
		$errors = [];
		$fieldsToCheck = [
			'RQ_FIRST_NAME' => "Реквизит Имя - пустое",
			'RQ_LAST_NAME' => "Реквизит Фамилия - пустое",
			'UF_CRM_1684493639' => "Дата рождения - пустое",
			'UF_CRM_1647929611' => "Место рождения - пустое",
			'RQ_IDENT_DOC_ISSUED_BY' => "Реквизит Кем выдан документ - пустое",
			'RQ_IDENT_DOC_NUM' => "Реквизит Номер документа - пустое",
			'RQ_IDENT_DOC_DATE' => "Реквизит Дата/Время выдачи документа - пустое",
		];

		if (!$isFirstRequest) {
			$fieldsToCheck['creditAmount'] = "Поле Совокупный лимит клиента SC - пустое";
		}
		//$fieldsToCheck['RQ_INN'] = $isMultiple ? "Реквизит ИНН - пустое" : "";
		$fieldsToCheck['bkiConsentDate'] = $isMultiple ? "Поле Согласие ПДН - пустое" : "Поле Дата получения согласия - пустое";

		foreach ($fieldsToCheck as $field => $errorMessage) {
			if (empty($requisite[$field] ?? $contactsIdList[$field])) {
				$errors[] = $errorMessage;
			}
		}

		return implode(" | ", $errors);
	}
	private static function formatDate($date, $format): string
	{
		return date($format, strtotime($date));
	}
	private static function getFullName($requisite): string
	{
		return $requisite['RQ_LAST_NAME'] . " " . $requisite['RQ_FIRST_NAME'] . " " . $requisite['RQ_SECOND_NAME'];
	}
	private static function formatPassportData($requisite, $contactsIdList, $isFirstRequest, $isMultiple = false,
	                                           $contactType = false): array
	{
		$data = [];
		$dateVidan = self::formatDate($requisite['RQ_IDENT_DOC_DATE'], "Y-m-d");
		$timeVidan = self::formatDate($requisite['RQ_IDENT_DOC_DATE'], "H:i:s");

		// Логируем результат
		Logs\File::AddMessage($contactType, "contactType", LOG_BP);

		if(!$isMultiple) {
			$data["position"] = $contactsIdList['types'];
		} else {
			$data["position"] = $contactType;
		}


		$data["isGuarantor"] = false;
		$data["innFl"] = $requisite['RQ_INN'];
		$data["name"] = $requisite['RQ_FIRST_NAME'];
		$data["surname"] = $requisite['RQ_LAST_NAME'];
		$data["birthday"] = self::formatDate($requisite['UF_CRM_1684493639'], "d.m.Y");
		$data["patronymic"] = $requisite['RQ_SECOND_NAME'];
		$data["birthPlace"] = $requisite['UF_CRM_1647929611'];
		if (!$isFirstRequest) {
			$data["creditAmount"] = intval(str_replace('|RUB', '', $contactsIdList['creditAmount']));
		}
		$data["bkiReportConsentDate"] = $contactsIdList['bkiConsentDate'];
		$data["bkiReportDealDate"] = $contactsIdList['bkiConsentDate'];
		$data["passportIssuer"] = $requisite['RQ_IDENT_DOC_ISSUED_BY'];
		$data["passportNumber"] = $requisite['RQ_IDENT_DOC_NUM'];
		$data["passportSeries"] = $requisite['RQ_IDENT_DOC_SER'];
		$data["passportIssuedAt"] = $dateVidan . "T" . $timeVidan;
		$data["passportIssuerCode"] = $requisite['RQ_IDENT_DOC_DEP_CODE'];
		$data["bkiReportStateCode"] = "643";
		$data["bkiReportBirthPlaceCode"] = "643";

		return $data;
	}
	private static function getIndividualPassportInfo($contactId, $contactsIdList, $isFirstRequest, $isMultiple = false): array
	{
		$data = [];
		if(!$isMultiple) {
			$requisite = self::getCompanyRequisiteData($contactId);
			if(!$requisite) $requisite = self::getContactRequisiteData($contactId);

			$textError = self::validateRequisiteData($requisite, $contactsIdList, $isFirstRequest, $isMultiple);

			if ($textError !== "") {
				$fullName = $isMultiple ? $contactsIdList['fullName'] : self::getFullName($requisite);
				$data["errorFields"][$fullName] = $textError;
			} else {
				$data[$contactId] = self::formatPassportData($requisite, $contactsIdList, $isFirstRequest, $isMultiple);
			}
		} else {
			$requisite = self::getCompanyRequisiteData($contactId['id']);
			if(!$requisite) $requisite = self::getContactRequisiteData($contactId['id']);

			$textError = self::validateRequisiteData($requisite, $contactsIdList, $isFirstRequest, $isMultiple);

			if ($textError !== "") {
				$fullName = $isMultiple ? $contactsIdList['fullName'] : self::getFullName($requisite);
				$data["errorFields"][$fullName] = $textError;
			} else {
				$data[$contactId['id']] = self::formatPassportData($requisite, $contactsIdList, $isFirstRequest,
					$isMultiple, $contactId['type']);
			}
		}

		return $data;
	}
	public static function getPassportInfoById($contactsIdList, bool $organization = false, $isFirstRequest = false): array
	{
		$data = [];

		if(!$organization) {
			$data = self::getIndividualPassportInfo($contactsIdList['ids'], $contactsIdList, $isFirstRequest);
		}
		else {
			foreach ($contactsIdList['ids'] as $contactId) {
				$individualData = self::getIndividualPassportInfo($contactId, $contactsIdList, $isFirstRequest, true);
				if (isset($individualData["errorFields"])) {
					return $individualData;
				}
				$data[$contactId['id']] = $individualData[$contactId['id']];
			}
		}
		return $data;
	}
	//endregion getPassportInfoById

	//region getBitrixData
	private static function validateBitrixData($itemData, $companyData, $isOrganization = false): array
	{
		$errors = [];

		if ($isOrganization) {
			if (empty($companyData['UF_CRM_1595595411835'])) {
				$errors[] = "Поле ФИО - пустое";
			}
		} else {
			if (empty($companyData['UF_CRM_1595595411835'])) {
				$errors[] = "Полное наименование Компании - пустое";
			}
            if (empty($companyData['UF_CRM_1693788279'])) {
                $errors[] = "Документ согласия - пустое";
            }
		}

		if (empty($itemData['ufCrm56_1684744846487'])) {
			$errors[] = "Поле approvedLimit - пустое";
		}
		if (empty($itemData['ufCrm56_1684744827969'])) {
			$errors[] = "Поле portfolio - пустое";
		}
		if (empty($itemData['ufCrm56_1684744875738'])) {
			$errors[] = "Поле balance - пустое";
		}
		if (empty(self::getUsernameByID($itemData['assignedById']))) {
			$errors[] = "Поле Ответственный СЗ - пустое";
		}

		return $errors;
	}
	private static function convertCurrencyToFloat($currencyValue): float
	{
		return (float)str_replace('|RUB', '', $currencyValue);
	}
	private static function getContractDetails($contracts): array
	{
		$contractDetails = [];
		$contractDates = [];

		foreach ($contracts as $contractId) {
			$contractItem = self::getItemData(188,$contractId);
			$contractDates[$contractItem['id']] = $contractItem['ufCrm15_1679925201'];
			$contractDetails[$contractItem['id']] = $contractItem;
		}

		asort($contractDates);

		$firstContractID = array_key_first($contractDates);
		$lastContractID = array_key_last($contractDates);

		return [
			'contractDate' => date('Y-m-d', strtotime($contractDetails[$firstContractID]['ufCrm15_1679925201'])),
			'lastTrancheDate' => date('Y-m-d', strtotime($contractDetails[$lastContractID]['ufCrm15_1679925201'])),
			'trancheTerm' => $contractDetails[$lastContractID]['ufCrm15SsSrok'],
			'trancheRate' => $contractDetails[$lastContractID]['ufCrm15SsStavka'],
		];
	}
	private static function getContractData($contracts): array
	{
		$contractData = [
			'contractDate' => null,
			'lastTrancheDate' => null,
			'trancheTerm' => null,
			'trancheRate' => null,
		];

		if ($contracts) {
			$contractDetails = self::getContractDetails($contracts);

			$contractData['contractDate'] = $contractDetails['contractDate'];
			$contractData['lastTrancheDate'] = $contractDetails['lastTrancheDate'];
			$contractData['trancheTerm'] = $contractDetails['trancheTerm'];
			$contractData['trancheRate'] = $contractDetails['trancheRate'];
		}

		return $contractData;
	}
	private static function countGuarantors($itemData): int
	{
		$guarantorFields = [
			'ufCrm56_1686298851',
			'ufCrm56_1686298912',
			'ufCrm56_1693179732',
			'ufCrm56_1693179748',
			'ufCrm56_1693179765',
			'ufCrm56_1693179777',
		];

		$count = 0;
		foreach ($guarantorFields as $field) {
			if (!empty($itemData[$field])) {
				$count++;
			}
		}

		return $count;
	}
	private static function populateBitrixData($itemData, $companyData)
	{
		$bitrixData = [];

		$bitrixData['clientName'] = $companyData['UF_CRM_1595595411835'];
		$bitrixData['clientGroupIndicator'] = self::getClientGroupIndicator($companyData);
		$bitrixData['mpCount'] = $companyData['UF_CRM_1706982719'] ?? null;
		$bitrixData['approvedLimit'] = self::convertCurrencyToFloat($itemData['ufCrm56_1684744846487']);
		$bitrixData['portfolio'] = self::convertCurrencyToFloat($itemData['ufCrm56_1684744827969']);
		$bitrixData['balance'] = self::convertCurrencyToFloat($itemData['ufCrm56_1684744875738']);
		$bitrixData['supportDepartmentEmployee'] = self::getUsernameByID($itemData['assignedById']);

		$contractData = self::getContractData($itemData['ufCrm56_1684743456']);
		$bitrixData = array_merge($bitrixData, $contractData);

		$bitrixData['guarantorCount'] = self::countGuarantors($itemData);

		return $bitrixData;
	}
	private static function getIndividualBitrixData($itemData, $companyData)
	{
		$bitrixData = [];
		$errors = self::validateBitrixData($itemData, $companyData);

		if (!empty($errors)) {
			$bitrixData["errorFields"][$companyData['UF_CRM_1595595411835']] = implode(" | ", $errors);
		} else {
			$bitrixData = self::populateBitrixData($itemData, $companyData);
		}

		return $bitrixData;
	}
    
	private static function getOrganizationBitrixData($itemData, $companyData, $company)
	{
		$bitrixData = [];
		$errors = self::validateBitrixData($itemData, $companyData, true);

		if (!empty($errors)) {
			$bitrixData["errorFields"][$company['fullName']] = implode(" | ", $errors);
		} else {
			$bitrixData = self::populateBitrixData($itemData, $companyData);
		}

		return $bitrixData;
	}
	public static function getBitrixData(int $entityTypeId, int $elementID, $companyId, $company, bool $organization = false): array
	{
		$bitrixData = [];
		$itemData = self::getItemData($entityTypeId, $elementID);
		$companyData = self::getCompanyData($companyId);
		if (!$organization) {
			$bitrixData = self::getIndividualBitrixData($itemData, $companyData);
		} else {
			$bitrixData = self::getOrganizationBitrixData($itemData, $companyData, $company);
		}
		return $bitrixData;

		/*
		$contractDates = [];
		$guarantorCount = 0;
		*/
		/*
		if(!$organization) {
			$contactId = $company['ids'];
			$arResult = \CRest::call('crm.company.get',['id'=>$contactId])['result'];
			$resultItem = \CRest::call('crm.item.get',['entityTypeId'=>$entityTypeId,'id'=>$elementID])['result']['item'];
			$data['supportDepartmentEmployee'] = self::getUsernameByID($resultItem['assignedById']); //Ответственный СЗ (ID)
			$textError = "";

			if($arResult['UF_CRM_1595595411835'] == "") $textError .= "Поле Полное наименование Компании - пустое"." | ";
			if($resultItem['ufCrm56_1684744846487'] == "") $textError .= "Поле approvedLimit - пустое"." | ";
			if($resultItem['ufCrm56_1684744827969'] == "") $textError .= "Поле portfolio - пустое"." | ";
			if($resultItem['ufCrm56_1684744875738'] == "") $textError .= "Поле balance - пустое"." | ";
			if($data['supportDepartmentEmployee'] == "") $textError .= "Поле Ответственный СЗ - пустое"." | ";
			if($textError !== "")
			{
				$data["errorFields"][$company['fullName']] = $textError;
			}
			else
			{
				//Группа клиентов SC -- UF_CRM_1702588306 -- company -- Привязка к элементам инф. блоков
				if($arResult["UF_CRM_1702588306"]) {
					$clientGroupIndicator = self::clientGroupValue($arResult["UF_CRM_1702588306"]);
					Logs\File::AddMessage($clientGroupIndicator,"clientGroupIndicator ИП",LOG_BP);
				} else {
					$clientGroupIndicator = "";
				}

				$data['clientName'] = $arResult['UF_CRM_1595595411835']; //полное наименование Компания
				$data['clientGroupIndicator'] = $clientGroupIndicator;
				$data['mpCount'] = $arResult['UF_CRM_1706982719'];

				$data['approvedLimit'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744846487']);
				$data['portfolio'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744827969']);
				$data['balance'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744875738']);

				$contracts = $resultItem['ufCrm56_1684743456'];
				if($contracts) {
					foreach ($contracts as $contractId) {
						$resContract = \CRest::call('crm.item.get',['entityTypeId'=>188,'id'=>$contractId])['result']['item'];
						$contractIdDates[] = $resContract['id'];
						$arContracts[$resContract['id']] = $resContract;
					}
					asort($contractIdDates);

					$countArContracts = count($contractIdDates) - 1;

					$firstContractID = $contractIdDates[0];
					$lastContractID = $contractIdDates[$countArContracts];

					$contractDate = date('Y-m-d', strtotime($arContracts[$firstContractID]['ufCrm15_1679925201']));
					$lastTrancheDate = date('Y-m-d', strtotime($arContracts[$lastContractID]['ufCrm15_1679925201']));
					$trancheTerm = $arContracts[$lastContractID]['ufCrm15SsSrok'];
					$trancheRate = $arContracts[$lastContractID]['ufCrm15SsStavka'];

					$data['contractDate'] = $contractDate;
					$data['lastTrancheDate'] = $lastTrancheDate;
					$data['trancheTerm'] = $trancheTerm;
					$data['trancheRate'] = $trancheRate;
				} else {
					$data['contractDate'] = null;
					$data['lastTrancheDate'] = null;
					$data['trancheTerm'] = null;
					$data['trancheRate'] = null;
				}

				$poruchitel_1 = $resultItem['ufCrm56_1686298851'];
				$poruchitel_2 = $resultItem['ufCrm56_1686298912'];
				$poruchitel_3 = $resultItem['ufCrm56_1693179732'];
				$poruchitel_4 = $resultItem['ufCrm56_1693179748'];
				$poruchitel_5 = $resultItem['ufCrm56_1693179765'];
				$poruchitel_6 = $resultItem['ufCrm56_1693179777'];

				if($poruchitel_1 !== null) $guarantorCount++;
				if($poruchitel_2 !== null) $guarantorCount++;
				if($poruchitel_3 !== null) $guarantorCount++;
				if($poruchitel_4 !== null) $guarantorCount++;
				if($poruchitel_5 !== null) $guarantorCount++;
				if($poruchitel_6 !== null) $guarantorCount++;
				$data['guarantorCount'] = $guarantorCount;
			}

		}
		else {

			$resultItem = \CRest::call('crm.item.get',['entityTypeId'=>$entityTypeId,'id'=>$elementID])['result']['item'];
			$data['supportDepartmentEmployee'] = self::getUsernameByID($resultItem['assignedById']); //Ответственный СЗ (ID)
			$_arResult = \CRest::call('crm.company.get',['id'=>$companyId])['result'];

			$data['clientName'] = $_arResult['UF_CRM_1595595411835'];
			Logs\File::AddMessage($company['ids'],"ООО Количество ФЛ",LOG_BP);

			$textError = "";
			if($data['clientName'] == "") $textError .= "Поле ФИО - пустое"." | ";
			if($resultItem['ufCrm56_1684744846487'] == "") $textError .= "Поле approvedLimit - пустое"." | ";
			if($resultItem['ufCrm56_1684744827969'] == "") $textError .= "Поле portfolio - пустое"." | ";
			if($resultItem['ufCrm56_1684744875738'] == "") $textError .= "Поле balance - пустое"." | ";
			if($data['supportDepartmentEmployee'] == "") $textError .= "Поле Ответственный в карточке Сопровождения займов - пустое"." | ";

			if($textError !== "")
			{
				$data["errorFields"][$company['fullName']] = $textError;
			}
			else
			{
				//Группа клиентов SC -- UF_CRM_1702588306 -- company -- Привязка к элементам инф. блоков
				if($_arResult["UF_CRM_1702588306"]) {
					$clientGroupIndicator = self::clientGroupValue($_arResult["UF_CRM_1702588306"]);
					Logs\File::AddMessage($clientGroupIndicator,"clientGroupIndicator ООО несколько ФЛ",LOG_BP);
				} else {
					$clientGroupIndicator = "";
				}
				//полное наименование Компания
				$data['clientGroupIndicator'] = $clientGroupIndicator;
				$data['mpCount'] = $_arResult['UF_CRM_1706982719'];
				$data['approvedLimit'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744846487']);
				$data['portfolio'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744827969']);
				$data['balance'] = (float) str_replace('|RUB', '', $resultItem['ufCrm56_1684744875738']);
				Logs\File::AddMessage($data,"ООО data Bitrix 1",LOG_BP);
				$contracts = $resultItem['ufCrm56_1684743456'];
				if($contracts){
					foreach ($contracts as $contractId) {
						$resContract = \CRest::call('crm.item.get',['entityTypeId'=>188,'id'=>$contractId])['result']['item'];
						$contractIdDates[] = $resContract['id'];
						$arContracts[$resContract['id']] = $resContract;
					}
					asort($contractIdDates);

					$countArContracts = count($contractIdDates) - 1;

					$firstContractID = $contractIdDates[0];
					$lastContractID = $contractIdDates[$countArContracts];

					$contractDate = date('Y-m-d', strtotime($arContracts[$firstContractID]['ufCrm15_1679925201']));
					$lastTrancheDate = date('Y-m-d', strtotime($arContracts[$lastContractID]['ufCrm15_1679925201']));

					$trancheTerm = $arContracts[$lastContractID]['ufCrm15SsSrok'];
					$trancheRate = $arContracts[$lastContractID]['ufCrm15SsStavka'];

					$data['contractDate'] = $contractDate;
					$data['lastTrancheDate'] = $lastTrancheDate;
					$data['trancheTerm'] = $trancheTerm;
					$data['trancheRate'] = $trancheRate;

				}
				else {
					$data['contractDate'] = "";
					$data['lastTrancheDate'] = "";
					$data['trancheTerm'] = "";
					$data['trancheRate'] = "";
				}

				$poruchitel_1 = $resultItem['ufCrm56_1686298851'];
				$poruchitel_2 = $resultItem['ufCrm56_1686298912'];
				$poruchitel_3 = $resultItem['ufCrm56_1693179732'];
				$poruchitel_4 = $resultItem['ufCrm56_1693179748'];
				$poruchitel_5 = $resultItem['ufCrm56_1693179765'];
				$poruchitel_6 = $resultItem['ufCrm56_1693179777'];

				if($poruchitel_1 !== null) $guarantorCount++;
				if($poruchitel_2 !== null) $guarantorCount++;
				if($poruchitel_3 !== null) $guarantorCount++;
				if($poruchitel_4 !== null) $guarantorCount++;
				if($poruchitel_5 !== null) $guarantorCount++;
				if($poruchitel_6 !== null) $guarantorCount++;
				$data['guarantorCount'] = $guarantorCount;
			}
		}
		return $data;
		*/
	}
	//endregion getBitrixData

	//region getRegistrationDataById
	private static function extractRegistrationAddress($addressList)
	{
		foreach ($addressList as $addrItem) {
			if ($addrItem['TYPE_ID'] == 4) {
				$addressController = new \Bitrix\Location\Controller\Address;
				return $addressController->findById($addrItem['LOC_ADDR_ID'])['fieldCollection'];
			}
		}
		return null;
	}
	private static function getAddressList($contactId)
	{
		$resultAddressList = \CRest::call('crm.address.list', [
			'filter' => ['ANCHOR_ID' => $contactId],
			'select' => ['TYPE_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID', 'ANCHOR_ID', 'LOC_ADDR_ID']
		]);
		return $resultAddressList['result'];
	}
	private static function getKladrFieldData($addressFields, $fieldId, $contentType, $regionId = '', $cityId = '')
	{
		if (empty($addressFields[$fieldId])) {
			return [];
		}

		$query = explode(" ", $addressFields[$fieldId])[0];
		$curlOptions = [
			CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}&regionId={$regionId}&cityId={$cityId}",
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
			CURLOPT_RETURNTRANSFER => true
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$result = json_decode(curl_exec($ch), true)['result'];

		return $result[1] ?? [];
	}
	private static function getHouseNumber($addressFields)
	{
		$houseNumber = trim(self::getField($addressFields, 400));

		if(str_contains($houseNumber, 'д')) {
			$houseNumber = trim(explode("д", $houseNumber)[0]);
		}

		if(str_contains($houseNumber, 'строение')) {
			$houseNumber = trim(explode("строение", $houseNumber)[0]);
		}
		elseif(str_contains($houseNumber, 'корпус')) {
			$houseNumber = trim(explode("корпус", $houseNumber)[0]);
		}
		// Логируем результат
		Logs\File::AddMessage($houseNumber, "houseNumber", LOG_BP);

		return $houseNumber;
	}
	private static function extractBuilding($addressFields): ?string
	{
		$houseNumber = trim(self::getField($addressFields, 400));
		if(str_contains($houseNumber, 'строение')) {
			$Building = (int) trim(explode("строение", $houseNumber)[1]);
		} else {
			$Building = null;
		}
		return $Building;
	}
	private static function extractStructure($addressFields): ?string
	{
		$houseNumber = trim(self::getField($addressFields, 400));
		if(str_contains($houseNumber, 'корпус')) {
			$Structure = (int) trim(explode("корпус", $houseNumber)[1]);
		} else {
			$Structure = null;
		}
		return $Structure;
	}
	private static function getField($addressFields, $fieldId)
	{
		return $addressFields[$fieldId] ?? '';
	}
	private static function getKladrData($addressFields): array
	{
		$addressData = [];

		$regionData = self::getKladrFieldData($addressFields, 200, 'region');
		if (empty($regionData)) {
			return ['errorFields' => 'Нет данных о Регионе'];
		}

		$cityData = self::getKladrFieldData($addressFields, 300, 'city', $regionData['id']);
		if (empty($cityData) && $regionData['name'] !== 'Москва') {
			return ['errorFields' => 'Нет данных о Городе'];
		}

		$streetData = self::getKladrFieldData($addressFields, 340, 'street', $regionData['id'], $cityData['id']);
		if (empty($streetData)) {
			return ['errorFields' => 'Нет данных об Улице'];
		}

		$addressData['region'] = intval(mb_strimwidth($regionData['id'], 0, 2, ""));
		$addressData['regionKladrId'] = $regionData['id'];
		$addressData['city'] = $cityData['name'] ?? '';
		$addressData['street'] = $streetData['name'] ?? '';
		$addressData['house'] = self::getHouseNumber($addressFields);
		$addressData['flat'] = self::getField($addressFields, 600);
		$addressData['building'] = self::extractBuilding($addressFields);
		$addressData['structure'] = self::extractStructure($addressFields);
		$addressData['registrationType'] = "const";

		return $addressData;
	}
	private static function getSimpleAddressData($addressFields): array
	{
		$addressData = [];

        $regionData = self::getKladrFieldData($addressFields, 200, 'region');
        if (empty($regionData)) {
            return ['errorFields' => 'Нет данных о Регионе'];
        }
        // Логируем результат
        Logs\File::AddMessage($regionData, "regionData", LOG_BP);
        $cityData = self::getField($addressFields, 300);
        if (empty($cityData)) {
            return ['errorFields' => 'Нет данных о Городе'];
        }

		$addressData['region'] = intval(mb_strimwidth($regionData['id'], 0, 2, ""));
        $addressData['regionKladrId'] = $regionData['id'];
		$addressData['city'] = $cityData;
		$addressData['street'] = self::getField($addressFields, 340);
		$addressData['house'] = self::getField($addressFields, 400);
		$addressData['flat'] = self::getField($addressFields, 600);
		$addressData['building'] = self::extractBuilding($addressFields);
		$addressData['structure'] = self::extractStructure($addressFields);
		$addressData['registrationType'] = "const";

		// Логируем результат
		Logs\File::AddMessage($addressData, "addressData", LOG_BP);

		return $addressData;
	}
	private static function getAddressData($contactId, $kladr): array
	{
		$addressList = self::getAddressList($contactId);
        // Логируем результат
        Logs\File::AddMessage($addressList, "addressList", LOG_BP);

		$registrationAddress = self::extractRegistrationAddress($addressList);
        // Логируем результат
        Logs\File::AddMessage($registrationAddress, "registrationAddress", LOG_BP);

		if ($kladr) {
			return self::getKladrData($registrationAddress);
		} else {
			return self::getSimpleAddressData($registrationAddress);
		}
	}
	public static function getRegistrationDataById($contactsIdList, bool $organization = false, $kladr = false): array
	{
		if(!$organization)
		{
			if (is_array($contactsIdList['ids']))
			{
				$contactsIds = array_unique($contactsIdList['ids']);
			} else
			{
				$contactsIds = [$contactsIdList['ids']];
			}

			$result = [];

			foreach ($contactsIds as $contactId)
			{
				$addressData = self ::getAddressData($contactId, $kladr);
				if (isset($addressData['errorFields']))
				{
					$result['errorFields'][$contactsIdList['fullName']] = $addressData['errorFields'];
				} else
				{
					$result[$contactId]['registrationAddressFias'] = $addressData;
				}
			}
		} else {
			if (is_array($contactsIdList['ids']))
			{
				foreach ($contactsIdList['ids'] as $_contactId) {
					$_contactsIds[] = $_contactId['id'];
				}
				$contactsIds = array_unique($_contactsIds);
			} else
			{
				foreach ($contactsIdList['ids'] as $_contactId) {
					$contactsIds = [$_contactId['id']];
				}
			}

			$result = [];

			foreach ($contactsIds as $contactId)
			{
				$addressData = self ::getAddressData($contactId, $kladr);
				if (isset($addressData['errorFields']))
				{
					$result['errorFields'][$contactsIdList['fullName']] = $addressData['errorFields'];
				} else
				{
					$result[$contactId]['registrationAddressFias'] = $addressData;
				}
			}
		}

		return $result;
	}
	//endregion getRegistrationDataById

	//region getInfo
	public static function prepareOvkFields($data): array
	{
		return [
			'UF_CRM_COMPANY_OVK_CHECKDOMAIN' => $data["checkDomain"], // Проверка домена
			'UF_CRM_COMPANY_OVK_CHECK_COMPANY_INFO' => $data["checkCompanyInfo"], // Проверка информации о компании
			'UF_CRM_COMPANY_OVK_CHECK_LICENSES' => $data["checkLicenses"], // Проверка лицензий
			'UF_CRM_COMPANY_OVK_IS_IN_TERRORIST_LIST' => $data["isInTerroristList"], // Наличие в списке террористов
			'UF_CRM_COMPANY_OVK_IS_IN_MVK_LIST' => $data["isInMvkList"], // Наличие в списке МВК
			'UF_CRM_COMPANY_OVK_IS_IN_OMU_LIST' => $data["isInOmuList"], // Наличие в списке ОМУ
			'UF_CRM_COMPANY_OVK_IS_IN_STRATEGIC_LIST' => $data["isInStrategicList"], // Наличие в стратегическом списке
			'UF_CRM_COMPANY_OVK_IS_IN_OPK_ULS' => $data["isInOpkUls"], // Наличие в списке ОПК УЛС
			'UF_CRM_COMPANY_OVK_IS_IN_SANCTION_LIST' => $data["isInSanctionList"], // Наличие в санкционном списке
			'UF_CRM_COMPANY_OVK_IS_IN_PEP_LIST' => $data["isInPepList"], // Наличие в списке ПЭП
			'UF_CRM_COMPANY_OVK_IS_IN_764_LIST' => $data["isIn764List"], // Наличие в списке 764
			'UF_CRM_COMPANY_OVK_IS_IN_FINANCIAL_PYRAMYDE' => $data["isInFinancialPyramyde"], // Наличие в финансовой пирамиде
			'UF_CRM_COMPANY_OVK_IS_IN_ILLEGAL_CREDITOR' => $data["isInIllegalCreditor"], // Наличие в списке нелегальных кредиторов
			'UF_CRM_COMPANY_OVK_RESULT' => $data["result"], // Результат
		];
	}
	public static function getInfo($entityTypeId, $elementID, $kladr = false)
	{
		$companyId = self::getCompanyIdBySmart($entityTypeId, $elementID);
		$contactsIdList = self::getContactListByCompanyId($companyId);

		if ($errorResponse = self::checkForErrors($contactsIdList)) {
            if ($entityTypeId == 134) {
                self::moveToStage($entityTypeId, $elementID, 'DT134_104:UC_8397AG');
            }
            \CRest ::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $elementID,
                    "ENTITY_TYPE" => 'DYNAMIC_'.$entityTypeId,
                    "COMMENT" => "[b]".json_encode($errorResponse, JSON_UNESCAPED_UNICODE)."[/b]"
                ]
            ]);
			return $errorResponse;
		}

		$result = self::initializeResult($companyId, $contactsIdList);

		// Логируем результат
		Logs\File::AddMessage($result, "initializeResult", LOG_BP);

		if ($entityTypeId == 149) {
			$result['isFirstRequest'] = true;
		}

		$passportInfo = self::getPassportInfo($contactsIdList, $result['organization'], $result['isFirstRequest']);

        // Логируем результат
        Logs\File::AddMessage($passportInfo, "result +PassportInfo", LOG_BP);

		if ($errorResponse = self::checkForErrors($passportInfo)) {
            if ($entityTypeId == 134) {
                self::moveToStage($entityTypeId, $elementID, 'DT134_104:UC_8397AG');
            }
            \CRest ::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $elementID,
                    "ENTITY_TYPE" => 'DYNAMIC_'.$entityTypeId,
                    "COMMENT" => "[b]".json_encode($errorResponse, JSON_UNESCAPED_UNICODE)."[/b]"
                ]
            ]);
			return $errorResponse;
		}

		// Логируем результат
		//Logs\File::AddMessage($passportInfo, "passportInfo", LOG_BP);

		$regData = self::getRegistrationData($contactsIdList, $result['organization'], $kladr);

        // Логируем результат
        Logs\File::AddMessage($regData, "result +RegistrationData", LOG_BP);

		if ($errorResponse = self::checkForErrors($regData)) {
            if ($entityTypeId == 134) {
                self::moveToStage($entityTypeId, $elementID, 'DT134_104:UC_8397AG');
            }
            \CRest ::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $elementID,
                    "ENTITY_TYPE" => 'DYNAMIC_'.$entityTypeId,
                    "COMMENT" => "[b]".json_encode($errorResponse, JSON_UNESCAPED_UNICODE)."[/b]"
                ]
            ]);
			return $errorResponse;
		}

		$result['payload'] = self::mergeData($passportInfo, $regData);

		// Логируем результат
		//Logs\File::AddMessage($result['payload'], "payload", LOG_BP);

		if ($entityTypeId !== 149) {
			$bitrixData = self::getBitrixData($entityTypeId, $elementID, $companyId, $contactsIdList, $result['organization']);


            // Логируем результат
            Logs\File::AddMessage($bitrixData, "result +bitrixData", LOG_BP);

            if ($errorResponse = self::checkForErrors($bitrixData)) {
                if ($entityTypeId == 134) {
                    self::moveToStage($entityTypeId, $elementID, 'DT134_104:UC_8397AG');
                }
                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $elementID,
                        "ENTITY_TYPE" => 'DYNAMIC_'.$entityTypeId,
                        "COMMENT" => "[b]".json_encode($errorResponse, JSON_UNESCAPED_UNICODE)."[/b]"
                    ]
                ]);
				return $errorResponse;
			}
			$result['bitrixData'] = $bitrixData;
		}

		// Логируем результат
		Logs\File::AddMessage($result, "result body", LOG_BP);

		return json_encode($result, JSON_UNESCAPED_UNICODE);
	}
	public static function getInfoByCompanyId($companyId, $kladr = true) {
		// Получаем данные по контактам компании
		$contactsIdList = self::getContactListByCompanyId($companyId);
		if ($errorResponse = self::checkForErrors($contactsIdList)) {
			return $errorResponse;
		}

		// Инициализация результата
		$result = self::initializeResult($contactsIdList);
		$result['isFirstRequest'] = true; // Является ли это первым запросом

		// Получаем паспортную информацию
		$passportInfo = self::getPassportInfo($contactsIdList, $result['organization'], $result['isFirstRequest']);
		if ($errorResponse = self::checkForErrors($passportInfo)) {
			return $errorResponse;
		}

		// Получаем регистрационные данные
		$regData = self::getRegistrationData($contactsIdList, $result['organization'], $kladr);
		if ($errorResponse = self::checkForErrors($regData)) {
			return $errorResponse;
		}

		// Объединение данных
		$result['payload'] = self::mergeData($passportInfo, $regData);

		// Логируем результат
		Logs\File::AddMessage($result, "result", LOG_BP);

		// Возвращаем результат в формате JSON
		return json_encode($result, JSON_UNESCAPED_UNICODE);
	}
	public static function getOvkByCompanyId($companyId) {
		$timeData = Logs\TimeData::start();
		$point = "BX_SE";
		$emulation = false;
		$objectData = self::getItemForPostById(\CCrmOwnerType::Company, $companyId);
		$objectDataTitle = $objectData['ITEM_TITLE'];
		$objectData['ITEM_TITLE'] = "Получение чек-листа ОВК по {$objectDataTitle}";

		$companyData = self::getCompanyData($companyId);
		$inn = $companyData['UF_CRM_6433D7C925893'];
		$jsonData = [];

		$url = 'https://internal.seller-capital.ru/GetOvk?inn='.$inn;

		if(defined("TOKEN_KEY")) {
			$jsonResponse = \KPLab\Curl::get_LK(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);
		}
		// Логируем результат
		Logs\File::AddMessage($jsonResponse, "jsonResponse", LOG_BP);

		$fields = self::prepareOvkFields(json_decode($jsonResponse['success'],true));

		$fields['UF_CRM_DATE_CHEK_OVK'] = date('d.m.Y H:i:s',strtotime('now'));
		Logs\File::AddMessage($fields, "fields", LOG_BP);

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
		$item = $factory->getItem($companyId);
		$item->setFromCompatibleData($fields);
		$operation = $factory->getUpdateOperation($item);
		$operation->disableCheckAccess()->enableCheckWorkflows()->enableCheckRequiredUserFields()->enableAfterSaveActions()->enableBizProc()->enableAutomation();

		$operationResult = $operation->launch();

		if ($operationResult->isSuccess()) {
			return "Данные ovk успешно сохранились";
		} else {
			return $operationResult->getErrorMessages();
		}
	}
	//endregion getInfo

	public static function createRequest($jsonData, $objectData, $timeData, $point = "", $EnrichViaBuffer = false, $emulation = false, $test = false) {

        if(!$test) {
            if(!$EnrichViaBuffer) {
                $url = 'https://internal.seller-capital.ru/Bitrix/CreateBalancePlatformRequest';
            }
            else {
                $url = 'https://internal.seller-capital.ru/Bitrix/EnrichViaBuffer';
            }
        } else {
            if(!$EnrichViaBuffer) {
                $url = 'https://internal.dev.seller-capital.ru/Bitrix/CreateBalancePlatformRequest';
            }
            else {
                $url = 'https://internal.dev.seller-capital.ru/Bitrix/EnrichViaBuffer';
            }
        }

		if(defined("TOKEN_KEY")) {

            // Логируем результат
            Logs\File::AddMessage([TOKEN_KEY,$url,$objectData,$timeData,$point,$emulation], "data_post_v2", LOG_BP);
            // Логируем результат
            Logs\File::AddMessage($jsonData, "jsonData_post_v2", LOG_BP);

            $methodName = __FUNCTION__;
            $controllerName = static::class;

            $headersRequest = array(
                "key" => TOKEN_KEY,
                "Content-Type" => "application/json"
            );
            $logData = [
                'objectData' => $objectData,
                'methodName' => $methodName,
                'controllerName' => $controllerName,
                'method' => 'POST',
                'timeData' => $timeData,
                'point' => $point
            ];
            $jsonResponse = \KPLab\ApiRequest::sendRequest($url, 'POST', $headersRequest, $jsonData, $logData);
            //return $jsonResponse;


			//$jsonResponse = \KPLab\Curl::post_v2(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);

            Logs\File::AddMessage($jsonResponse, "jsonResponse", LOG_BP);
            return $jsonResponse;
		}

		return null;
	}
	public static function getRequest($jsonData, $objectData, $timeData, $point = "", $EnrichViaBuffer = false,
	                                $emulation = false) {

		$url = 'https://internal.seller-capital.ru/GetOvk';
		if(defined("TOKEN_KEY")) {
			$jsonResponse = \KPLab\Curl::get_LK(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);
			return $jsonResponse;
		}

		return null;
	}

	//region NEW methods
	const ENTITY_TYPE_ID = 149;
	const GUARANTOR_FIELDS = [
		'UF_CRM_49_1683882204',
		'UF_CRM_49_1683882348',
		'UF_CRM_49_1693180767',
		'UF_CRM_49_1693180780',
		'UF_CRM_49_1693180790',
		'UF_CRM_49_1693180802'
	];
	const INDIVIDUAL_FIELDS = [
		'innFl' => 'RQ_INN',
		"passportSeries" => 'RQ_IDENT_DOC_SER',
		"passportNumber" => 'RQ_IDENT_DOC_NUM',
		"surname" => 'RQ_LAST_NAME',
		"name" => 'RQ_FIRST_NAME',
		"patronymic" => 'RQ_SECOND_NAME',
		"birthday" => 'UF_CRM_1684493639',
		"birthPlace" => 'UF_CRM_1647929611',
		"passportIssuer" => 'RQ_IDENT_DOC_ISSUED_BY',
		"passportIssuerCode" => 'RQ_IDENT_DOC_DEP_CODE',
		"passportIssuedAt" => 'RQ_IDENT_DOC_DATE',
        "clientType" => 'clientType'
	];
	const CONSENT_DATE_FIELDS = [
		'company' => 'UF_CRM_1700138022',
		'contact' => 'UF_CRM_1700137692'
	];
	public static function getInfoGuarantors($smartId)
	{
		if (!Loader::includeModule('crm')) {
			throw new \Exception("Модуль CRM не загружен");
		}

		$result = [];

		$entityTypeId = self::ENTITY_TYPE_ID;
		$factory = Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($smartId);

		if ($item) {
			$itemData = $item->getData();

			//Logs\File::AddMessage($itemData,"itemData",LOG_BP);

			foreach (self::GUARANTOR_FIELDS as $field) {

				if (isset($itemData["{$field}"]) && $itemData["{$field}"] !== "") {

					$guarantorValue = $itemData["{$field}"];
					$guarantorData = self::getGuarantorData($guarantorValue);
					//Logs\File::AddMessage($guarantorData,"guarantorData",LOG_BP);
					if ($guarantorData) {
						$result[] = $guarantorData;
					}
				}
			}
		}

		return $result;
	}
	private static function getGuarantorData(string $guarantorValue): ?array
	{
		if (preg_match('/CO_(\d+)$/', $guarantorValue, $matches)) {
			$guarantorId = (int)$matches[1];
			$guarantorType = 'COMPANY';
		} elseif (preg_match('/C_(\d+)$/', $guarantorValue, $matches)) {
			$guarantorId = (int)$matches[1];
			$guarantorType = 'CONTACT';
		} else {
			return null;
		}

		// Получение реквизитов поручителя
		$requisite = self::_getRequisiteData($guarantorId, $guarantorType);

		if (!$requisite) {
			return null;
		}

        //$guarantor['guarantorType'] = $guarantorType;
		$guarantor['not'] = 'organization';
		$guarantor['ids'] = $guarantorId;
		$guarantor['fullName'] = $requisite['RQ_LAST_NAME'] . " " . $requisite['RQ_FIRST_NAME'] . " " .$requisite['RQ_SECOND_NAME'];

		$regData = self::getRegistrationDataById($guarantor, false,false);

		if ($regData['errorFields'])
			return $regData;


		$data = [];
		foreach (self::INDIVIDUAL_FIELDS as $field => $fieldValue) {
			if($fieldValue == 'UF_CRM_1684493639') {
				$requisite[$fieldValue] = date("d.m.Y", strtotime($requisite[$fieldValue]));
			}
			$data[$guarantorId][$field] = $requisite[$fieldValue] ?? '';
		}

		$data[$guarantorId]['creditAmount'] = 0;
		$data[$guarantorId]['bkiReportConsentDate'] = self::getConsentDate($guarantorId, $guarantorType);
		$data[$guarantorId]['bkiReportDealDate'] = self::getConsentDate($guarantorId, $guarantorType);
		$data[$guarantorId]['bkiReportBirthPlaceCode'] = '643';
		$data[$guarantorId]['bkiReportStateCode'] = '643';
		$data[$guarantorId]['guarantorType'] = $guarantorType;

		foreach ($data as $i => $p)
		{
			$result = array_merge($p, $regData[$i]);
		}

		return $result;
	}
	private static function getConsentDate(int $guarantorId, string $guarantorType): string
	{
		$consentDateField = $guarantorType === 'CONTACT' ?
			self::CONSENT_DATE_FIELDS['contact'] :
			self::CONSENT_DATE_FIELDS['company'];

		if($guarantorType === 'CONTACT') {
			$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
			$item = $factory->getItem($guarantorId);
			$guarantor = $item->getData();
		} else {
			$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Company);
			$item = $factory->getItem($guarantorId);
			$guarantor = $item->getData();
		}

		return $guarantor[$consentDateField] ?? '';
	}
	private static function _getRequisiteData(int $guarantorId, string $guarantorType): ?array
	{
		$requisite = new EntityRequisite();
		$requisites = $requisite->getList([
			'filter' => [
				'=ENTITY_TYPE_ID' => $guarantorType === 'CONTACT' ? \CCrmOwnerType::Contact : \CCrmOwnerType::Company,
				'=ENTITY_ID' => $guarantorId,
			],
			'select' => ['*','UF_*']
		])->fetchAll();

		if (empty($requisites)) {
			return null;
		}

        if($guarantorType === 'COMPANY') {
            $factory = Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $item = $factory->getItem($guarantorId);
            if ($item) {
                $itemData = $item->getData();
                $requisites[0]['clientType'] = $itemData['UF_CRM_1684145100226'];
                return $requisites[0];
            }
        } else {
            return $requisites[0];
        }
	}
	public static function createGuarantorRequest(
		$jsonData,
		$objectData,
		$timeData,
		$point = "",
		$legalEntityInn = null,
		$emulation = false
	) {

		//$url = "https://api.seller-capital.ru/Guarantor/AddOrUpdatePerson?legalEntityInn={$legalEntityInn}";
        $url = "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";
		Logs\File::AddMessage([$url, $jsonData, $objectData,$timeData,$point,$emulation],"entityTypeId, elementID, companyId",
			LOG_BP);
		if(defined("TOKEN_KEY")) {
			$jsonResponse = \KPLab\Curl::post_v2(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);
			return $jsonResponse;
		}

		return null;
	}
    public static function AddOrUpdateGuarantorsRequest(
        $jsonData,
        $objectData,
        $timeData,
        $point = "",
        $legalEntityInn = null,
        $emulation = false
    ) {

        // Новый URL API
        $url = "https://internal.seller-capital.ru/Bitrix/AddOrUpdateGuarantors";
        $data = [
            "beneficiarInn" => $legalEntityInn,
            "guarantorsLE" => [], // Поручители ООО
            "guarantorsFL" => []  // Поручители ФЛ/ИП
        ];

        Logs\File::AddMessage([$url, $jsonData, $objectData,$timeData,$point,$emulation],"entityTypeId, elementID, companyId",
            LOG_BP);
        if(defined("TOKEN_KEY")) {
            $jsonResponse = \KPLab\Curl::post_v2(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);
            return $jsonResponse;
        }

        return null;
    }
	//endregion

    private static function moveToStage($entityTypeId, $elementID, $stageId): void
    {
        // Получаем фабрику для работы с элементами указанного типа
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

        if (!$factory) {
            throw new \Exception("Фабрика не найдена для entityTypeId: {$entityTypeId}");
        }

        // Получаем элемент по его ID
        $item = $factory->getItem($elementID);

        if ($item) {
            // Устанавливаем новую стадию
            $item->setStageId($stageId);

            // Сохраняем изменения
            $operation = $factory->getUpdateOperation($item);
            $operation->disableCheckAccess()->enableCheckWorkflows()->enableCheckRequiredUserFields()->enableAfterSaveActions()->enableBizProc()->enableAutomation();
            $result = $operation->launch();

            // Логируем результат
            Logs\File::AddMessage($result->isSuccess() ? 'Stage updated successfully' : $result->getErrorMessages(), "moveToStageWithFactory result", LOG_BP);

            // Проверка успешности перемещения
            if (!$result->isSuccess()) {
                Logs\File::AddMessage($result->getErrorMessages(), "moveToStageWithFactory error", LOG_BP);
            }
        } else {
            Logs\File::AddMessage("Item not found", "moveToStageWithFactory error", LOG_BP);
        }
    }

	/*
			 *
			 * ClientName - Наименование клиента (-- Полное наименование -- компании)
			 * SupportDepartmentEmployee - Сотрудник отдела сопровождения (--- Ответственный --- СЗ)
			 * ClientGroupIndicator - Признак группы клиента (--- компания ---)
			 * GuarantorCount - Кол-во поручителей ( --- СЗ --- UF_CRM_56_1686298851 / UF_CRM_56_1686298912 / UF_CRM_56_1693179732 / UF_CRM_56_1693179748 / UF_CRM_56_1693179765 / UF_CRM_56_1693179777 )
			 * Mpcount - Кол-во маркетплейсов (--- компания --- UF_CRM_1706982719 ---)
			 * ContractDate - Дата договора (--- дата первого ДЗ  --- UF_CRM_15_1679925201 ---)
			 * LastTrancheDate - Дата выдачи последнего транша (--- дата крайнего ДЗ  --- UF_CRM_15_1679925201 ---)
			 * TrancheTerm - Срок последнего транша (--- срок крайнего ДЗ  --- UF_CRM_15_SS_SROK ---)
			 * TrancheRate - Ставка последнего транша (проценты) (--- ставка крайнего ДЗ  --- UF_CRM_15_SS_STAVKA ---)
			 * ApprovedLimit - Одобренный лимит (суммарно КПК и МКК) (--- Лимит возобновляемой кредитной линии --- СЗ ) UF_CRM_56_1684744846487
			 * Portfolio - Портфель (суммарно КПК и МКК) UF_CRM_56_1684744827969
			 * Balance - Баланс (сумммарно КПК и МКК) UF_CRM_56_1684744875738
			 *
			 * */
}

