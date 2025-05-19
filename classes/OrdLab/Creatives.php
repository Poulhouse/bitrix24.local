<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_ORDLAB_CREATIVES", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/creatives.log");
//define("URL","https://sandbox.ord-lab.ru/api/v2/creatives");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");
//Маркировка креативов
class Creatives {
    const URL = "https://api.ord-lab.ru/api/v4/creatives";
    const point = "BX_ORDLAB";
    // POST /creatives - Регистрация Креатива в ОРД
    /*
    //{
    // "cid": [
    // 	"string"
    // ],
    // "contractId": [
    // 	"string"
    // ],
    // "creativeData": [
    // 	{
    // 	"description": "string",
    // 	"externalId": "string",
    // 	"mediaUrl": "string",
    // 	"mediaUrlFileType": "image",
    // 	"textData": "string"
    // 	}
    // ],
    // "creativeOkveds": [
    // 	"string"
    // ],
    // "description": "string",
    // "erirIdType": "MED",
    // "form": "text-block",
    // "id": "string",
    // "isNative": true,
    // "isSocial": true,
    // "ktu": [
    // 	"string"
    // ],
    // "marker": "string",
    // "selfPromotionOrganizationId": "string",
    // "targetAudience": {
    // 	"age": [
    // 	"string"
    // 	],
    // 	"geo": [
    // 	"string"
    // 	],
    // 	"sex": [
    // 	"string"
    // 	]
    // },
    // "type": "other",
    // "url": [
    // 	"string"
    // ]
    // }
    */

