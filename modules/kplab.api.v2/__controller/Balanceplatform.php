<?php namespace KPLab;

use Bitrix\Crm\EntityRequisite;
use Bitrix\Crm\Service\Container;
use \Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Loader;
use \KPLab\Authentication;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Web\JWT;
use \KPLab\Logs;

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

		/*
		if(!$organization) {
			$id = $contactsIds;
			$resultAddressList = \CRest::call('crm.address.list', array(
				'filter' => array('ANCHOR_ID' => $id),
				'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','LOC_ADDR_ID')
			));
			$resAddrList = $resultAddressList['result'];

			foreach ($resAddrList as $addrItem) {
				if($addrItem['TYPE_ID'] == 4) {
					$Address = new \Bitrix\Location\Controller\Address;
					$resAddress = $Address->findById($addrItem['LOC_ADDR_ID'])['fieldCollection'];
				}
			}

			$arRegion = [];
			$arDistrict = [];
			$arCity = [];
			$arStreet = [];
			$arBuilding = [];
			$arOther = [];
			$_structure = null;
			$_building = null;

			Logs\File::AddMessage($resAddress,"address {$id} ИП",LOG_BP);

			if($kladr) {
				foreach ($resAddress as $n => $item)
				{
					//region
					if ($n == 200 && $item !== '')
					{
						$query = explode(" ", $item)[0];
						$contentType = 'region';
						$curlOptions = [
							CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}",
							CURLOPT_HEADER => false,
							CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
							CURLOPT_RETURNTRANSFER => true
						];
						$ch = curl_init();
						curl_setopt_array($ch, $curlOptions);
						$arRegion = json_decode(curl_exec($ch), true)['result'][1];

					}
					//district
					elseif ($n == 210 && $item !== '')
					{
						$query = explode(" ", $item)[0];
						$contentType = 'district';
						$regionId = $arRegion['id'];

						$curlOptions = [
							CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}&regionId={$regionId}",
							CURLOPT_HEADER => false,
							CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
							CURLOPT_RETURNTRANSFER => true
						];
						$ch = curl_init();
						curl_setopt_array($ch, $curlOptions);
						$arDistrict = json_decode(curl_exec($ch), true)['result'][1];
					}
					//city
					elseif ($n == 300 && $item !== '')
					{
						$queryCity = "";
						$query = explode(" ", $item);
						array_pop($query);
						foreach ($query as $city)
						{
							$queryCity .= $city . " ";
						}
						$queryCity = trim($queryCity);

						$contentType = 'city';
						$regionId = $arRegion['id'];
						$districtId = $arDistrict['id'];
						$regionGuid = $arRegion['guid'];
						$districtGuid = $arDistrict['guid'];
						$curlOptions = [
							CURLOPT_URL => "https://kladr-api.ru/api.php?withParent=1&query={$queryCity}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}",
							CURLOPT_HEADER => false,
							CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
							CURLOPT_RETURNTRANSFER => true
						];
						$ch = curl_init();
						curl_setopt_array($ch, $curlOptions);

						$arCityResult = json_decode(curl_exec($ch), true)['result'];

						//Logs\File ::AddMessage($arCityResult, "arCityResult {$id} ИП", LOG_BP);

						if ($arCityResult === null || $arCityResult[0]['id'] == 'Free')
						{
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?withParent=1&query={$queryCity}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);

							$arCityResult = json_decode(curl_exec($ch), true)['result'];

							Logs\File ::AddMessage($arCityResult, "arCityResult 2 {$id} ИП", LOG_BP);
						}

						foreach ($arCityResult as $k => $cityResponse)
						{
							$cityName = trim($cityResponse['name'] . " " . $cityResponse['typeShort']);

							if ($cityName == $item)
							{
								if ($cityResponse['parentGuid'] == "")
								{
									$arCity = $cityResponse;
								} elseif ($cityResponse['parentGuid'] !== $regionGuid)
								{
									$parents = $cityResponse['parents'];
									foreach ($parents as $parent)
									{
										if ($parent['contentType'] == "region" && $parent['guid'] == $regionGuid)
										{
											$arCity = $cityResponse;
											break;
										} elseif ($parent['contentType'] == "regionOwner" && $parent['guid'] == $regionGuid)
										{
											$arCity = $cityResponse;
											break;
										}
									}
								} elseif ($cityResponse['parentGuid'] == $regionGuid)
								{
									$arCity = $cityResponse;
									break;
								}
							}
						}

					}
					//street
					elseif ($n == 340 && $item !== '')
					{
						$arStreetResult = null;
						$queryStreet = "";
						$contentType = 'street';
						$regionId = $arRegion['id'];
						$districtId = $arDistrict['id'];
						$cityId = $arCity['id'];
						$cityGuid = $arCity['guid'];
						$query = explode(" ", $item);
						$i = 1;
						$c = count($query);
						Logs\File ::AddMessage([$regionId, $districtId, $cityId, $cityGuid], "regionId, districtId, cityId, cityGuid ИП street", LOG_BP);
						while ($i <= $c)
						{
							$queryStreet .= $query[$i - 1] . " ";
							Logs\File ::AddMessage($queryStreet, "queryStr ИП Street", LOG_BP);
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?withParent=1&query={$queryStreet}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);

							$arStreetResult = json_decode(curl_exec($ch), true)['result'];

							if ($arStreetResult !== null) break 1;
							$i++;
						}

						if ($arStreetResult === null)
						{
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?withParent=1&query={$queryStreet}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);

							$arStreetResult = json_decode(curl_exec($ch), true)['result'];

						}
						Logs\File ::AddMessage($arStreetResult, "arStreetResult ИП Street", LOG_BP);

						if(count($arStreetResult) == 1) {
							$arStreet['name'] = trim($queryStreet);
						} else {
							foreach ($arStreetResult as $k => $streetResponse)
							{
								$strName = trim($streetResponse['name'] . " " . $streetResponse['typeShort']);
								Logs\File ::AddMessage([$strName, $item, $streetResponse['parentGuid'], $cityGuid], "strName item parentGuid cityGuid ИП Street", LOG_BP);

								if ($strName == $item)
								{

									Logs\File ::AddMessage([$strName, $item], "strName и item равны ИП Street", LOG_BP);
									if ($streetResponse['parentGuid'] !== $cityGuid)
									{
										Logs\File ::AddMessage([$streetResponse['parentGuid'], $cityGuid], "parentGuid != cityGuid ИП Street", LOG_BP);
										$parents = $streetResponse['parents'];
										foreach ($parents as $parent)
										{
											if ($parent['contentType'] == "city" && $parent['guid'] == $cityGuid)
											{
												$arStreet = $streetResponse;
												break;
											} elseif ($parent['contentType'] == "cityOwner" && $parent['guid'] == $cityGuid)
											{
												$arStreet = $streetResponse;
												break;
											}
										}
									} elseif ($streetResponse['parentGuid'] == $cityGuid)
									{
										Logs\File ::AddMessage([$streetResponse['parentGuid'], $cityGuid], "parentGuid = cityGuid ИП Street", LOG_BP);
										$arStreet = $streetResponse;
										break;
									}
								}
							}
						}
					}

					//building
					elseif ($n == 400 && $item !== '')
					{
						$query = explode(" ", $item);
						if(count($query) > 1) {
							array_pop($query);
							//Logs\File::AddMessage($query,"query2 {$id} ИП",LOG_BP);
						}
						foreach ($query as $str)
						{
							$queryHouse .= $str . " ";
						}
						$queryHouse = trim($queryHouse);
						// Logs\File::AddMessage($queryHouse,"queryHouse {$id} ИП",LOG_BP);

						//с проверкой

						//без проверки
						if (str_contains($queryHouse, 'корпус'))
						{
							$arStrStructure = explode("корпус", $queryHouse);
							$_structure = $arStrStructure[1];
						}
						if (str_contains($queryHouse, 'строение'))
						{
							$arStrBuilding = explode("строение", $queryHouse);
							$_building = $arStrBuilding[1];
						}

					}

					elseif ($n == 600 && $item !== '')
					{
						$arOther[] = $item;
					}
				}
				if(empty($arRegion)) {
					$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
				}
				elseif(empty($arCity) && $arRegion['name'] !== 'Москва'){
					$result['errorFields'][$contactsIdList['fullName']]['city'] = 'Нет данных о Городе';
				}
				elseif(empty($arStreet))
				{
					$result['errorFields'][$contactsIdList['fullName']]['street'] = 'Нет данных об Улице';
					// } elseif(empty($arBuilding)){ $result['errorFields'][$contactsIdList['fullName']]['house'] = 'Нет данных о Номере дома'; }
				}
				else {
					$res['city'] = $arCity['name'];
					$res['flat'] = $arOther[0];
					$res['house'] = $queryHouse;
					$res['region'] = intval(mb_strimwidth($arRegion['id'],0,2,""));
					$res['street'] = $arStreet['name'];
					//$res['district'] = $arDistrict;
					$res['building'] = $_building;
					$res['structure'] = $_structure;
					//$res['other'] = $arOther;
					$res['regionKladrId'] = $arRegion['id'];
					$res['registrationType'] = "const";

					$result[$id]['registrationAddressFias'] = $res;
				}
			}
			else {
				foreach ($resAddress as $n => $item)
				{
					if ($n == 200 && $item !== '')
					{
						$query = explode(" ", $item)[0];
						$contentType = 'region';
						$curlOptions = [
							CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}",
							CURLOPT_HEADER => false,
							CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
							CURLOPT_RETURNTRANSFER => true
						];
						$ch = curl_init();
						curl_setopt_array($ch, $curlOptions);
						$arRegion = json_decode(curl_exec($ch), true)['result'][1];
					}

					elseif ($n == 300 && $item !== '')
					{
						$queryCity = "";
						$query = explode(" ", $item);
						array_pop($query);
						foreach ($query as $city)
						{
							$queryCity .= $city . " ";
						}
						$cityName = trim($queryCity);
					}

					elseif ($n == 340 && $item !== '')
					{
						$arStreetResult = null;
						$queryStreet = "";
						$query = explode(" ", $item);
						array_pop($query);
						foreach ($query as $street)
						{
							$queryStreet .= $street . " ";
						}
						$streetName = trim($queryStreet);
					}

					elseif ($n == 400 && $item !== '')
					{
						$query = explode(" ", $item);
						if(count($query) > 1) {
							array_pop($query);
							//Logs\File::AddMessage($query,"query2 {$id} ИП",LOG_BP);
						}
						foreach ($query as $str)
						{
							$queryHouse .= $str . " ";
						}
						$houseName = trim($queryHouse);

						//без проверки
						if (str_contains($houseName, 'корпус'))
						{
							$arStrStructure = explode("корпус", $houseName);
							$_structure = $arStrStructure[1];
						}
						if (str_contains($houseName, 'строение'))
						{
							$arStrBuilding = explode("строение", $houseName);
							$_building = $arStrBuilding[1];
						}

					}
				}
				if(empty($arRegion)) {
					$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
				}
				else {
					$res['city'] = $cityName;
					$res['flat'] = $arOther[0];
					$res['house'] = $houseName;
					$res['region'] = intval(mb_strimwidth($arRegion['id'], 0, 2, ""));
					$res['street'] = $streetName;
					$res['building'] = $_building;
					$res['structure'] = $_structure;
					$res['regionKladrId'] = $arRegion['id'];
					$res['registrationType'] = "const";

					$result[$id]['registrationAddressFias'] = $res;
				}
			}
		}
		else {
			//Logs\File::AddMessage($contactsIds,"contactsIds ООО",LOG_BP);
			if(count($contactsIds) == 1) {

				$idd = $contactsIds[0];

				$resultAddressList = \CRest::call('crm.address.list', array(
					'filter' => array('ANCHOR_ID' => $idd),
					'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','LOC_ADDR_ID')
				));

				$resAddrList = $resultAddressList['result'];

				foreach ($resAddrList as $addrItem) {
					if($addrItem['TYPE_ID'] == 4) {
						$Address = new \Bitrix\Location\Controller\Address;
						$resAddress = $Address->findById($addrItem['LOC_ADDR_ID'])['fieldCollection'];
					}
				}
				$arRegion = [];
				$arDistrict = [];
				$arCity = [];
				$arStreet = [];
				$arBuilding = [];
				$arOther = [];
				$result = [];
				$structure = null;
				$_building = null;

				if($kladr)
				{
					foreach ($resAddress as $n => $item)
					{
						if ($n == 200 && $item !== '')
						{
							$queryRegion = explode(" ", $item)[0];
							$contentType = 'region';
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryRegion}&contentType={$contentType}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arRegion = json_decode(curl_exec($ch), true)['result'][1];
						}
						elseif ($n == 210 && $item !== '')
						{
							$queryDistrict = explode(" ", $item)[0];
							$contentType = 'district';
							$regionId = $arRegion['id'];
							//$districtId = $arDistrict['id'];
							//$cityId = $arCity['id'];
							//$streetId = $arStreet['id'];

							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryDistrict}&contentType={$contentType}&regionId={$regionId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arDistrict = json_decode(curl_exec($ch), true)['result'][1];
						}
						elseif ($n == 300 && $item !== '')
						{
							$query = explode(" ", $item);

							array_pop($query);
							foreach ($query as $city)
							{
								$queryCity .= $city . " ";
							}
							$queryCity = trim($queryCity);

							$contentType = 'city';
							$regionId = $arRegion['id'];
							$districtId = $arDistrict['id'];
							//$cityId = $arCity['id'];
							//$streetId = $arStreet['id'];

							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryCity}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arCityResult = json_decode(curl_exec($ch), true)['result'];

							//Logs\File::AddMessage($arCityResult,"arCityResult ООО",LOG_BP);

							foreach ($arCityResult as $k => $cityResponse)
							{
								$cityName = trim($cityResponse['name']);
								if ($cityName == $queryCity)
								{
									$arCity = $cityResponse;
									break;
								}
							}

						}
						elseif ($n == 340 && $item !== '')
						{
							$queryStr = "";
							$query = explode(" ", $item);
							array_pop($query);
							foreach ($query as $str)
							{
								$queryStr .= $str . " ";
							}
							$queryStr = trim($queryStr);


							//Logs\File::AddMessage($queryStr,"queryStr ООО",LOG_BP);

							$contentType = 'street';
							$regionId = $arRegion['id'];
							$districtId = $arDistrict['id'];
							$cityId = $arCity['id'];
							//$streetId = $arStreet['id'];

							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryStr}&oneString=1&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);

							$arStreetResult = json_decode(curl_exec($ch), true)['result'];

							if ($arStreetResult === null)
							{
								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryStr}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);

								$arStreetResult = json_decode(curl_exec($ch), true)['result'];
							}

							//Logs\File::AddMessage("https://kladr-api.ru/api.php?query={$queryStr}&oneString=1&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}","query ООО",LOG_BP);

							//Logs\File::AddMessage($arStreetResult,"arStreetResult ООО",LOG_BP);

							foreach ($arStreetResult as $k => $streetResponse)
							{

								$strName = trim($streetResponse['name']);

								if ($strName == $queryStr)
								{
									$arStreet = $streetResponse;
									break;
								}
							}

						}
						elseif ($n == 400 && $item !== '')
						{
							$queryHouse = [];
							$query = explode(" ", $item);
							array_pop($query);
							foreach ($query as $str)
							{
								$queryHouse[$idd] .= $str . " ";
							}
							$queryHouse[$idd] = trim($queryHouse[$idd]);

							//с проверкой

							//без проверки
							if (str_contains($queryHouse[$idd], 'корпус'))
							{
								$arStrStructure = explode("корпус", $queryHouse[$idd]);
								$_structure = $arStrStructure[1];
							}
							if (str_contains($queryHouse[$idd], 'строение'))
							{
								$arStrBuilding = explode("строение", $queryHouse[$idd]);
								$_building = $arStrBuilding[1];
							}


						}
						elseif ($n == 410 && $item !== '' && empty($arStreet) && empty($arBuilding))
						{
							$streetAndBuilding = explode(", ", $item);

							$str_street = $streetAndBuilding[0];
							$street = explode(" ", $str_street);

							$cStrStreet = count($street) - 1;

							$strStreetG = "";
							for ($i = 0; $i < $cStrStreet; $i++)
							{
								$strStreetG = $strStreetG . ' ' . $street[$i];
							}

							$contentTypeS = 'street';
							$regionId = $arRegion['id'];
							$districtId = $arDistrict['id'];
							$cityId = $arCity['id'];

							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$strStreetG}&contentType={$contentTypeS}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arStreet = json_decode(curl_exec($ch), true)['result'][1];


							$str_building = $streetAndBuilding[1];
							$building = explode(" ", $str_building);

							$cStrBuilding = count($building) - 1;

							$strBuildingG = "";
							for ($i = 0; $i < $cStrBuilding; $i++)
							{
								$strBuildingG = $strBuildingG . ' ' . $building[$i];
							}
							$contentTypeB = 'building';
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$strBuildingG}&contentType={$contentTypeB}&regionId={$regionId}&districtId={$districtId}&cityId={$arCity['id']}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arBuilding = json_decode(curl_exec($ch), true)['result'][1];

							$arStrHouse = explode(" ", $arBuilding['name']);
							$house = $arStrHouse[0];

							if (str_contains($arBuilding['name'], 'корпус'))
							{
								$arStrStructure = explode("корпус", $arBuilding['name']);
								$structure = $arStrStructure[1];
							}
							if (str_contains($arBuilding['name'], 'строение'))
							{
								$arStrBuilding = explode("строение", $arBuilding['name']);
								$_building = $arStrBuilding[1];
							}

						} elseif ($n == 600 && $item !== '')
						{
							$arOther[] = $item;
						}
					}

					if (empty($arRegion)){
						$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
					}
					elseif (empty($arCity)){
						$result['errorFields'][$contactsIdList['fullName']]['city'] = 'Нет данных о Городе';
					}
					elseif (empty($arStreet)){
						$result['errorFields'][$contactsIdList['fullName']]['street'] = 'Нет данных об Улице';
					} /*elseif(empty($arBuilding)){
						$result['errorFields'][$contactsIdList['fullName']]['house'] = 'Нет данных о Номере дома';
					}*/
		/*
					else
					{
						$res['city'] = $arCity['name'];
						$res['flat'] = $arOther[0];
						$res['house'] = $queryHouse[$idd];
						$res['region'] = intval(mb_strimwidth($arRegion['id'], 0, 2, ""));
						$res['street'] = $arStreet['name'];
						//$res['district'] = $arDistrict;
						$res['building'] = $_building;
						$res['structure'] = $structure;
						//$res['other'] = $arOther;
						$res['regionKladrId'] = $arRegion['id'];
						$res['registrationType'] = "const";

						$result[$idd]['registrationAddressFias'] = $res;
					}
				}
				else {
					foreach ($resAddress as $n => $item)
					{
						if ($n == 200 && $item !== '')
						{
							$query = explode(" ", $item)[0];
							$contentType = 'region';
							$curlOptions = [
								CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}",
								CURLOPT_HEADER => false,
								CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
								CURLOPT_RETURNTRANSFER => true
							];
							$ch = curl_init();
							curl_setopt_array($ch, $curlOptions);
							$arRegion = json_decode(curl_exec($ch), true)['result'][1];
						}
						elseif ($n == 300 && $item !== '')
						{
							$queryCity = "";
							$query = explode(" ", $item);
							array_pop($query);
							foreach ($query as $city)
							{
								$queryCity .= $city . " ";
							}
							$cityName = trim($queryCity);
						}
						elseif ($n == 340 && $item !== '')
						{
							$arStreetResult = null;
							$queryStreet = "";
							$query = explode(" ", $item);
							array_pop($query);
							foreach ($query as $street)
							{
								$queryStreet .= $street . " ";
							}
							$streetName = trim($queryStreet);
						}
						elseif ($n == 400 && $item !== '')
						{
							$query = explode(" ", $item);
							array_pop($query);
							foreach ($query as $str)
							{
								$queryHouse .= $str . " ";
							}
							$houseName = trim($queryHouse);

							//без проверки
							if (str_contains($houseName, 'корпус'))
							{
								$arStrStructure = explode("корпус", $houseName);
								$_structure = $arStrStructure[1];
							}
							if (str_contains($houseName, 'строение'))
							{
								$arStrBuilding = explode("строение", $houseName);
								$_building = $arStrBuilding[1];
							}

						}
					}
					if(empty($arRegion)) {
						$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
					}
					else {
						$res['city'] = $cityName;
						$res['flat'] = $arOther[0];
						$res['house'] = $houseName;
						$res['region'] = intval(mb_strimwidth($arRegion['id'], 0, 2, ""));
						$res['street'] = $streetName;
						$res['building'] = $_building;
						$res['structure'] = $_structure;
						$res['regionKladrId'] = $arRegion['id'];
						$res['registrationType'] = "const";

						$result[$idd]['registrationAddressFias'] = $res;
					}
				}
			}
			else {
				foreach ($contactsIds as $idd)
				{
					$resultAddressList = \CRest::call('crm.address.list', array(
						'filter' => array('ANCHOR_ID' => $idd),
						'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','LOC_ADDR_ID')
					));
					$resAddrList = $resultAddressList['result'];

					foreach ($resAddrList as $addrItem) {
						if($addrItem['TYPE_ID'] == 4) {
							$Address = new \Bitrix\Location\Controller\Address;
							$resAddress = $Address->findById($addrItem['LOC_ADDR_ID'])['fieldCollection'];
						}
					}

					$res = [];
					$arRegion = [];
					$arDistrict = [];
					$arCity = [];
					$arStreet = [];
					$arBuilding = [];
					$arOther = [];
					$structure = null;
					$_building = null;

					if($kladr)
					{
						foreach ($resAddress as $n => $item)
						{

							if ($n == 200 && $item !== '') {
								$query = explode(" ", $item)[0];
								$contentType = 'region';
								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arRegion = json_decode(curl_exec($ch), true)['result'][1];
							}
							elseif ($n == 210 && $item !== '')
							{
								$query = explode(" ", $item)[0];
								$contentType = 'district';
								$regionId = $arRegion['id'];
								//$districtId = $arDistrict['id'];
								//$cityId = $arCity['id'];
								//$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}&regionId={$regionId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arDistrict = json_decode(curl_exec($ch), true)['result'][1];
							}
							elseif ($n == 300 && $item !== '')
							{
								$query = explode(" ", $item);

								array_pop($query);
								foreach ($query as $city)
								{
									$queryCity .= $city . " ";
								}
								$queryCity = trim($queryCity);

								$contentType = 'city';
								$regionId = $arRegion['id'];
								$districtId = $arDistrict['id'];
								//$cityId = $arCity['id'];
								//$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryCity}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arCityResult = json_decode(curl_exec($ch), true)['result'];

								//Logs\File::AddMessage($arCityResult,"addressFull ООО City",LOG_BP);

								foreach ($arCityResult as $k => $cityResponse)
								{

									$cityName = trim($cityResponse['name']);
									if ($cityName == $queryCity)
									{
										$CITY_GUID = $cityResponse['guid'];
										$CITY_PARENT_GUID = $cityResponse['parentGuid'];
										$arCity = $cityResponse;
										$queryCity = "";
										break;
									}
								}

							}
							elseif ($n == 340 && $item !== '')
							{
								$queryStr = "";
								$query = explode(" ", $item);
								array_pop($query);
								foreach ($query as $str)
								{
									$queryStr .= $str . " ";
								}
								$queryStr = trim($queryStr);

								//Logs\File::AddMessage($queryStr,"Выбранный {$idd} ООО queryStr",LOG_BP);

								$contentType = 'street';
								$regionId = $arRegion['id'];
								$districtId = $arDistrict['id'];
								$cityId = $arCity['id'];
								//$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryStr}&oneString=1&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);

								$arStreetResult = json_decode(curl_exec($ch), true)['result'];

								//Logs\File::AddMessage($arStreetResult,"Выбранный {$idd} ООО arStreetResult",LOG_BP);

								foreach ($arStreetResult as $k => $streetResponse)
								{

									$strName = trim($streetResponse['name']);
									$STREET_GUID = $streetResponse['guid'];
									$STREET_PARENT_GUID = $streetResponse['parentGuid'];

									if ($CITY_GUID == $STREET_PARENT_GUID)
									{

										//Logs\File::AddMessage($CITY_GUID,"Выбранный {$idd} ООО CITY_GUID",LOG_BP);
										//Logs\File::AddMessage($STREET_PARENT_GUID,"Выбранный {$idd} ООО STREET_PARENT_GUID",LOG_BP);

										$arStreet = $streetResponse;
										$queryStr = "";
										break;
									}
								}

							}
							elseif ($n == 400 && $item !== '')
							{
								$queryHouse = [];
								$query = explode(" ", $item);
								array_pop($query);
								foreach ($query as $str)
								{
									$queryHouse[$idd] .= $str . " ";
								}
								$queryHouse[$idd] = trim($queryHouse[$idd]);

								//Logs\File::AddMessage($queryHouse[$idd],"Выбранный {$idd} ООО queryHouse[idd]",LOG_BP);

								$contentType = 'building';
								$regionId = $arRegion['id'];
								$districtId = $arDistrict['id'];
								$cityId = $arCity['id'];
								$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$queryHouse[$idd]}&contentType={$contentType}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}&streetId={$streetId}&limit=50",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);

								$arBuildingResult = json_decode(curl_exec($ch), true)['result'];

								//Logs\File::AddMessage($arBuildingResult,"Выбранный {$idd} ООО arBuildingResult",LOG_BP);

								foreach ($arBuildingResult as $k => $buildingResponse)
								{

									$houseName = trim($buildingResponse['name']);

									if ($houseName == $queryHouse[$idd])
									{
										$arBuilding = $buildingResponse;

										$arStrHouse = explode(" ", $arBuilding['name']);
										$house = $arStrHouse[0];

										if (str_contains($arBuilding['name'], 'корпус'))
										{
											$arStrStructure = explode("корпус", $arBuilding['name']);
											$structure = $arStrStructure[1];
										}
										if (str_contains($arBuilding['name'], 'строение'))
										{
											$arStrBuilding = explode("строение", $arBuilding['name']);
											$_building = $arStrBuilding[1];
										}
										break;
									}
								}

							}
							elseif ($n == 410 && $item !== '' && empty($arStreet) && empty($arBuilding))
							{
								$streetAndBuilding = explode(", ", $item);

								$str_street = $streetAndBuilding[0];
								$street = explode(" ", $str_street);

								$cStrStreet = count($street) - 1;

								$strStreetG = "";
								for ($i = 0; $i < $cStrStreet; $i++)
								{
									$strStreetG = $strStreetG . ' ' . $street[$i];
								}

								$contentTypeS = 'street';
								$regionId = $arRegion['id'];
								$districtId = $arDistrict['id'];
								$cityId = $arCity['id'];
								//$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$strStreetG}&contentType={$contentTypeS}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arStreet = json_decode(curl_exec($ch), true)['result'][1];


								$str_building = $streetAndBuilding[1];
								$building = explode(" ", $str_building);

								$cStrBuilding = count($building) - 1;

								$strBuildingG = "";
								for ($i = 0; $i < $cStrBuilding; $i++)
								{
									$strBuildingG = $strBuildingG . ' ' . $building[$i];
								}
								$contentTypeB = 'building';
								$regionId = $arRegion['id'];
								$districtId = $arDistrict['id'];
								$cityId = $arCity['id'];
								//$streetId = $arStreet['id'];

								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$strBuildingG}&contentType={$contentTypeB}&regionId={$regionId}&districtId={$districtId}&cityId={$cityId}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arBuilding = json_decode(curl_exec($ch), true)['result'][1];

								$arStrHouse = explode(" ", $arBuilding['name']);
								$house = $arStrHouse[0];

								if (str_contains($arBuilding['name'], 'корпус'))
								{
									$arStrStructure = explode("корпус", $arBuilding['name']);
									$structure = $arStrStructure[1];
								}
								if (str_contains($arBuilding['name'], 'строение'))
								{
									$arStrBuilding = explode("строение", $arBuilding['name']);
									$_building = $arStrBuilding[1];
								}

							}
							elseif ($n == 600 && $item !== ''){
								$arOther[] = $item;
							}
						}
						if (empty($arRegion)) {
							$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
						}
						elseif (empty($arCity)) {
							$result['errorFields'][$contactsIdList['fullName']]['city'] = 'Нет данных о Городе';
						}
						elseif (empty($arStreet)) {
							$result['errorFields'][$contactsIdList['fullName']]['street'] = 'Нет данных об Улице';
						} /*elseif(empty($arBuilding)){
							$result['errorFields'][$contactsIdList['fullName']]['house'] = 'Нет данных о Номере дома';
						}*/
		/*else
						{
							$res['city'] = $arCity['name'];
							$res['flat'] = $arOther[0];
							$res['house'] = $queryHouse[$idd];
							$res['region'] = intval(mb_strimwidth($arRegion['id'], 0, 2, ""));
							$res['street'] = $arStreet['name'];
							//$res['district'] = $arDistrict;
							$res['building'] = $_building;
							$res['structure'] = $structure;
							//$res['other'] = $arOther;
							$res['regionKladrId'] = $arRegion['id'];
							$res['registrationType'] = "const";

							$result[$idd]['registrationAddressFias'] = $res;
						}
					}
					else{
						foreach ($resAddress as $n => $item)
						{
							if ($n == 200 && $item !== '')
							{
								$query = explode(" ", $item)[0];
								$contentType = 'region';
								$curlOptions = [
									CURLOPT_URL => "https://kladr-api.ru/api.php?query={$query}&contentType={$contentType}",
									CURLOPT_HEADER => false,
									CURLOPT_HTTPHEADER => ["Content-Type: text/json; charset=utf-8"],
									CURLOPT_RETURNTRANSFER => true
								];
								$ch = curl_init();
								curl_setopt_array($ch, $curlOptions);
								$arRegion = json_decode(curl_exec($ch), true)['result'][1];
							}
							elseif ($n == 300 && $item !== '')
							{
								$queryCity = "";
								$query = explode(" ", $item);
								array_pop($query);
								foreach ($query as $city)
								{
									$queryCity .= $city . " ";
								}
								$cityName = trim($queryCity);
							}
							elseif ($n == 340 && $item !== '')
							{
								$arStreetResult = null;
								$queryStreet = "";
								$query = explode(" ", $item);
								array_pop($query);
								foreach ($query as $street)
								{
									$queryStreet .= $street . " ";
								}
								$streetName = trim($queryStreet);
							}
							elseif ($n == 400 && $item !== '')
							{
								$query = explode(" ", $item);
								array_pop($query);
								foreach ($query as $str)
								{
									$queryHouse .= $str . " ";
								}
								$houseName = trim($queryHouse);

								//без проверки
								if (str_contains($houseName, 'корпус'))
								{
									$arStrStructure = explode("корпус", $houseName);
									$_structure = $arStrStructure[1];
								}
								if (str_contains($houseName, 'строение'))
								{
									$arStrBuilding = explode("строение", $houseName);
									$_building = $arStrBuilding[1];
								}

							}
						}
						if(empty($arRegion)) {
							$result['errorFields'][$contactsIdList['fullName']]['regionKladrId'] = 'Нет данных о Регионе';
						}
						else {
							$res['city'] = $cityName;
							$res['flat'] = $arOther[0];
							$res['house'] = $houseName;
							$res['region'] = intval(mb_strimwidth($arRegion['id'], 0, 2, ""));
							$res['street'] = $streetName;
							$res['building'] = $_building;
							$res['structure'] = $_structure;
							$res['regionKladrId'] = $arRegion['id'];
							$res['registrationType'] = "const";

							$result[$idd]['registrationAddressFias'] = $res;
						}
					}
				}
			}
		}

		return $result;//$resAddress[100].', '.$resAddress[200].', '.$resAddress[210].', '.$resAddress[300].', '.$resAddress[340].', '.$resAddress[400].', '.$resAddress[600];
	*/
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

			$jsonResponse = \KPLab\Curl::post_v2(TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point, $emulation);

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

