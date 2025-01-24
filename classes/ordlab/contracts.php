<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_ORDLAB_CONTRACTS", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/contract.log");
//define("URL","https://sandbox.ord-lab.ru/api/v2/contracts");
//define("TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9
//.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");

class Contracts {
	const URL = "https://api.ord-lab.ru/api/v2/contracts";
	// POST /contracts - Регистрация Договора в ОРД
	/*
	 * {
	 * "type*": "contract", //intermediary-contract - Посреднический договор | contract - Договор оказания услуг | additional-agreement - Дополнительное соглашение | self-promo - Самореклама
	 * "clientId*": "bee899e8-17f7-11ed-861d-0242ac120002",
	 * "contractorId*": "bee89dd0-17f7-11ed-861d-0242ac120002",
	 * "isRegReport*": true,
	 * "actionType": "distribution", // other - Иное | distribution - Действия в целях распространения рекламы | conclude - Заключение договоров | commercial - Коммерческое представительство
	 * "subjectType*": "org-distribution", //representation - Представительство | other - Иное | org-distribution - Договор на организацию распространения рекламы | mediation - Посредничество | distribution - Договор на распространение рекламы
	 * "number": "123/22-02",
	 * "date*": "2022-05-23",
	 * "isVat*": true
	 * }
	 * 202 Accepted
	 * { "id": "bee89ef2-17f7-11ed-861d-0242ac120002", "erirRegisteredAt": "2022-09-12T13:25:50.052" }
	 */
	public static function Registration($elementId) {
		$timeData = Logs\TimeData::start();
		Loader::includeModule('iblock');
		$IBLOCK_ID = 177;
		$dataOrganization = [];
		$jsonData = "";
		$url = "";
		$entityTypeId = 148; //Смарт ОРД-Маркетинг
		$point = "BX_ORDLAB";

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);
		if ($item)
		{
			$title = $item -> getData()['TITLE'];
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Регистрация договора: ".$title;
			$objectData['METHOD'] = "POST";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			$TITLE = $item -> getData()['TITLE'];

			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_982_VALUE" => $elementId,
				"ACTIVE_DATE" => "Y",
				"ACTIVE"=>"Y"
			];
			$arGroupBy = false;
			$arNavStartParams = [];
			$arSelect = ["*","PROPERTY_*"];

			$itemContract = Entity::getItem($IBLOCK_ID, $elementId);
			$itemOrg = Entity::getItem(176, $elementId);
			//print_r($item);

			$itemContractId = $itemContract['FIELDS']['ID'];
			$itemContractProp_id = $itemContract['PROPERTIES']['id']['VALUE'];
			$itemContractProp_state = $itemOrg['PROPERTIES']['state']['VALUE_XML_ID'];

			if($itemContractProp_id == "")
			{
				$contractorId = $itemOrg['PROPERTIES']['id']['VALUE'];
				//print_r($contractorId);

				$PROPERTY_CONTRACTED_VALUES = [
					"contractorId" => $contractorId
				];

				\CIBlockElement ::SetPropertyValuesEx($itemContractId, $IBLOCK_ID, $PROPERTY_CONTRACTED_VALUES);

				$res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);

				while ($ob = $res -> GetNextElement())
				{
					$arFields = $ob -> GetFields();
					$arProps = $ob -> GetProperties();

					if($arProps['isRegReport']['VALUE_XML_ID'] == "true") $isRegReport = true;
					if($arProps['isRegReport']['VALUE_XML_ID'] == "false") $isRegReport = false;
					if($arProps['isVat']['VALUE_XML_ID'] == "true") $isVat = true;
					if($arProps['isVat']['VALUE_XML_ID'] == "false") $isVat = false;


					if (!empty($arFields))
					{
						$result['type'] = $arProps['type']['VALUE_XML_ID'];
						$result['clientId'] = $arProps['clientId']['VALUE'];
						$result['contractorId'] = $arProps['contractorId']['VALUE'];
						$result['isRegReport'] = $isRegReport;
						$result['subjectType'] = $arProps['subjectType']['VALUE_XML_ID'];
						$result['number'] = str_replace('"', '\"', $arProps['number']['VALUE']);
						$result['date'] = date('Y-m-d', strtotime($arProps['date']['VALUE']));
						$result['amount'] = 0;
						$result['isVat'] = $isVat;
					}

				}

				$jsonData = json_encode($result, JSON_UNESCAPED_UNICODE);

