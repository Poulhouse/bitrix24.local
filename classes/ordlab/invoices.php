<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;
use CRest;
use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Diag\Debug;


define("LOG_ORDLAB_INVOICE", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/invoces.log");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");

class Invoices
{
	const URL = "https://api.ord-lab.ru/api/v1/invoices";

	// POST /invoices - Передача информации об Акте и статистике выполнения РК в ОРД
	// В теле запроса передается объект Invoice без id
	/*
	 *POST /invoices {
	 * "contractId" : string(uuid);required,
	 * "number" : string,
	 * "clientRole" : string; required,
	 * "contractorRole" : string; required,
	 * "date" : date; required,
	 * "startDate" : date; required,
	 * "endDate" : date; required,
	 * "amount" : number (double); required,
	 * "isVat" : boolean; required,
	 * "items" : [
	 *  {
	 *      "contractId" : string(uuid),
	 *      "amount" : number (double); required,
	 *      "isVat" : boolean; required,
	 *      "creatives" : []
	 *  }
	 * ]
	 *}
	 * param $elementId
	*/
	public static function Registration($elementId) {
		$timeData = Logs\TimeData::start();
		Loader::includeModule('iblock');
		$jsonData = "";
		$url = "";
		$dataInvoices = [];
		$entityTypeId = 148; //Смарт ОРД-Маркетинг
		$point = "BX_ORDLAB";

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
		$item = $factory -> getItem($elementId);
		if ($item)
		{
			$title = $item -> getData()['TITLE'];

			$itemInvoice_id = $item->getData()['UF_ORD_INVOICE_UID'];
			$itemInvoice_erirRegisteredAt = $item->getData()['UF_ORD_INVOICE_ERIR_REGISTERED_AT'];

			//*********** $itemInvoice_State
			$oUserFieldEnum = new \CUserFieldEnum();
			$rsGender = $oUserFieldEnum::GetList(array(), array(
				"ID" => $item->getData()['UF_ORD_INVOICE_STATUS'],
			));
			if($arGender = $rsGender->GetNext())
				$itemInvoice_State = $arGender["XML_ID"];
			//***********

			$contractId = $item->getData()['UF_ORD_CONTRACT_UID'];
			$number = $item->getData()['UF_ORD_NUMBER_ACT'];
			$date = $item->getData()['UF_ORD_DATE_ACT'];
			$startDate = $item->getData()['UF_ORD_STARTDATE_ACT'];
			$endDate = $item->getData()['UF_ORD_ENDDATE_ACT'];
			$amount = $item->getData()['UF_ORD_AMOUNT_ACT'];

			//*********** $clientRoleValue
			$oUserFieldEnum = new \CUserFieldEnum();
			$rsGender = $oUserFieldEnum::GetList(array(), array(
				"ID" => $item->getData()['UF_ORD_CLIENTROLE_ACT'],
			));
			if($arGender = $rsGender->GetNext())
				$clientRole = $arGender["XML_ID"];
			//***********

			//*********** $contractorRole
			$oUserFieldEnum = new \CUserFieldEnum();
			$rsGender = $oUserFieldEnum::GetList(array(), array(
				"ID" => $item->getData()['UF_ORD_CONTRACTORROLE_ACT'],
			));
			if($arGender = $rsGender->GetNext())
				$contractorRole = $arGender["XML_ID"];
			//***********

			//*********** $isVat
			$oUserFieldEnum = new \CUserFieldEnum();
			$rsGender = $oUserFieldEnum::GetList(array(), array(
				"ID" => $item->getData()['UF_ORD_ISVAT_ACT'],
			));
			if($arGender = $rsGender->GetNext())
				$isVat = (bool) $arGender["XML_ID"];
			//***********

			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Регистрация акта: ".$title;
			$objectData['METHOD'] = "POST";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			if($itemInvoice_id == "")
			{
				$dataInvoices["contractId"] = $contractId;
				$dataInvoices["number"] = $number;
				$dataInvoices["clientRole"] = $clientRole;
				$dataInvoices["contractorRole"] = $contractorRole;
				$dataInvoices["date"] = date("Y-m-d", strtotime($date));
				$dataInvoices["startDate"] = date("Y-m-d", strtotime($startDate));
				$dataInvoices["endDate"] = date("Y-m-d", strtotime($endDate));
				$dataInvoices["amount"] = (float) str_replace("|RUB","",$amount);
				$dataInvoices["isVat"] = $isVat;
				$dataInvoices["items"][] = [
					'contractId' => $contractId,
					'isVat' => $isVat,
					'amount' => (float) str_replace("|RUB","",$amount),
					'creatives' => self::GetCreatives($elementId)
				];

				$jsonData = json_encode($dataInvoices, JSON_UNESCAPED_UNICODE);
				if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
				{
					$url = self::URL . "/";
					$jsonResponse = \KPLab\Curl ::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);

					Logs\File::AddMessage($jsonResponse,"Ответ",LOG_ORDLAB_INVOICE);

					if (!empty($jsonResponse['success']))
					{
						$responseArray = json_decode($jsonResponse['success'], true);
						if ($responseArray['id'] !== "")
						{

							$UF_ORD_INVOICE_STATUS_ENUM = Entity::getUserFieldEnumByValue('UF_ORD_INVOICE_STATUS', $responseArray['state']);
							$UF_ORD_INVOICE_STATUS_ENUM_ID = $UF_ORD_INVOICE_STATUS_ENUM['ID'];
							if($UF_ORD_INVOICE_STATUS_ENUM == null) $UF_ORD_INVOICE_STATUS_ENUM_ID = 17654;

							$UF_ORD_INVOICE_STATUS_ENUM_VALUE = $UF_ORD_INVOICE_STATUS_ENUM['VALUE'];

							$fields = [
								'UF_ORD_INVOICE_UID' => $responseArray['id'],
								'UF_ORD_INVOICE_STATUS' => $UF_ORD_INVOICE_STATUS_ENUM_ID,
								'UF_ORD_INVOICE_ERIR_REGISTERED_AT' => date('Y-m-d H:i:s', strtotime($responseArray['erirRegisteredAt']))
							];

							$item -> setFromCompatibleData($fields);

							// Step 1: get operation
							$operation = $factory -> getUpdateOperation($item);

							// Step 2: config operation (optional)
							$operation
								-> disableCheckAccess()
								-> enableCheckWorkflows()
								-> enableCheckRequiredUserFields()
								-> enableAfterSaveActions()
								-> enableBizProc()
								-> enableAutomation();

							// Step 3: launch operation
							$operationResult = $operation -> launch();

							if ($operationResult -> isSuccess())
							{
								$message = "Регистрации Акта успешно прошла";
								\CRest ::call('crm.timeline.comment.add', [
									'fields' => [
										"ENTITY_ID" => $item -> getId(),
										"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
										"COMMENT" => "Регистрация акта [b]{$title}[/b] успешно прошла!"
									]
								]);
							}
							else
							{
								$statusCode = "Ошибка при сохранении";
								$detailError = $operationResult -> getErrorMessages();

								$arError['errorArray'] = [
									'statusCode' => $statusCode,
									'detailError' => $detailError
								];

								\CRest ::call('crm.timeline.comment.add', [
									'fields' => [
										"ENTITY_ID" => $item -> getId(),
										"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
										"COMMENT" => "Регистрация акта [b]{$title}[/b] не прошла! {$statusCode}! {$detailError}"
									]
								]);

								return $arError;
							}

							return $responseArray;
						}
					}
					else {
						$arErrors = $jsonResponse['errorArray'];
						if($arErrors['code'] == 'invalid-json') {
							$statusCode = "Ошибка в запросе";
							$detailError = $arErrors['detail'];
						}


						Logs\File::AddMessage($arErrors,"Ответ с ошибками (МАССИВ)",LOG_ORDLAB_INVOICE);

						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Регистрация акта [b]{$title}[/b] не прошла! {$statusCode}! {$detailError}"
							]
						]);
						return $jsonResponse;
					}
				}
			}
			else {

				CRest::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
						"COMMENT" => "Регистрация акта [b]{$title}[/b] не прошла, уже зарегистрирован!"
					]
				]);
				return ['state' => $itemInvoice_State];
			}
		}
		return false;
	}

	// GET /invoices/{id}/status - Получение информации о статусе зарегистрированной в ОРД Организации
	public static function GetStatus($elementId) {
		Loader::includeModule('iblock');

		$timeData = Logs\TimeData::start();
		$entityTypeId = 148; //Смарт ОРД-Маркетинг
		$point = "BX_ORDLAB";
		$jsonData = "";

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);

		if ($item)
		{
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Получение статуса акта: ".$item->getData()['TITLE'];
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";


			if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
				$url = self::URL."/".$item->getData()['UF_ORD_INVOICE_UID']."/status/";

				$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);

				if(!empty($jsonResponse['success'])) {
					$responseStr = "{".$jsonResponse['success']."}";
					$responseArray = json_decode($responseStr,true);

					if ($responseArray['id'] !== "")
					{
						$UF_ORD_INVOICE_STATUS_ENUM = Entity::getUserFieldEnumByValue('UF_ORD_INVOICE_STATUS', $responseArray['state']);
						$UF_ORD_INVOICE_STATUS_ENUM_ID = $UF_ORD_INVOICE_STATUS_ENUM['ID'];
						if($UF_ORD_INVOICE_STATUS_ENUM == null) $UF_ORD_INVOICE_STATUS_ENUM_ID = 17654;

						$UF_ORD_INVOICE_STATUS_ENUM_VALUE = $UF_ORD_INVOICE_STATUS_ENUM['VALUE'];

						$fields = [
							'UF_ORD_INVOICE_UID' => $responseArray['id'],
							'UF_ORD_INVOICE_STATUS' => $UF_ORD_INVOICE_STATUS_ENUM_ID,
							'UF_ORD_INVOICE_ERIR_REGISTERED_AT' => date('Y-m-d H:i:s', strtotime($responseArray['erirRegisteredAt']))
						];

						$item -> setFromCompatibleData($fields);

						// Step 1: get operation
						$operation = $factory -> getUpdateOperation($item);

						// Step 2: config operation (optional)
						$operation
							-> disableCheckAccess()
							-> enableCheckWorkflows()
							-> enableCheckRequiredUserFields()
							-> enableAfterSaveActions()
							-> enableBizProc()
							-> enableAutomation();

						// Step 3: launch operation
						$operationResult = $operation -> launch();

						if ($operationResult -> isSuccess())
						{
							$message = "Текущий статус регистрации Акта получен";
							\CRest ::call('crm.timeline.comment.add', [
								'fields' => [
									"ENTITY_ID" => $item -> getId(),
									"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
									"COMMENT" => "Текущий статус регистрации Акта: [b]{$UF_ORD_INVOICE_STATUS_ENUM_VALUE}[/b]!"
								]
							]);
						} else
						{
							//$message = $operationResult -> getErrorMessages();
							$statusCode = "Ошибка при сохранении";
							$detailError = $operationResult -> getErrorMessages();

							$arError['errorArray'] = [
								'statusCode' => $statusCode,
								'detailError' => $detailError
							];

							\CRest ::call('crm.timeline.comment.add', [
								'fields' => [
									"ENTITY_ID" => $item -> getId(),
									"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
									"COMMENT" => "Текущий статус регистрации Акта не получен! {$statusCode}! {$detailError}"
								]
							]);

							return $arError;
						}

					}
					return $responseArray['state'];
				}

			}

		} else {
			return null;
		}
	}

	public static function GetItems($elementId,$contractId,$amount,$isVat) {
		/**
		 *"items": [
		 * {
	     *  "contractId" : string(uuid),
		 *  "amount" : number (double); required,
	     *  "isVat" : boolean; required,
		 *  "creatives" : []
		 * }
		 *]
		 */
		$result = array();
		$result['contractId'] = $contractId;
		$result['isVat'] = $isVat;
		$result['amount'] = $amount;
		$result['creatives'] = self::GetCreatives($elementId);

		return $result;
	}

	public static function GetCreatives($elementId) {
		/*
		 * "creatives" : [
		 *  {
		 *      "creativeId" : string(uuid),
		 *      "platforms" : [
	     *          {
		 *              "platformId" : string; required,
		 *              "impsPlan" : int64; required,
		 *              "dateStartPlan" : date; required,
		 *              "dateEndPlan" : date; required,
		 *              "impsFact" : int64; required,
		 *              "dateStartFact" : date; required,
		 *              "dateEndFact" : date; required,
		 *              "amount" : number (double); required,
		 *              "amountPerShow" : number (double); required,
		 *              "isVat" : boolean; required
		 *          }
		 *      ]
		 *  }
		 * ]
		 *
		 */
		$resultCreative = array();
		Loader::includeModule('iblock');
		$IBLOCK_ID = 181;
		$arOrder = ['ID' => 'ASC'];
		$arFilter = [
			"IBLOCK_ID" => $IBLOCK_ID,
			"=PROPERTY_1087_VALUE" => $elementId,
			"ACTIVE_DATE" => "Y",
			"ACTIVE"=>"Y"
		];
		$arGroupBy = false;
		$arNavStartParams = [];
		$arSelect = ["*","PROPERTY_*"];

		$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

		while($ob = $res->GetNextElement()) {
			$arFields = $ob->GetFields();
			$arProps = $ob->GetProperties();
			$result['creativeId'] = $arProps['creativeId']['VALUE'];
			if($arProps['isVat']['VALUE'] == "Да") $isVat = true;
			if($arProps['isVat']['VALUE'] == "Нет") $isVat = false;

			if(!empty($arFields)) {
				$platforms["platformId"] = $arProps['platformId']['VALUE'];
				$platforms["impsPlan"] = (int) $arProps['impsPlan']['VALUE'];
				$platforms["dateStartPlan"] = date("Y-m-d", strtotime($arProps['dateStartPlan']['VALUE']));
				$platforms["dateEndPlan"] = date("Y-m-d", strtotime($arProps['dateEndPlan']['VALUE']));
				$platforms["impsFact"] = (int) $arProps['impsFact']['VALUE'];
				$platforms["dateStartFact"] = date("Y-m-d", strtotime($arProps['dateStartFact']['VALUE']));
				$platforms["dateEndFact"] = date("Y-m-d", strtotime($arProps['dateEndFact']['VALUE']));
				$platforms["amount"] = (float) str_replace("|RUB","",$arProps['amount']['VALUE']);
				$platforms["amountPerShow"] = (float) str_replace("|RUB","",$arProps['amountPerShow']['VALUE']);
				$platforms["isVat"] = $isVat;
			}

			$result['platforms'][] = $platforms;
			$resultCreative[] = $result;
		}

		return $resultCreative;
	}
}