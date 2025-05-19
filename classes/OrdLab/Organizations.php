<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;
use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Diag\Debug;


define("LOG_ORDLAB_ORG", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/organization.log");
//define("URL","https://sandbox.ord-lab.ru/api/v2/organizations");
//define("TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9
//.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");

class Organizations {
	const point = "BX_ORDLAB";
	const URL = "https://api.ord-lab.ru/api/v4/organizations";
	// POST /organizations - Регистрация Организации в ОРД
	//В теле запроса передается объект Organization без id
	/*
	 * POST /organizations {
	 * "type": "ul", // ffl, ful, ip, fl, ul
	 * "isOrs": false,
	 * "isRr": false,
	 * "inn": "77012345678",
	 * "name": "ООО "Некий рекламодатель"",
	 * "platforms": [
	 *      {
     *          "externalId":"myPlatform",
	 *          "isOwned":"false", // true - Площадка принадлежит контрагенту
	 *          "type":"site", // is, apps, site
	 *          "name":"Моя Площадка",
	 *          "url": "http://myplatform.org"
	 *      }
	 *  ]
	 * }
	 * param $elementId
	*/
	public static function Registration($elementId, $IBLOCK_ID = 176, $entityTypeId = 148)
	{

		$timeData = Logs\TimeData::start();
		Loader::includeModule('iblock');
		$dataOrganization = [];
		$jsonData = "";
		$url = self::URL;
		$isOrs = false;
		$itemOrg = Entity::getItem($IBLOCK_ID, $elementId);
		$itemOrgProp_id = $itemOrg['PROPERTIES']['id']['VALUE'];
		$itemOrgProp_state = $itemOrg['PROPERTIES']['state']['VALUE_XML_ID'];

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
		$item = $factory -> getItem($elementId);
		
		if ($item)
		{
			
			$title = $item -> getData()['TITLE'];
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Регистрация организации: ".$title;
			$objectData['METHOD'] = "POST";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";print_r($itemOrgProp_id);
			
			if($itemOrgProp_id == "")
			{
				
				if($itemOrg['PROPERTIES']['isRr']['VALUE_XML_ID'] == "true") $isRr = true;
				if($itemOrg['PROPERTIES']['isRr']['VALUE_XML_ID'] == "false") $isRr = false;

				$dataOrganization['type'] = $itemOrg['PROPERTIES']['type']['VALUE_XML_ID'];
				$dataOrganization['isOrs'] = $isOrs;
				$dataOrganization['isRr'] = $isRr;
				$dataOrganization['inn'] = $itemOrg['PROPERTIES']['inn']['VALUE'];
				$dataOrganization['name'] = str_replace('"', '\"', $itemOrg['PROPERTIES']['name']['VALUE']);
				$dataOrganization['platforms'] = self::GetPlatforms($elementId);
				if($dataOrganization['type']=='ul')$dataOrganization['kpp'] = $itemOrg['PROPERTIES']['kpp']['VALUE'];

				$jsonData = json_encode($dataOrganization, JSON_UNESCAPED_UNICODE);
				// print_r($jsonData);
				if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
				{
					$url = self::URL . "/";
					$jsonResponse = \KPLab\Curl ::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
					// print_r($jsonResponse);
					if (!empty($jsonResponse['success']))
					{
						$responseArray = json_decode($jsonResponse['success'], true);

						$itemOrgId = $itemOrg['FIELDS']['ID'];

						$PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);
						// print_r($responseArray);
						if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
						{
							$PROPERTY_VALUES = [
								"id" => $responseArray['id'],
								"state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID),
								"erirRegisteredAt" => $responseArray['erirRegisteredAt']
							];
							\CIBlockElement ::SetPropertyValuesEx($itemOrgId, $IBLOCK_ID, $PROPERTY_VALUES);
							\CRest ::call('crm.timeline.comment.add', [
								'fields' => [
									"ENTITY_ID" => $item -> getId(),
									"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
									"COMMENT" => "Регистрация организации [b]{$title}[/b] успешно прошла!"
								]
							]);
							self::updatePlatforms($responseArray['platforms']);
							return $responseArray;
						}
					} else {
						$arErrors = $jsonResponse['errorArray'];
						if($arErrors['code'] == 'invalid-json') {
							$statusCode = "Ошибка в запросе";
							$detailError = $arErrors['detail'];
						}


						Logs\File::AddMessage($arErrors,"Ответ с ошибками (МАССИВ)",LOG_ORDLAB_ORG);

						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $item -> getId(),
								"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
								"COMMENT" => "Регистрация организации [b]{$title}[/b] не прошла! {$statusCode}! {$detailError}"
							]
						]);

						return $jsonResponse;
					}

				}
			} else {

				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
						"COMMENT" => "Регистрация организации [b]{$title}[/b] не прошла, уже зарегистрирована!"
					]
				]);
				return ['state'=>$itemOrgProp_state];
			}
		}
		return false; //{ "id": "bee899e8-17f7-11ed-861d-0242ac120002", "erirRegisteredAt": "2022-09-12T13:20:50.052" }
	}

	// PUT /organizations/{id} - Обновление информации об Организации в ОРД
    //В теле запроса передается объект Organization
    public static function Update($elementId, $IBLOCK_ID = 176, $entityTypeId = 148) { //Смарт ОРД-Маркетинг
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');

        $dataOrganization = [];
        $jsonData = "";
        $url = "";
        $isOrs = false;

        $itemOrg = Entity::getItem($IBLOCK_ID, $elementId);

        $itemOrgProp_id = $itemOrg['PROPERTIES']['id']['VALUE'];

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $item = $factory->getItem($elementId);

        if ($item)
        {
            $title = $item -> getData()['TITLE'];
            $objectData['ITEM_ID'] = $elementId;
            $objectData['ITEM_TYPE_ID'] = $entityTypeId;
            $objectData['ITEM_TITLE'] = "Обновление организации: ".$title;
            $objectData['METHOD'] = "POST";
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

            if($itemOrgProp_id !== "")
            {

                if($itemOrg['PROPERTIES']['isRr']['VALUE_XML_ID'] == "true") $isRr = true;
                if($itemOrg['PROPERTIES']['isRr']['VALUE_XML_ID'] == "false") $isRr = false;

                $dataOrganization['id'] = $itemOrgProp_id;
                $dataOrganization['type'] = $itemOrg['PROPERTIES']['type']['VALUE_XML_ID'];
                $dataOrganization['isOrs'] = $isOrs;
                $dataOrganization['isRr'] = $isRr;
                $dataOrganization['inn'] = $itemOrg['PROPERTIES']['inn']['VALUE'];
                $dataOrganization['name'] = $itemOrg['PROPERTIES']['name']['VALUE'];
				$dataOrganization['kpp'] = $itemOrg['PROPERTIES']['kpp']['VALUE'];
                $dataOrganization['platforms'] = self::GetPlatforms($elementId);

                $jsonData = json_encode($dataOrganization, JSON_UNESCAPED_UNICODE);
                if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
                {
                    $url = self::URL."/".$itemOrgProp_id."/";
                    $jsonResponse = \KPLab\Curl::put_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

                    // Logs\File::AddMessage($jsonResponse,"Ответ (МАССИВ)",LOG_ORDLAB_ORG);

                    if (!empty($jsonResponse['success']))
                    {
                        $responseStr = "{" . $jsonResponse['success'] . "}";

                        $responseArray = json_decode($responseStr, true);

                        $itemOrgId = $itemOrg['FIELDS']['ID'];

                        $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                        if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
                        {
                            \CRest ::call('crm.timeline.comment.add', [
                                'fields' => [
                                    "ENTITY_ID" => $item -> getId(),
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Обновление данных организации [b]{$title}[/b] успешно прошло!"
                                ]
                            ]);
                            return null;
                        }
                    } else {
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $item -> getId(),
                                "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                "COMMENT" => "Обновление данных организации [b]{$title}[/b] не прошло! Ошибка!"
                            ]
                        ]);
                        $responseArray = json_decode($jsonResponse['error'], true);
                        return $responseArray;
                    }
                }
            } else {
                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $item -> getId(),
                        "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                        "COMMENT" => "ID организации [b]{$title}[/b] не существует! Сначало зарегистрируйте организацию!"
                    ]
                ]);
            }
        }
    }

	// GET /organizations/{id} - Получение информации о зарегистрированной в ОРД Организации
	public static function GetInfo($elementId, $IBLOCK_ID = 176, $entityTypeId = 148) {
		$timeData = Logs\TimeData::start();

		Loader::includeModule('iblock');

		
		$jsonData = "";
		$url = "";

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);

		if ($item)
		{
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = $item->getData()['TITLE'];
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

			//$TIP_ORGANIZATSIYA_ORD__ELEMENTITEM_ENUM_ID = $item->getData()['UF_CRM_73_1697181697'];
			//$NAZVANIE_ORGANIZATSII_ORGANIZATSIYA_ORD = $item->getData()['UF_CRM_73_1697181820'];
			//$INN_ORGANIZATSIYA_ORD = $item->getData()['UF_CRM_73_1697181845'];
			//$ORGANIZATSIYA_YAVLYAETSYA_REKLAMORASPROSTRANITELEM__ELEMENTITEM_ENUM_ID = $item->getData()['UF_CRM_73_1697181936'];
			//AddMessage2Log($ORGANIZATSIYA_YAVLYAETSYA_REKLAMORASPROSTRANITELEM__ELEMENTITEM_ENUM_ID, "ORGANIZATSIYA_YAVLYAETSYA_REKLAMORASPROSTRANITELEM__ELEMENTITEM_ENUM_ID");

			$arSelect = ["*","PROPERTY_*"];

			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_1014_VALUE" => $elementId,
				"ACTIVE_DATE" => "Y",
				"ACTIVE"=>"Y"
			];

			$res = \CIBlockElement::GetList(
				['ID' => 'ASC'],
				$arFilter,
				false,
				[],
				$arSelect
			);
			while($ob = $res->GetNextElement())
			{
				$arFields = $ob -> GetFields();
				//AddMessage2Log($arFields, "Массив в списке Регистрации ОРД");
				//AddMessage2Log($arFields["PROPERTY_1013_VALUE"], "Идентификатор организации API");

					$url = self::URL . "/" . $arFields["PROPERTY_1013_VALUE"];

			}
			if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
				$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
				if(!empty($jsonResponse['success'])) {
					$responseStr = "{".$jsonResponse['success']."}";
					$responseArray = json_decode($responseStr, true);
					return $responseArray;
				}
				return null;
			}
			return null;
		} else {
			return null;
		}
	}

	// GET /organizations/{id}/status - Получение информации о статусе зарегистрированной в ОРД Организации
	public static function GetStatus($elementId, $IBLOCK_ID = 176, $entityTypeId = 148) {
		Loader::includeModule('iblock');

		$timeData = Logs\TimeData::start();
		$jsonData = "";

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		$item = $factory->getItem($elementId);

		if ($item)
		{
			$objectData['ITEM_ID'] = $elementId;
			$objectData['ITEM_TYPE_ID'] = $entityTypeId;
			$objectData['ITEM_TITLE'] = "Получение статуса организации: ".$item->getData()['TITLE'];
			$objectData['METHOD'] = "GET";
			$objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";



			$arOrder = ['ID' => 'ASC'];
			$arFilter = [
				"IBLOCK_ID" => $IBLOCK_ID,
				"=PROPERTY_1014_VALUE" => $elementId,
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

					$jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
					
					if(!empty($jsonResponse['success'])) {
						$responseStr = "{".$jsonResponse['success']."}";
						$responseArray = json_decode($responseStr,true);

						if($responseArray['state'] == "PENDING") $STATUS_ORGANIZATSII_ORD_API_ENUM_ID = 606;
						if($responseArray['state'] == "ACCEPTED") $STATUS_ORGANIZATSII_ORD_API_ENUM_ID = 607;
						if($responseArray['state'] == "MEDIA LOADING") $STATUS_ORGANIZATSII_ORD_API_ENUM_ID = 608;
						if($responseArray['state'] == "APPROVED") $STATUS_ORGANIZATSII_ORD_API_ENUM_ID = 609;
						if($responseArray['state'] == "DECLINED") $STATUS_ORGANIZATSII_ORD_API_ENUM_ID = 610;


						$PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

						if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
						{
							$PROPERTY_VALUES = [
								"state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID)
							];
							\CIBlockElement ::SetPropertyValuesEx($arFields["ID"], $IBLOCK_ID, $PROPERTY_VALUES);
							$currentState = Entity::getPropertyEnumValueByArray($responseArray['state'], $IBLOCK_ID);

							\CRest ::call('crm.timeline.comment.add', [
								'fields' => [
									"ENTITY_ID" => $item -> getId(),
									"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
									"COMMENT" => "Текущий статус Организации: [b]{$currentState}[/b]!"
								]
							]);

						}
						return $responseArray['state'];
					}

				}

			}

		} else {
			return null;
		}
	}

	// POST /organizations/{id}/sync - Возобновление неоконченной регистрации или обновления данных Организации

	public static function GetPlatforms($elementId, $IBLOCK_ID = 175) {
		/**
		 * "platforms": [
		 * {
		*	"externalId": "myPlatform",
		*	"isOwned": false,
		*	"name": "Моя Площадка",
		*	"type": "site",
		*	"url": "http://myplatform.org"
		*	}
		 * ]
		 */
		$result = array();
		Loader::includeModule('iblock');
		$k = 0;
		$arOrder = ['ID' => 'ASC'];
		$arFilter = [
			"IBLOCK_ID" => $IBLOCK_ID,
			"=PROPERTY_1007_VALUE" => $elementId,
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

			if($arProps['isOwned']['VALUE_XML_ID'] == "true") $isOwned = true;
			if($arProps['isOwned']['VALUE_XML_ID'] == "false") $isOwned = false;

			if(!empty($arFields)) {
				$url = $arProps['url']['VALUE'][0];
				if($arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'] !== "") $result[$k]['platformId'] = $arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'];
				$result[$k]['externalId'] = "platform".$arFields['ID'];
				$result[$k]['isOwned'] = $isOwned;
				$result[$k]['type'] = $arProps['type']['VALUE_XML_ID'];
				$result[$k]['name'] = $arFields['NAME'];
				if($url !== '')	$result[$k]['url'] = $url;
			}
			$k++;
		}
		//print_r($result);
		// Logs\File::AddMessage($result,"GetPlatforms (МАССИВ)",LOG_ORDLAB_ORG);
		return $result;
	}

	public static function updatePlatforms($platforms, $IBLOCK_ID = 175) {
		/**
		 * "platforms": [
		 * {
		 * "externalId":"myPlatform",
		 * "isOwned*":"false",
		 * "type*":"site",
		 * "name*":"Моя Площадка",
		 * "url": "http://myplatform.org"
		 * "platformId": "5a9420b2-d6ab-417d-b403-34a3b000bddd"
		 * }
		 * ]
		 */

		Loader::includeModule('iblock');

		// Logs\File::AddMessage($platforms,"Входящие данные",LOG_ORDLAB_ORG);

		if(!empty($platforms) && isset($platforms) && is_array($platforms)) {
			foreach($platforms as $platform) {
				$ELEMENT_ID = str_replace("platform","",$platform["externalId"]); // код элемента
				$PROP[1089] = $platform["platformId"];
				$PROPERTY_VALUES = [
					"IDENTIFIKATOR_PLOSHCHADKI_API" => $platform["platformId"]
				];

				// Установим новое значение для данного свойства данного элемента
				\CIBlockElement::SetPropertyValuesEx($ELEMENT_ID, $IBLOCK_ID, $PROPERTY_VALUES);
			}
			return true;
		} else {
			return null;
		}

	}
}
