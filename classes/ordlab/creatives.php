<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_ORDLAB_CREATIVES", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/creatives.log");
//define("URL","https://sandbox.ord-lab.ru/api/v2/creatives");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");

//Маркировка креативов
class Creatives {
	const URL = "https://api.ord-lab.ru/api/v2/creatives";
	// POST /creatives - Регистрация Креатива в ОРД
	/*
	//{
	// "contractId*": "df56689e-17f8-11ed-861d-0242ac120002",
	// "description": "Баннер РК 123123",
	// "type*": "cpc",
	// "form*": "banner",
	// "url": ["https://advertiser.ru/landing"],
	// "isSocial*": false,
	// "creativeOkveds*": ["062"],
	// "targetGeo": ["bb035cc3-1dc2-4627-9d25-a1bf2d4b936b"],
	// "creativeData": [
	//      {
	//          "mediaUrl*": "https://advertiser.ru/logo.gif",
	//          "description*": "Логотип"
	//      }
	//  ]
	// }
	*/
	public static function Registration($elementId) {
		$timeData = Logs\TimeData::start();
		Loader::includeModule('iblock');
		$IBLOCK_ID = 174;
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
			$objectData['ITEM_TITLE'] = "Регистрация креатива: ".$title;
			$objectData['METHOD'] = "POST";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			$title = $item -> getData()['TITLE'];
			//$inn = $item -> getData()['UF_CRM_73_1697181845'];

			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_998_VALUE" => $elementId,
				"ACTIVE_DATE" => "Y",
				"ACTIVE"=>"Y"
			];
			$arGroupBy = false;
			$arNavStartParams = [];
			$arSelect = ["*","PROPERTY_*"];

			$itemContract = Entity::getItem(177, $elementId);
			$itemOrg = Entity::getItem(176, $elementId);
			$inn = $itemOrg['PROPERTIES']['inn']['VALUE'];

			$contractProp_id = $itemContract['PROPERTIES']['id']['VALUE'];
			//print_r($contractProp_id);
			if($contractProp_id !== "")
			{
				$k = 0;
				$res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);
				while ($ob = $res -> GetNextElement())
				{

					$arFields = $ob -> GetFields();
					$creativeName = $arFields['NAME'];
					$PROPERTY_CREATIVE_VALUES = [
						"contractId" => $contractProp_id
					];
					\CIBlockElement ::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID, $PROPERTY_CREATIVE_VALUES);

					$itemCreativeById = Entity::getItembyID($arFields['ID'], $IBLOCK_ID, $elementId);

					$itemCreativeProp_id = $itemCreativeById['PROPERTIES']['id']['VALUE'];
					$itemCreativeProp_state = $itemCreativeById['PROPERTIES']['state']['VALUE_XML_ID'];
					$itemCreativeProp_marker = $itemCreativeById['PROPERTIES']['marker']['VALUE'];
					$itemCreativeProp_SDELKA = $itemCreativeById['PROPERTIES']['SDELKA_CRM']['VALUE'];

					//print_r($itemCreativeProp_id);
					//print_r($itemCreativeProp_marker);

					if($itemCreativeProp_id == "" && $itemCreativeProp_marker == "") {
						$arProps = $ob -> GetProperties();
						if($arProps['isSocial']['VALUE_XML_ID'] == "true") $isSocial = true;
						if($arProps['isSocial']['VALUE_XML_ID'] == "false") $isSocial = false;
						if (!empty($arFields))
						{
							$result['contractId'] = $contractProp_id;
							$result['description'] = $arProps['description']['VALUE'];
							$result['type'] = $arProps['type']['VALUE_XML_ID'];
							$result['form'] = $arProps['form']['VALUE_XML_ID'];
							$result['url'] = $arProps['url']['VALUE'];
							$result['isSocial'] = $isSocial;
							$result['erirIdType'] = $arProps['erirIdType']['VALUE_XML_ID'];
							$result['creativeOkveds'] = $arProps['creativeOkveds']['VALUE'];
							$result['creativeData'][] = [
								'mediaUrl' => $arProps['creativeData_mediaUrl']['VALUE'],
								'description' => $arProps['creativeData_description']['VALUE']
							];


						}
						$jsonData = json_encode($result, JSON_UNESCAPED_UNICODE);
						$result = [];
						if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
						{
							$url = self::URL."/";
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
										"erirRegisteredAt" => $responseArray['erirRegisteredAt'],
										"marker" => $responseArray['marker']
									];
									\CIBlockElement ::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID, $PROPERTY_VALUES);
									$marker = $responseArray['marker'];
									\CRest ::call('crm.timeline.comment.add', [
										'fields' => [
											"ENTITY_ID" => $item -> getId(),
											"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
											"COMMENT" => "Регистрация креатива [b]{$creativeName}[/b] успешно прошла! Рекламный маркер: [b]{$marker}[/b]!"
										]
									]);
									$link = "https://seller-capital.ru/?erid={$marker}&utm_source=seller&utm_medium=referral&utm_campaign=online&utm_content={$inn}&utm_term={$marker}";
									\CRest ::call('crm.timeline.comment.add', [
										'fields' => [
											"ENTITY_ID" => $item -> getId(),
											"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
											"COMMENT" => "Рекламный маркер: [b]{$marker}[/b]! Рекламная ссылка: {$link}"
										]
									]);