    public static function Registration($elementId,$IBLOCK_ID = 174,$entityTypeId = 148) {
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');
        $textError = "";
        $dataOrganization = [];
        $jsonData = "";
        $url = self::URL;

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
						if($arProps['isSocialQuota']['VALUE_XML_ID'] == "true") $isSocialQuota = true;
                        if($arProps['isSocialQuota']['VALUE_XML_ID'] == "false") $isSocialQuota = false;
                        if (!empty($arFields))
                        {
                            $result['contractId'][] = $contractProp_id;
							$result['description'] = self::remove_emoji($arProps['description']['VALUE']);
							//$result['type'] = $arProps['type']['VALUE_XML_ID']; //Убрать v4
                            $result['form'] = $arProps['form']['VALUE_XML_ID'];
                            $result['url'] = $arProps['url']['VALUE'];
                            $result['isSocial'] = $isSocial;
							$result['isSocialQuota'] = $isSocialQuota;
                            $result['erirIdType'] = $arProps['erirIdType']['VALUE_XML_ID'];
							//$result['creativeOkveds'] = $arProps['creativeOkveds']['VALUE'];

							if($arProps['creativeData_mediaUrl']['VALUE']!=null){
								$result['creativeData'][] = [
									//'textData' => self::remove_emoji($arProps['creativeData_textData']['VALUE']['TEXT']),
								//'description' => self::remove_emoji($arProps['creativeData_description']['VALUE']), //Убрать v4
									'mediaUrlFileType' => 'image',
									'mediaUrl' => $arProps['creativeData_mediaUrl']['VALUE'][0],
								];
							}

							if($arProps['creativeData_textData']['VALUE']!=null){
									$result['creativeData'][] = [
									'textData' => self::remove_emoji($arProps['creativeData_textData']['VALUE']['TEXT']),
									//'description' => self::remove_emoji($arProps['creativeData_description']['VALUE']), //Убрать v4
									//'mediaUrlFileType' => $arProps['creativeData_mediaUrl']['VALUE'],
										//'mediaUrl' => $arProps['creativeData_mediaUrl']['VALUE'][0],
										//'mediaUrlFileType' => 'image',
								];
							}
                            // $result['ktu'][] = '29.2.3';
                            $result['ktu'] = self::getKtuFromId($arProps['ktu']['VALUE'], $arProps['ktu']['LINK_IBLOCK_ID']);
                        }
                        $jsonData = json_encode($result, JSON_UNESCAPED_UNICODE);
						var_dump($result);

                        if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
                        {
                            $url = self::URL."/";
                            $jsonResponse = \KPLab\Curl ::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

							print_r($jsonResponse);
							$result = [];
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
                                    $itemDeal = $factoryDeals->getItem($elementId);
                                    if($itemDeal) {
                                        \CRest ::call('crm.timeline.comment.add', [
                                            'fields' => [
                                                "ENTITY_ID" => $elementId,
                                                "ENTITY_TYPE" => "deal",
                                                "COMMENT" => "Рекламный маркер: [b]{$marker}[/b]! Рекламная ссылка: {$link}"
                                            ]
                                        ]);
                                        $data = $itemDeal->getCompatibleData();
                                        $linksArray = $data['UF_CRM_1707998967'];
                                        //$markerArray = $data['UF_CRM_1707998967'];
                                        // Logs\File::AddMessage($linksArray,"linksArray",LOG_ORDLAB_CREATIVES);
                                        if(!is_array($linksArray)) { $linksArray = []; }
                                        array_push($linksArray, $link);
                                        // Logs\File::AddMessage($linksArray,"linksArray2",LOG_ORDLAB_CREATIVES);
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

                        // \CRest ::call('crm.timeline.comment.add', [
                        // 	'fields' => [
                        // 		"ENTITY_ID" => $item -> getId(),
                        // 		"ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                        // 		"COMMENT" => "Регистрация креатива [b]{$creativeName}[/b] не прошла, уже зарегистрирован!"
                        // 	]
                        // ]);
                        // $textError .=  "Регистрация креатива {$creativeName} не прошла, уже зарегистрирован! | ";
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
    public static function GetInfo($elementId, $IBLOCK_ID = 174, $entityTypeId = 148) {
        $timeData = Logs\TimeData::start();

        Loader::includeModule('iblock');
        $jsonData = "";
        $url = self::URL;
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
                $jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
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
    public static function GetStatus($elementId, $IBLOCK_ID = 174, $entityTypeId = 148) {
        $timeData = Logs\TimeData::start();

        Loader::includeModule('iblock');
        $jsonData = "";
        $url = self::URL;
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
                    $url = self::URL."/".$arProps["id"]['VALUE']."/status/";

                    $jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, $point);
                    print_r($url);
                    print_r($jsonResponse);
                    if(!empty($jsonResponse['success'])) {
                        $responseStr = "{".$jsonResponse['success']."}";
                        $responseArray = json_decode($responseStr,true);

                        print_r($responseArray);

                        $PROPERTY_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                        $PROPERTY_VALUES = [];
                        if ($PROPERTY_ENUM_ID !== "")
                        {
                            $PROPERTY_VALUES = [
                                "state" => Array("VALUE" => $PROPERTY_ENUM_ID)
                            ];
                        }

                        \CIBlockElement ::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID, $PROPERTY_VALUES);

                        $creativeName = $arFields['NAME'];
                        $currentState = Entity::getPropertyEnumValueByArray($responseArray['state'], $IBLOCK_ID);

                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $item -> getId(),
                                "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                "COMMENT" => "Текущий статус Креатива {$creativeName}: [b]{$currentState}[/b]!"
                            ]
                        ]);
                    }

                }

            }
            return true;
        } else {
            return null;
        }
    }

    //Вспомогательная функция, убрать все эмоджи
    public static function remove_emoji($string = null) {
        // Удаляем все символы, которые не являются буквами, цифрами, пробелами или знаками препинания
        if($string!=null){
            $newStr = preg_replace('/[^\p{L}\p{N}\p{Z}\p{P}]/u', '', $string);
            echo $newStr;
            return $newStr;
        }else return null;
    }

    //Находим ККТУ по ID из элемента списка в креативе
    private static function getKtuFromId($ktuId, $IBLOCK_ID) {

        foreach ($ktuId as $numElem => $idElem){

            $resu = \CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => $IBLOCK_ID,
                    "ID" => $idElem,
                ],
                false,
                [],
                ['*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
            );
            if ($Element = $resu->GetNextElement()) {

                if($Props = $Element->GetProperties()){
                    $codeKtu[] = $Props['CODE']['VALUE'];
                }
            }
        }
        return $codeKtu;
    }
    // PUT /creatives/{id} - Обновление информации об Организации в ОРД
    //В теле запроса передается объект Creative
    public static function Update($elementId,$IBLOCK_ID = 174,$entityTypeId = 148) { //Смарт ОРД-Маркетинг
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');

        $dataCre = [];
        $jsonData = "";
        $url = "";
        $filter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_999" => (string)$elementId,
        ];
        $itemsCre = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            [],
            ['*','PROPERTY_*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
        );
        $itemCre_id = null;
        $itemCreProp_TITLE = null;
        $ordElement = null;
        $itemCre_api_id=null;
        if ($arElement = $itemsCre->GetNextElement()) {

            $itemCreProp_TITLE = $arElement['FIELDS']['TITLE'];
            if ($arProps = $arElement->GetProperties()) {

                if($arProps['isSocial']['VALUE_XML_ID'] == "true") $isSocial = true;
                if($arProps['isSocial']['VALUE_XML_ID'] == "false") $isSocial = false;

                $itemCre_api_id = $arElement['id']['VALUE'];
                $ordElement = $arProps['SMART_ORD']['VALUE'];

//                $dataCre['contractId'][] = $contractProp_id;
                $dataCre['description'] = self::remove_emoji($arProps['description']['VALUE']);
                $dataCre['type'] = $arProps['type']['VALUE_XML_ID']; //Убрать v4
                $dataCre['form'] = $arProps['form']['VALUE_XML_ID'];
                $dataCre['url'] = $arProps['url']['VALUE'];
                $dataCre['isSocial'] = $isSocial;
                $dataCre['erirIdType'] = $arProps['erirIdType']['VALUE_XML_ID'];
                $dataCre['creativeOkveds'] = $arProps['creativeOkveds']['VALUE'];
                if($arProps['creativeData_mediaUrl']['VALUE']!=null)$dataCre['creativeData'][] = [
                    'mediaUrl' => $arProps['creativeData_mediaUrl']['VALUE'],
                    'description' => self::remove_emoji($arProps['creativeData_description']['VALUE']), //Убрать v4
                ];
                if($arProps['creativeData_textData']['VALUE']!=null)$dataCre['creativeData'][] = [
                    'textData' => self::remove_emoji($arProps['creativeData_textData']['VALUE']['TEXT']),
                    'description' => self::remove_emoji($arProps['creativeData_description']['VALUE']), //Убрать v4
                ];
                // $result['ktu'][] = '29.2.3';
                $dataCre['ktu'] = self::getKtuFromId($arProps['ktu']['VALUE'], $arProps['ktu']['LINK_IBLOCK_ID']);
            }
        }


        if (!is_null($dataCre))
        {
            $title = $itemCreProp_TITLE;
            $objectData['ITEM_ID'] = $elementId;
            $objectData['ITEM_TYPE_ID'] = $entityTypeId;
            $objectData['ITEM_TITLE'] = "Обновление креатива: ".$title;
            $objectData['METHOD'] = "POST";
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

            if($itemCre_id !== "")
            {
                $jsonData = json_encode($dataCre, JSON_UNESCAPED_UNICODE);
                if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
                {
                    $url = self::URL."/".$itemCre_api_id."/";
                    $jsonResponse = \KPLab\Curl::put_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

                    // Logs\File::AddMessage($jsonResponse,"Ответ (МАССИВ)",LOG_ORDLAB_ORG);

                    if (!empty($jsonResponse['success']))
                    {
                        $responseStr = "{" . $jsonResponse['success'] . "}";

                        $responseArray = json_decode($responseStr, true);



                        $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                        if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
                        {

                            \CRest ::call('crm.timeline.comment.add', [
                                'fields' => [
                                    "ENTITY_ID" => $ordElement,
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Обновление данных креатива [b]{$title}[/b] успешно прошло!"
                                ]
                            ]);
                            return null;
                        }
                    } else {
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $ordElement,
                                "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                "COMMENT" => "Обновление данных креатива [b]{$title}[/b] не прошло! Ошибка!"
                            ]
                        ]);
                        $responseArray = json_decode($jsonResponse['error'], true);
                        return $responseArray;
                    }
                }
            } else {
                \CRest ::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $ordElement,
                        "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                        "COMMENT" => "ID креатива [b]{$title}[/b] не существует! Сначало зарегистрируйте креатив!"
                    ]
                ]);
            }
        }
    }
}