class BalancePlatformRequest extends \Bitrix\Main\Engine\Controller {

	private $balancePlatformRequest;

	public function getDefaultPreFilters()
	{
		return [
			new Authentication(),
		];
	}
	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		//$this->loans = new \KPLab\JWT\Loans();
		return parent::prepareParams();
	}

	public function onBeforeAction(\Event $event) {

		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		$apikey = json_decode($request->getInput(),true)['apiKey'];

		if ($apikey)
		{
			if ($apikey !== APIKEY)
			{
				$this -> addError(new Error('API key not found', 401));
				return new EventResult(EventResult::ERROR, '', '', $this);
			} else
			{
				global $USER;
				if (!is_object($USER))
					$USER = new \CUser;
				// по умолчанию авторизация из-под админа
				$USER->Authorize(1);
			}
		}

		return null;
	}

	public function recieveAction() {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$response = $context -> getResponse();
		$server = $context -> getServer();

        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса

		$point = "SE_BX";
		$url = $server['SCRIPT_URI'];
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}
		$recieve = json_decode($request->getInput(),true);
		Logs\File::AddMessage($recieve,"recieve",LOG_BP);
		//$headers = $request['headers']['headers'];
		$status = $recieve['status'];
		$data = $recieve['data'];
		$isFirstRequest = $recieve['isFirstRequest'];
		$taskId = $recieve['taskId'];
		$message = $recieve['message'];

		$objectData['ITEM_TITLE'] = $data['clientName'];
		$objectData['TASK_ID'] = $taskId;


		$balancePlatformRequestDate = $recieve['balancePlatformRequestDate'];


		if(is_null($message)) {

			Logs\File::AddMessage($message,"message",LOG_BP);
			if($isFirstRequest)
			{
				Logs\File::AddMessage($isFirstRequest,"isFirstRequest",LOG_BP);
				//$res149 = self ::setParams149($taskId, $data);
				$resCompany = self ::setParamsCompany($taskId, $recieve);
				Logs\File::AddMessage($resCompany,"resCompany",LOG_BP);
				if ($resCompany !== null)
				{

					$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(149);

					$items = $factory->getItems([
						'filter' => [
							'%UF_CRM_49_1706019437092' => $taskId
						]
					]);
					if($items):
						foreach ($items as $k => $item)
						{
							$item->getData();
							$objectData['ITEM_TITLE'] = $item->getData()['TITLE'];

							$item->set('UF_CRM_SCORING_PASSED', true);
							$item->setStageId("DT149_231:SUCCESS");

							$operation = $factory -> getUpdateOperation($item);

							// Step 2: config operation (optional)
							$operation->disableAllChecks();

							// Step 3: launch operation
							$operationResult = $operation -> launch();

							if ($operationResult -> isSuccess())
							{
								$message = "Данные успешно сохранились";
								\CRest ::call('crm.timeline.comment.add', [
									'fields' => [
										"ENTITY_ID" => $item -> getId(),
										"ENTITY_TYPE" => 'DYNAMIC_149',
										"COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
									]
								]);
							} else
							{
								$message = $operationResult -> getErrorMessages();
                                $statusRequest = 'Failed'; // Статус запроса
								$jsonRes['success'] = "";
								$jsonRes['error'] = $message;
                                // Логируем информацию
                                \KPLab\API\LogsAction::Request(
                                    $objectData,
                                    $methodName,                        // Метод запроса (имя метода)
                                    $url,                               // URL запроса
                                    $controllerName,                    // имя текущего контроллера
                                    $request->getRequestMethod(),       // Метод запроса (POST или GET)
                                    $statusRequest,                     // Статус запроса
                                    json_encode($jsonRes),              // Ответ на запрос
                                    $timeData,                          // Время
                                    $request->getInput(),               // Тело запроса
                                    json_encode($request->getHeaders()),// Заголовки запроса
                                    $taskId,                            // Task ID (если есть)
                                    0,                              // ID Типа запроса (если есть)
                                    false                              // Тип запроса (если есть)
                                );
								Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
								return false;
							}
						}
					endif;

					$jsonRes['success'] = $resCompany;
					$jsonRes['error'] = "";
                    // Логируем информацию
                    \KPLab\API\LogsAction::Request(
                        $objectData,
                        $methodName,                        // Метод запроса (имя метода)
                        $url,                               // URL запроса
                        $controllerName,                    // имя текущего контроллера
                        $request->getRequestMethod(),       // Метод запроса (POST или GET)
                        $statusRequest,                     // Статус запроса
                        json_encode($jsonRes),              // Ответ на запрос
                        $timeData,                          // Время
                        $request->getInput(),               // Тело запроса
                        json_encode($request->getHeaders()),// Заголовки запроса
                        $taskId,                            // Task ID (если есть)
                        0,                              // ID Типа запроса (если есть)
                        false                              // Тип запроса (если есть)
                    );
					Logs\IBlock ::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
					$res = $resCompany;
				}
			}
			else {
				$res134 = self::setParams134($taskId, $recieve);
                if($res134 !== null) {
                    $resCompany = self ::setParamsCompany($taskId, $recieve);
                    Logs\File::AddMessage($resCompany,"resCompany",LOG_BP);
                    if ($resCompany !== null)
                    {
                        $jsonRes['success'] = $res134;
                        $jsonRes['error'] = "";
                        // Логируем информацию
                        \KPLab\API\LogsAction::Request(
                            $objectData,
                            $methodName,                        // Метод запроса (имя метода)
                            $url,                               // URL запроса
                            $controllerName,                    // имя текущего контроллера
                            $request->getRequestMethod(),       // Метод запроса (POST или GET)
                            $statusRequest,                     // Статус запроса
                            json_encode($jsonRes),              // Ответ на запрос
                            $timeData,                          // Время
                            $request->getInput(),               // Тело запроса
                            json_encode($request->getHeaders()),// Заголовки запроса
                            $taskId,                            // Task ID (если есть)
                            0,                              // ID Типа запроса (если есть)
                            false                              // Тип запроса (если есть)
                        );
                        Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
                        $res = $res134;
                    }
				}
			}
			return $res;
		}
		else {
			$jsonRes['success'] = "";
			$jsonRes['error'] = $message;

            $statusRequest = 'Failed'; // Статус запроса

            // Логируем информацию
            \KPLab\API\LogsAction::Request(
                $objectData,
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                $request->getRequestMethod(),       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                json_encode($jsonRes),              // Ответ на запрос
                $timeData,                          // Время
                $request->getInput(),               // Тело запроса
                json_encode($request->getHeaders()),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                false                              // Тип запроса (если есть)
            );
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headers);
			return false;
		}

	}

	public function getInfoAction(array $params = []) {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$point = "SE_BX";
		$url = $server['SCRIPT_URI'];
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}

		$recieve = json_decode($request->getInput(),true);

		$recieveINN = $recieve['inn'];

		$objectData['ITEM_TITLE'] = "Запрос по ИНН: {$recieveINN}";

		$allData134 = self::getInfo134byINN($recieveINN);
		if(!$allData134) {
			$errorMessage = "Not found card information by INN: {$recieveINN}";
			$this->addError(new Error($errorMessage, 403));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}
		elseif(is_array($allData134) && $allData134['errorFields']) {
			$error['message'] = "There are empty fields";
			$error['erData'] =  $allData134['errorFields'];
			$this->addError(new Error($error['message'], 403, $error['erData']));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $error;
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}
		else {
			$jsonRes['success'] = $allData134;
			$jsonRes['error'] = "";
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			$point = "BX_SE";
			$res = BalancePlatform::createRequest($allData134, $objectData, $timeData, $point);
			return $res;
		}

	}

	public static function setParams134(string $taskId, $recieve) {
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(134);

		$items = $factory->getItems([
			'filter' => [
				'%UF_CRM_56_1705945353800' => $taskId
			]
		]);
		if($items):
			foreach ($items as $k => $item)
			{
				if($recieve == null) {
					$message = "Данные не поступили!";
					\CRest ::call('crm.timeline.comment.add', [
						'fields' => [
							"ENTITY_ID" => $item -> getId(),
							"ENTITY_TYPE" => "DYNAMIC_134",
							"COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
						]
					]);
				}
				else
				{

                    // Сохраняем данные Checklist StatusType
                    $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $recieve["checklist"]["_StatusType"]);
                    if (is_array($checklistStatus)) {
                        return [
                            'status' => 'error',
                            'messages' => $checklistStatus,
                        ];
                    }

					$fields = [
						'UF_CRM_56_1684843345' => date('d.m.Y H:i:s'),
						'UF_CRM_56_1705999659270' => $recieve["data"]['avg6MonthRevenue'], //Среднемесячная выручка
						'UF_CRM_56_1705999684755' => $recieve["data"]['lastMonthRevenue'], //Выручка за последний месяц
						'UF_CRM_56_1705999722689' => $recieve["data"]['stocksSum'], //Остаток товаров string ---UF_CRM_56_1705999840678---
						'UF_CRM_56_1706000045243' => $recieve["data"]['pdn'], //ПДН
						'UF_CRM_56_1706000068667' => $recieve["data"]['overdueBKISum'], //Наличие просроченных платежей
						'UF_CRM_56_1706000119258' => $recieve["data"]['proceedingsPhysical'], //ФССП
						'UF_CRM_56_1684846350' => $recieve["data"]['smoothedLimit'], //Новый лимит с учетом сглаживания
						'UF_CRM_56_1710466920' => $recieve["data"]['hasInvalidKeys'], //Новый лимит с учетом сглаживания
						'UF_CRM_56_1706002947573' => $recieve["data"]['status'], // Статус Скоринг системы Seller-Engine
						'UF_CRM_56_1711371246' => $recieve["data"]['pdN_Group'] // Групповой ПДН
					];
                    self::updateItemFields($factory, $item, $fields, 'Данные for 134');

					$item -> setFromCompatibleData($fields);

					// Step 1: get operation
					$operation = $factory -> getUpdateOperation($item);

					// Step 2: config operation (optional)
					$operation->disableAllChecks();

					// Step 3: launch operation
					$operationResult = $operation -> launch();

					if ($operationResult -> isSuccess())
					{
						$message = "Данные успешно сохранились";
						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_134",
								"COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
							]
						]);
					} else
					{
						$message = $operationResult -> getErrorMessages();
					}
				}
			}
			return $message;
		endif;
		return null;
	}

	public static function getInfo134byINN($inn) {
		$entityTypeId = 134;
		$req = new \Bitrix\Crm\EntityRequisite();
		$rsCompany = $req->getList(array(
			'filter' => array(
				'RQ_INN' => $inn
			),
			'select' => ['ENTITY_ID']
		));

		$rq = $rsCompany->fetch();
		$rqID = $rq['ENTITY_ID'];

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(134);
		//$items=[];
		$items = $factory->getItems([
			'filter' => [
				//'UF_CRM_56_1684841075' => $inn,
				'COMPANY_ID' => $rqID
			]
		]);

		if($items):
			foreach ($items as $k => $item)
			{
				$elementID = $item->getId();
				$allData = BalancePlatform::getInfo($entityTypeId, $elementID);
			}
			return $allData;
		endif;

		return null;
	}

	public static function setParams149(string $taskId, $data) {

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(149);

		$items = $factory->getItems([
			'filter' => [
				'%UF_CRM_49_1706019437092' => $taskId
			]
		]);
		if($items):
			foreach ($items as $k => $item)
			{
				if($data == null) {
					$message = "Данные не поступили!";
					\CRest ::call('crm.timeline.comment.add', [
						'fields' => [
							"ENTITY_ID" => $item -> getId(),
							"ENTITY_TYPE" => "DYNAMIC_149",
							"COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
						]
					]);
				} else {

                    // Сохраняем данные Checklist StatusType

                    $checklistStatus = self::saveChecklistStatusNumData($factory, $item, $data["_StatusType"]);
                    if (is_array($checklistStatus)) {
                        return [
                            'status' => 'error',
                            'messages' => $checklistStatus,
                        ];
                    }


					$fields = [
						'UF_CRM_49_1706081754331' => date('d.m.Y H:i:s'),
						'UF_CRM_49_1706081303342' => $data['avg6MonthRevenue'], //Среднемесячная выручка
						'UF_CRM_49_1706081312614' => $data['lastMonthRevenue'], //Выручка за последний месяц
						'UF_CRM_49_1706081324071' => $data['stocksSum'], //Остаток товаров string ---UF_CRM_56_1705999840678---
						'UF_CRM_49_1706081415791' => $data['pdn'], //ПДН
						'UF_CRM_49_1706081340065' => $data['overdueBKISum'], //Наличие просроченных платежей
						'UF_CRM_49_1706081290967' => $data['proceedingsPhysical'], //ФССП
						'UF_CRM_49_1706081357760' => $data['smoothedLimit'], //Новый лимит с учетом сглаживания
					];
                    self::updateItemFields($factory, $item, $fields, 'Данные for 149');

					$item -> setFromCompatibleData($fields);

					// Step 1: get operation
					$operation = $factory -> getUpdateOperation($item);

					// Step 2: config operation (optional)
					$operation->disableAllChecks();

					// Step 3: launch operation
					$operationResult = $operation -> launch();

					if ($operationResult -> isSuccess())
					{
						$message = "Данные успешно сохранились";
						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_149",
								"COMMENT" => "[b]Данные успешно обновились от Seller-Engine![/b]"
							]
						]);
					} else
					{
						$message = $operationResult -> getErrorMessages();
					}
				}
			}
			return $message;
		endif;

		return null;
	}

	public static function setParamsCompany(string $taskId, $data) {
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
		$itemsCompany = $factory->getItems([
			'filter' => [
				'%UF_CRM_UF_SE_TASK_ID' => $taskId
			]
		]);
		if($itemsCompany):
			//Logs\File::AddMessage("Найдена компания","item",LOG_BP);
			foreach ($itemsCompany as $k => $itemCompany)
			{
				if($data == null) {
					$message = "Данные не поступили!";
					\CRest ::call('crm.timeline.comment.add', [
						'fields' => [
							"ENTITY_ID" => $itemCompany -> getId(),
							"ENTITY_TYPE" => "COMPANY",
							"COMMENT" => "[b]Данные не поступили от Seller-Engine![/b]"
						]
					]);
				}
				else {

                    // Сохраняем данные и проверяем результат
                    $saveResult = self::saveAllData($factory, $itemCompany, $data);
                    if ($saveResult['status'] === 'error') {
                        // Если произошла ошибка, добавляем комментарий с сообщениями об ошибках
                        $message = "Ошибка при сохранении данных: " . implode(', ', $saveResult['messages']);
                        \CRest::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $itemCompany->getId(),
                                "ENTITY_TYPE" => "COMPANY",
                                "COMMENT" => "[b]{$message}[/b]"
                            ]
                        ]);
                    } else {
                        // Если все прошло успешно
                        $message = "Данные успешно сохранились";
                        \CRest::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $itemCompany->getId(),
                                "ENTITY_TYPE" => "COMPANY",
                                "COMMENT" => "[b]{$message} от Seller-Engine![/b]"
                            ]
                        ]);
                    }
				}
			}
			return $message;

		else:
			Logs\File::AddMessage("Нихуя НЕ Найдена компания","item",LOG_BP);
		endif;

		return null;
	}



	private static function prepareOvkFields($data): array
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
	private static function prepareKonturFocusFields($data): array
	{
		foreach ($data["certificates"] as $certificate_konturFocusData) {
			$type_certificate_konturFocusData = $certificate_konturFocusData["type"];
			$endDate_certificate_konturFocusData = $certificate_konturFocusData["endDate"];
			$productName_certificate_konturFocusData = $certificate_konturFocusData["productName"];
			$UF_CRM_COMPANY_KNTR_CERTIFICATE[] = "{$type_certificate_konturFocusData} | {$endDate_certificate_konturFocusData} | {$productName_certificate_konturFocusData}";
		}

		$subjects_lesseeContracts_konturFocusData = $data["lesseeContracts"][0]["subjects"]; //...
		$contractDate_lesseeContracts_konturFocusData = $data["lesseeContracts"][0]["contractDate"]; //...

		foreach ($data["licenses"] as $license_konturFocusData) {
			$activity_license_konturFocusData = $license_konturFocusData["activity"];
			$dateEnd_license_konturFocusData = $license_konturFocusData["dateEnd"];
			$UF_CRM_COMPANY_KNTR_LICENSES[] = "{$activity_license_konturFocusData} | {$dateEnd_license_konturFocusData}";
		}

		$stage_lastBankruptcyDataKonturFocusData = $data["lastBankruptcyData"]["stage"];
		$stageDate_lastBankruptcyDataKonturFocusData = $data["lastBankruptcyData"]["stageDate"];

		return [
			//region KONTUR FOCUS
			'UF_CRM_COMPANY_KNTR_ADMIN_OFFENCE_CASE_COUNT' => $data["administrativeOffenceCaseCount"],
			'UF_CRM_COMPANY_KNTR_ARBITR_CLAIMS_LOST_CASES_SUM' => $data["arbitrationClaimsForLostCasesSum"],
			'UF_CRM_COMPANY_KNTR_ARBIT_CLAIMS_REVIEW_CASES_SUM' => $data["arbitrationClaimsForReviewCasesSum"],
			'UF_CRM_COMPANY_KNTR_ANY_FNS_BLOCKED_ACCOUNTS' => $data["anyFnsBlockedAccounts"],
			'UF_CRM_COMPANY_KNTR_ANY_SENT_DOCUMENTS_TO_FNS' => $data["anySentDocumentsToFns"],
			'UF_CRM_COMPANY_KNTR_ANY_DISQUALIFIED_DIRECTORS' => $data["anyDisqualifiedDirectors"],
			'UF_CRM_COMPANY_KNTR_BANKS' => $data["banks"],
			'UF_CRM_COMPANY_KNTR_BLOCKED_ACCOUNTS' => $data["blockedAccounts"],
			'UF_CRM_COMPANY_KNTR_CERTIFICATE' => $UF_CRM_COMPANY_KNTR_CERTIFICATE,
			'UF_CRM_COMPANY_KNTR_LESSEE_CONTRACTS' => "{$subjects_lesseeContracts_konturFocusData} | {$contractDate_lesseeContracts_konturFocusData}",
			'UF_CRM_COMPANY_KNTR_CONNECTED_COMPANIES' => $data["connectedCompanies"],
			'UF_CRM_COMPANY_KNTR_CONNECTED_SITES' => $data["connectedSites"],
			'UF_CRM_COMPANY_KNTR_ENFORCEMENT_PROCEEDINGS_SUM' => $data["enforcementProceedingsSum"],
			'UF_CRM_COMPANY_KNTR_IN_ANY_SANCTION_LISTS' => $data["inAnySanctionLists"],
			'UF_CRM_COMPANY_KNTR_IN_ANY_FNS_LIST' => $data["inAnyFnsList"],
			'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_ENTERPRISE_LIST' => $data["inStrategicEnterpriseList1009"],
			'UF_CRM_COMPANY_KNTR_IN_JOIN_STOCK_CMPNY_LIST91P' => $data["inJointStockCompanyList91P"],
			'UF_CRM_COMPANY_KNTR_IN_UNRELIABLE_SIPPLIER_LIST' =>$data["inUnreliableSupplierList"],
			'UF_CRM_COMPANY_KNTR_MSP_LIST_DATE' => $data["mspListDate"],
			'UF_CRM_COMPANY_KNTR_LICENSES' => $UF_CRM_COMPANY_KNTR_LICENSES,
			'UF_CRM_COMPANY_KNTR_TRADEMARKS' => $data["trademarks"],
			'UF_CRM_COMPANY_KNTR_LAST_BANKRUPTCY_DATA' => "{$stage_lastBankruptcyDataKonturFocusData} | {$stageDate_lastBankruptcyDataKonturFocusData}",
			//endregion
		];
	}
	private static function prepareKonturPrismaFields($data): array
	{
		return [
			//region KONTUR PRISMA
			'UF_CRM_COMPANY_KNTR_IN_GOVERNMENT_DIRECTIVE_LIST' => $data["inGovernmentDirectiveList"],
			'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_ORG_LIST' => $data["inStrategicOrganizationsList"],
			'UF_CRM_COMPANY_KNTR_WEBSITE_BLOCK_INFOS' => $data["websiteBlockInfos"],
			'UF_CRM_COMPANY_KNTR_IN_PRLIFRTN_RISK_DETECTIONLIST' => $data["inProliferationRiskDetectionList"],
			'UF_CRM_COMPANY_KNTR_IN_REFUSAL_LIST764P' => $data["inRefusalList764P"],
			'UF_CRM_COMPANY_KNTR_IN_BANK_REFUSAL_LIST764P' => $data["inBankRefusalList764P"],
			'UF_CRM_COMPANY_KNTR_IN_SANCTIONS_LIST' => $data["inSanctionsList"],
			'UF_CRM_COMPANY_KNTR_IN_STRATEGIC_COMPANIES_LIST' => $data["inStrategicCompaniesList"],
			'UF_CRM_COMPANY_KNTR_IN_TERRORIST_LIST' =>  $data["inTerroristsList"],
			'UF_CRM_COMPANY_KNTR_IN_EXTREMISTS_LIST' => $data["inExtremistsList"],
			'UF_CRM_COMPANY_KNTR_IN_INTERDEP_COMMISSION_LIST' => $data["inInterdepartmentalCommissionList"],
			'UF_CRM_COMPANY_KNTR_IN_WEAPON_MASS_DESTRUCTION_DIS' => $data["inWeaponsOfMassDestructionDistributorsList"],
			'UF_CRM_COMPANY_KNTR_IN_ILLEG_LENDER_INDICATOR_LIST' => $data["inIllegalLenderIndicatorsList"],
			'UF_CRM_COMPANY_KNTR_IN_PYRAMID_SCHEME_INDICATAOR_L' => $data["inPyramidSchemeIndicatorsList"],
			'UF_CRM_COMPANY_KNTR_ILLEG_SECUR_MARKET_PARTICIPANT' => $data["inIllegalSecuritiesMarketParticipantIndicatorsList"],
			//endregion
		];
	}
	private static function prepareChecklistStatusTypeFields($data): array
	{
		$statusType_checklistData = (string) $data;
		$statusType_checklistXML_ID = "checklist_".$statusType_checklistData;

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"XML_ID" => $statusType_checklistXML_ID,
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$statusType_checklistID = $arEnum['ID'];
		}

		return [
			'UF_CRM_COMPANY_CHKLST' => $statusType_checklistID,
		];
	}
    private static function prepareChecklistStatusNumFields($data): array
	{
		$statusType_checklistData = (string) $data;
		$statusType_checklistXML_ID = "check_list_".$statusType_checklistData;

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"XML_ID" => $statusType_checklistXML_ID,
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$statusType_checklistID = $arEnum['ID'];
		}

		return [
			'UF_CRM_CHKLST' => $statusType_checklistID,
		];
	}
    private static function prepareChecklistStatusFields($data): array
	{
		//$statusType_checklistData = (string) $data;
		$statusType_checklistValue = (string) $data;

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"VALUE" => $statusType_checklistValue,
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$statusType_checklistID = $arEnum['ID'];
		}

		return [
			'UF_CRM_CHKLST' => $statusType_checklistID,
		];
	}
	private static function prepareChecklistFields($data): array
	{
		return [
			//region checkList
			'UF_CRM_COMPANY_CHKLST_WB_INC_TO_SALE_FOR_SIX_MONTH' => $data["wbIncomeToSaleForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_OZON_INC_SALE_FOR_SIX_MOUNTH' => $data["ozonIncomeToSaleForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_MARKETPLACE_DURATION_IN_MNTH' => $data["marketplaceDurationInMonths"],
			'UF_CRM_COMPANY_CHKLST_SALES_TO_COUNT_SIX_MTH_RATIO' => $data["salesToCountForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_SALES_TO_RETURN_COUNT_SIX_MH' =>	$data["salesToReturnCountForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_STOK_SUM_LIMIT_SIX_MTH_RATIO' => $data["stockSumToLimitForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_SMOOTHED_LIM_FOR_SIX_MONTHS' => $data["smoothedLimitForSixMonths"],
			'UF_CRM_COMPANY_CHKLST_PDN' => $data["pdn"]*100,
			'UF_CRM_COMPANY_CHKLST_ANY_FNS_BLOCKED_ACCOUNTS' => $data["anyFnsBlockedAccounts"],
			'UF_CRM_COMPANY_CHKLST_PERSON_VALID_PASSPORT' => $data["personValidPassport"],
			'UF_CRM_COMPANY_CHKLST_LOCATED_IN_LISTS_NAMES' => $data["locatedInListsNames"],
			'UF_CRM_COMPANY_CHKLST_IN_UNRELIABLE_SUPPLIER_LIST' => $data["inUnreliableSupplierList"],
			'UF_CRM_COMPANY_CHKLST_ANY_DISQUALIFIED_DIRECTORS' => $data["anyDisqualifiedDirectors"],
			'UF_CRM_COMPANY_CHKLST_PERSON_AGE_LIST' => $data["personAgeList"][0],//[45]
			'UF_CRM_COMPANY_CHKLST_HAS_PERSON_RUSSIAN_NATIONAL' => $data["hasPersonRussianNationality"],
			'UF_CRM_COMPANY_CHKLST_MAX_ACTIVE_LOAN_OVERDUE_DAYS' => $data["maxActiveLoanOverdueDays"],
			'UF_CRM_COMPANY_CHKLST_ANY_LONG_OVERDUE_LOAN' => $data["anyLongOverdueLoan"],
			'UF_CRM_COMPANY_CHKLST_PERSON_LOAN_DATE' => $data["personLoanDate"],
			'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_MICRO_LOAN_CNT' => $data["personActiveMicroLoanCount"],
			'UF_CRM_COMPANY_CHKLST_PERSON_ACTIVE_FSSP_SUM' => $data["personActiveFsspSum"],
			'UF_CRM_COMPANY_CHKLST_CLAIMS_SUM_INC_FOR_SIX_MONTH' => $data["claimsSumToIncomeForSixMonthsRatio"],
			'UF_CRM_COMPANY_CHKLST_LAST_BANKRUPTCY_DATE' => $data["lastBankruptcyDate"],
			'UF_CRM_COMPANY_CHKLST_AUTO_LIMIT' => (int) $data["limitForAutoApprove"],
			//endregion
		];
	}
	private static function prepareRefusedInStatusFields($data): array
	{
		return [
			'UF_CRM_COMPANY_CHKLST_REFUSED_IN_STATUS_BY_DATA' => $data["automaticRefuse"],
			'UF_CRM_COMPANY_CHKLST_AUTOMATIC_APPROVE' => $data["automaticApprove"],
			'UF_CRM_COMPANY_CHKLST_AUTHORIZED_PERSON' => $data["authorizedPerson"],
			'UF_CRM_COMPANY_CHKLST_CREDIT_COMMITTEE' => $data["creditCommittee"]
		];
	}

	private static function saveOvkData($factory, $item, $data) {
		$fields = self::prepareOvkFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные ovk');
	}
	private static function saveKonturFocusData($factory, $item, $data) {
		$fields = self::prepareKonturFocusFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные Kontur Focus');
	}
	private static function saveKonturPrismaData($factory, $item, $data) {
		$fields = self::prepareKonturPrismaFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные Kontur Prisma');
	}
	private static function saveChecklistStatusTypeData($factory, $item, $data) {
		$fields = self::prepareChecklistStatusTypeFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные Checklist StatusType');
	}
    private static function saveChecklistStatusData($factory, $item, $data) {
        $fields = self::prepareChecklistStatusFields($data);

        Logs\File::AddMessage($fields,"fields saveChecklistStatusData",LOG_BP);
        return self::updateItemFields($factory, $item, $fields, 'Данные checklistStatus');
    }
    private static function saveChecklistStatusNumData($factory, $item, $data) {
        $fields = self::prepareChecklistStatusNumFields($data);
        return self::updateItemFields($factory, $item, $fields, 'Данные checklistStatus');
    }
	private static function saveChecklistData($factory, $item, $data) {
		$fields = self::prepareChecklistFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные Checklist');
	}
	private static function saveRefusedInStatusByData($factory, $item, $data) {
		$fields = self::prepareRefusedInStatusFields($data);
		return self::updateItemFields($factory, $item, $fields, 'Данные Checklist RefusedInStatusByData');
	}

    private static function saveAllData($factory, $item, $data)
    {
        // Сохраняем данные OVK
        $ovkResult = self::saveOvkData($factory, $item, $data['ovk']);
        if (is_array($ovkResult)) {
            // Возвращаем ошибку, если результат является массивом с сообщениями об ошибке
            return [
                'status' => 'error',
                'messages' => $ovkResult,
            ];
        }

        // Сохраняем данные Kontur Focus
        $konturFocusResult = self::saveKonturFocusData($factory, $item, $data['kontur']['focus']);
        if (is_array($konturFocusResult)) {
            return [
                'status' => 'error',
                'messages' => $konturFocusResult,
            ];
        }

        // Сохраняем данные Kontur Prisma
        $konturPrismaResult = self::saveKonturPrismaData($factory, $item, $data['kontur']['prisma']);
        if (is_array($konturPrismaResult)) {
            return [
                'status' => 'error',
                'messages' => $konturPrismaResult,
            ];
        }

        // Сохраняем данные Checklist StatusType
        $checklistStatusType = self::saveChecklistStatusTypeData($factory, $item, $data["checklist"]['statusType']);
        if (is_array($checklistStatusType)) {
            return [
                'status' => 'error',
                'messages' => $checklistStatusType,
            ];
        }

        // Сохраняем данные Checklist
        $checklist = self::saveChecklistData($factory, $item, $data["checklist"]['data']);
        if (is_array($checklist)) {
            return [
                'status' => 'error',
                'messages' => $checklist,
            ];
        }

        // Сохраняем данные RefusedInStatusByData
        $refusedInStatusBy = self::saveRefusedInStatusByData($factory, $item, $data["checklist"]['refusedInStatusByData']);
        if (is_array($refusedInStatusBy)) {
            return [
                'status' => 'error',
                'messages' => $refusedInStatusBy,
            ];
        }

        // Если все прошло успешно, возвращаем успешный результат
        return [
            'status' => 'success',
            'messages' => 'Все данные успешно сохранены.',
        ];
    }

    private static function updateItemFields($factory, $item, $fields, $messagePrefix)
    {
        Logs\File::AddMessage($fields, "$messagePrefix", LOG_BP);

        //$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $item->setFromCompatibleData($fields);
        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();

        $operationResult = $operation->launch();

        if ($operationResult->isSuccess()) {
            return "{$messagePrefix} успешно сохранились";
        } else {
            return $operationResult->getErrorMessages(); // Возвращаем массив ошибок
        }
    }
}