									$factoryDeals = \Bitrix\Crm\Service\Container::getInstance()->getFactory(2);
									$itemDeal = $factoryDeals->getItem($itemCreativeProp_SDELKA);
									if($itemDeal) {
										\CRest ::call('crm.timeline.comment.add', [
											'fields' => [
												"ENTITY_ID" => $itemCreativeProp_SDELKA,
												"ENTITY_TYPE" => "deal",
												"COMMENT" => "Рекламный маркер: [b]{$marker}[/b]! Рекламная ссылка: {$link}"
											]
										]);
										$data = $itemDeal->getCompatibleData();
										$linksArray = $data['UF_CRM_1707998967'];
										//$markerArray = $data['UF_CRM_1707998967'];
										Logs\File::AddMessage($linksArray,"linksArray",LOG_ORDLAB_CREATIVES);
										array_push($linksArray, $link);
										Logs\File::AddMessage($linksArray,"linksArray2",LOG_ORDLAB_CREATIVES);
										$fields = ['UF_CRM_1707998967' => $linksArray];
										$itemDeal->setFromCompatibleData($fields);
										$result = $itemDeal->save();
									}
								}

							} else {
								\CRest ::call('crm.timeline.comment.add', [
									'fields' => [
										"ENTITY_ID" => $item -> getId(),
										"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
										"COMMENT" => "Регистрация креатива [b]{$creativeName}[/b] не прошла! Ошибка!"
									]
								]);
								$textError .= "Регистрация креатива {$creativeName} не прошла! Ошибка! | ";
							}
						}
					} else {

						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Регистрация креатива [b]{$creativeName}[/b] не прошла, уже зарегистрирован!"
							]
						]);
						$textError .=  "Регистрация креатива {$creativeName} не прошла, уже зарегистрирован! | ";
					}
					$resCreative[$k] = $responseArray;
					$k++;
				}
				if($textError !== "") {
					return $textError;
				} else {
					return $resCreative;
				}

			} else {
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
						"COMMENT" => "Регистрация креативов не прошла! Нет договора!"
					]
				]);
				return "Регистрация креативов не прошла! Нет договора!";
			}
		}
		return false;
	}

	// GET /creatives/{id} - Получение информации о зарегистрированном в ОРД Креативе
	public static function GetInfo($elementId) {
		$timeData = Logs\TimeData::start();

		Loader::includeModule('iblock');
		$IBLOCK_ID = 174;
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
				"=PROPERTY_998_VALUE" => $elementId,
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


					$url = self::URL . $arProps["id"]['VALUE'];
			}
			if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
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

	// GET /creatives/{id}/status - Получение информации о статусе зарегистрированного в ОРД Креатива
	public static function GetStatus($elementId) {
		$timeData = Logs\TimeData::start();

		Loader::includeModule('iblock');
		$IBLOCK_ID = 174;
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
			$title = $item->getData()['TITLE'];
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Получение статуса креатива: ".$title;
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_998_VALUE" => $elementId,
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
					$url = self::URL.$arProps["id"]['VALUE']."/status/";

					$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);

					if(!empty($jsonResponse['success'])) {
						$responseStr = "{".$jsonResponse['success']."}";
						$responseArray = json_decode($responseStr,true);

						//print_r($responseArray);

						$PROPERTY_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

						$PROPERTY_VALUES = [];
						if ($PROPERTY_ENUM_ID !== "")
						{
							$PROPERTY_VALUES = [
								"state" => Array("VALUE" => $PROPERTY_ENUM_ID)
							];
						}

						\CIBlockElement ::SetPropertyValuesEx($itemContractId, $IBLOCK_ID, $PROPERTY_VALUES);

						$creativeName = $arFields['NAME'];
						$currentState = Entity::getPropertyEnumValueByArray($responseArray['state'], $IBLOCK_ID);

						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Текущий статус Креатива {$creativeName}: [b]{$currentState}[/b]!"
							]
						]);
						return $responseArray['state'];
					}

				}

			}
			return true;
		} else {
			return null;
		}
	}

	// PUT /creatives/{id} - Обновление информации о Креативе в ОРД
}