				if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
				{
					$url = self::URL . "/";
					$jsonResponse = \KPLab\Curl ::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);

					if (!empty($jsonResponse['success']))
					{

						$responseArray = json_decode($jsonResponse['success'], true);

						$PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

						if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
						{
							$PROPERTY_VALUES = [
								"id" => $responseArray['id'],
								"state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID),
								"erirRegisteredAt" => $responseArray['erirRegisteredAt']
							];
							\CIBlockElement ::SetPropertyValuesEx($itemContractId, $IBLOCK_ID, $PROPERTY_VALUES);
							\CRest ::call('crm.timeline.comment.add', [
								'fields' => [
									"ENTITY_ID" => $item -> getId(),
									"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
									"COMMENT" => "Регистрация договора для [b]{$title}[/b] успешно прошла!"
								]
							]);
							return $responseArray;
						}
					} else {
						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Регистрация договора для [b]{$title}[/b] не прошла! Ошибка!"
							]
						]);
						return "Регистрация договора для $title} не прошла! Ошибка!";
					}
				}
			} else {
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
						"COMMENT" => "Регистрация договора для [b]{$title}[/b] не прошла, уже зарегистрирован!"
					]
				]);
				return ['state'=>$itemContractProp_state];
			}
		}
		return false;
	}

	// PUT /contracts/{id} - Обновление информации о Договоре в ОРД

	// GET /contracts/{id} - Получение информации о зарегистрированном в ОРД Договоре
	public static function GetInfo($elementId) {
		$timeData = Logs\TimeData::start();

		Loader::includeModule('iblock');
		$IBLOCK_ID = 177;
		$jsonData = "";
		$url = "";
		$entityTypeId = 148; //Смарт ОРД-Маркетинг
		$point = "BX_ORDLAB";
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);

		if ($item)
		{
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = $item->getData()['TITLE'];
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_982_VALUE" => $elementId,
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
			}
			if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {

				$url = self::URL . "/" . $arProps["id"]['VALUE'];

				$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);
				//print_r($jsonResponse);
				if(!empty($jsonResponse['success'])) {
					$responseStr = "{".$jsonResponse['success']."}";
					$responseArray = json_decode($responseStr, true);
					return $responseArray;
				}
				return null;
			}
		}
		return null;
	}

	// GET /contracts/{id}/status - Получение информации о статусе зарегистрированного в ОРД Договора
	public static function GetStatus($elementId) {
		$timeData = Logs\TimeData::start();

		Loader::includeModule('iblock');
		$IBLOCK_ID = 177;
		$jsonData = "";
		$url = "";
		$entityTypeId = 148; //Смарт ОРД-Маркетинг
		$point = "BX_ORDLAB";
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);

		$itemContract = Entity::getItem($IBLOCK_ID, $elementId);
		$itemContractId = $itemContract['FIELDS']['ID'];

		if ($item)
		{
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Получение статуса договора: ".$item->getData()['TITLE'];
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_982_VALUE" => $elementId,
				"ACTIVE_DATE" => "Y",
				"ACTIVE"=>"Y"
			];
			$arGroupBy = false;
			$arNavStartParams = [];
			$arSelect = ["*","PROPERTY_*"];

			$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

			while($ob = $res->GetNextElement())
			{
				$arFields = $ob -> GetFields();
				$arProps = $ob -> GetProperties();

				if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
					$url = self::URL."/".$arProps["id"]['VALUE']."/status/";

					$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);

					if(!empty($jsonResponse['success'])) {
						$responseStr = "{".$jsonResponse['success']."}";
						$responseArray = json_decode($responseStr,true);

						$PROPERTY_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

						$PROPERTY_VALUES = [];
						if ($PROPERTY_ENUM_ID !== "")
						{
							$PROPERTY_VALUES = [
								"state" => Array("VALUE" => $PROPERTY_ENUM_ID)
							];
						}

						\CIBlockElement ::SetPropertyValuesEx($itemContractId, $IBLOCK_ID, $PROPERTY_VALUES);

						$currentState = Entity::getPropertyEnumValueByArray($responseArray['state'], $IBLOCK_ID);

						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Текущий статус Договора: [b]{$currentState}[/b]!"
							]
						]);

						return $responseArray['state'];
					}

				}

			}
			return null;
		} else {
			return null;
		}
	}

	// POST /contracts/{id}/sync - Возобновление неоконченной регистрации или обновления данных Договора
}