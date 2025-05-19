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
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");

class Invoices
{
    const URL = "https://api.ord-lab.ru/api/v4/invoices";
    const point = "BX_ORDLAB";

    /*
    POST /invoices/with-statistic
    {
      "amount": {
        "commission": {
          "excludingVat": "<number>",
          "includingVat": "<number>",
          "vat": "<number>",
          "vatRate": "<number>"
        },
        "services": {
          "excludingVat": "<number>",
          "includingVat": "<number>",
          "vat": "<number>",
          "vatRate": "<number>"
        }
      },
      "clientRole": "rd",
      "contractId": "<string>",
      "contractorRole": "rd",
      "date": "<string>",
      "endDate": "<string>",
      "id": "<string>",
      "irRelevant": "<boolean>",
      "items": [
        {
          "amount": {
            "excludingVat": "<number>",
            "includingVat": "<number>",
            "vat": "<number>",
            "vatRate": "<number>"
          },
          "cid": "<string>",
          "contractId": "<string>",
          "creatives": [
            {
              "creativeId": "<string>",
              "marker": "<string>",
              "platforms": [
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                },
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                }
              ]
            },
            {
              "creativeId": "<string>",
              "marker": "<string>",
              "platforms": [
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                },
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                }
              ]
            }
          ]
        },
        {
          "amount": {
            "excludingVat": "<number>",
            "includingVat": "<number>",
            "vat": "<number>",
            "vatRate": "<number>"
          },
          "cid": "<string>",
          "contractId": "<string>",
          "creatives": [
            {
              "creativeId": "<string>",
              "marker": "<string>",
              "platforms": [
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                },
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                }
              ]
            },
            {
              "creativeId": "<string>",
              "marker": "<string>",
              "platforms": [
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                },
                {
                  "amount": {
                    "excludingVat": "<number>",
                    "includingVat": "<number>",
                    "vat": "<number>",
                    "vatRate": "<number>"
                  },
                  "amountPerShow": "<number>",
                  "campaignType": "<string>",
                  "dateEndFact": "<string>",
                  "dateEndPlan": "<string>",
                  "dateStartFact": "<string>",
                  "dateStartPlan": "<string>",
                  "externalId": "<string>",
                  "impsFact": "<integer>",
                  "impsPlan": "<integer>",
                  "platformId": "<string>"
                }
              ]
            }
          ]
        }
      ],
      "number": "<string>",
      "startDate": "<string>",
      "type": "invoice"
    }
    */
    public static function Registration($elementId, $IBLOCK_ID = 234, $entityTypeId = 148)
    {
        // Logs\File::AddMessage($elementId, "Запрос на регистрацию", LOG_ORDLAB_INVOICE);
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');
        $jsonData = "";
        $url = "";
        $dataInvoices = [];

        $arOrder = ['ID' => 'ASC'];
        $arFilter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_1552" => (string)$elementId,
            "ACTIVE" => "Y"
        ];
        $arGroupBy = false;
        $arNavStartParams = [];
        $arSelect = ["*"];


        $res = \CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);

        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arProps = $ob->GetProperties();

			if ($arProps['irRelevant']['VALUE_XML_ID'] == "true") $irRelevant = true;
			if ($arProps['irRelevant']['VALUE_XML_ID'] == "false") $irRelevant = false;

            if (!empty($arFields) && $arProps['id']['VALUE'] == "") {
                // 	print_r("\n");

                // var_dump($arProps['clientRole']['VALUE_XML_ID']);
                // print_r("\n");
				$dataInvoices["irRelevant"] = $irRelevant;
                $dataInvoices["contractId"] = $arProps['contractId']['VALUE'];
                $dataInvoices["number"] = $arProps['number']['VALUE'];
                $dataInvoices["clientRole"] = $arProps['clientRole']['VALUE_XML_ID'];
                $dataInvoices["contractorRole"] = $arProps['contractorRole']['VALUE_XML_ID'];
                $dataInvoices["date"] = date("Y-m-d", strtotime($arProps['date']['VALUE']));
                $dataInvoices["startDate"] = date("Y-m-d", strtotime($arProps['startDate']['VALUE']));
                $dataInvoices["endDate"] = date("Y-m-d", strtotime($arProps['endDate']['VALUE']));
				var_dump($arProps['vat']['VALUE']);
                    print_r("\n");
//                $dataInvoices["amount"] = (float)$arProps['amount']['VALUE'];
                $dataInvoices["type"] = $arProps['type']['VALUE_XML_ID'];//v4
                if($arProps['type']['VALUE_XML_ID']=='invoice'){
                    $dataInvoices["amount"]["services"]["vat"] = (float)$arProps['vat']['VALUE'];
                    $dataInvoices["amount"]["services"]["excludingVat"] = (float)$arProps['excludingVat']['VALUE'];
                    $dataInvoices["amount"]["services"]["vatRate"] = (float)$arProps['vatRate']['VALUE'];
                    $dataInvoices["amount"]["services"]["includingVat"] = (float)$arProps['includingVat']['VALUE'];

					// $dataInvoices["amount"]["commission"]["vat"] = (float)0;
                    // $dataInvoices["amount"]["commission"]["excludingVat"] = (float)0;
                    // $dataInvoices["amount"]["commission"]["vatRate"] = (float)0;
                    // $dataInvoices["amount"]["commission"]["includingVat"] = (float)0;
                }else{
                    $dataInvoices["amount"]["commission"]["date"] = date("Y-m-d", strtotime($arProps['amount_commission_date']['VALUE']));
                    $dataInvoices["amount"]["commission"]["number"] = $arProps['amount_commission_number']['VALUE'];

                    $dataInvoices["amount"]["commission"]["vat"] = (float)$arProps['vat']['VALUE'];
                    $dataInvoices["amount"]["commission"]["excludingVat"] = (float)$arProps['excludingVat']['VALUE'];
                    $dataInvoices["amount"]["commission"]["vatRate"] = (float)$arProps['vatRate']['VALUE'];
                    $dataInvoices["amount"]["commission"]["includingVat"] = (float)$arProps['includingVat']['VALUE'];

					// $dataInvoices["amount"]["services"]["vat"] = 0;
                    // $dataInvoices["amount"]["services"]["excludingVat"] = 0;
                    // $dataInvoices["amount"]["services"]["vatRate"] = 0;
                    // $dataInvoices["amount"]["services"]["includingVat"] = 0;
                }

//                "amount": {
                //    "commission": {
                //      "date": "string",
                //      "excludingVat": 0,
                //      "includingVat": 0,
                //      "number": "string",
                //      "vat": 0,
                //      "vatRate": 0
                //    },
                //    "services": {
                //      "excludingVat": 0,
                //      "includingVat": 0,
                //      "vat": 0,
                //      "vatRate": 0
                //    }
                //  },

                //$dataInvoices["isVat"] = $isVat; //Убрать v4


                // $lastElement = $statics[array_key_last($statics)];
                // if($oldId != $lastElement['creativeId']){
                // 	var_dump($oldId);
                // 	$dataInvoices["items"][] = [
                // 		'contractId' => $arProps['contractId']['VALUE'],
                // 		'isVat' => $isVat,
                // 		'amount' =>  (float)$arProps['amount']['VALUE'],
                // 		'creatives' => $statics
                // 	];
                // }
                // $oldId = $lastElement['creativeId'];
            }
        }
        $statics = self::GetCreatives($elementId, $arFields['ID']);

        if (is_null($statics))
            Logs\File::AddMessage($statics, "Пришли пустые ответы по статистикам", LOG_ORDLAB_INVOICE);

        $dataInvoices["items"][] = [
            'contractId' => $arProps['contractId']['VALUE'],

            // 'amount' => (float)$arProps['amount']['VALUE'],
			"amount" => [
						"excludingVat" => (float)$arProps['excludingVat']['VALUE'],
						"includingVat" => (float)$arProps['includingVat']['VALUE'],
						"vat" => (float)$arProps['vat']['VALUE'],
						"vatRate" => (float)$arProps['vatRate']['VALUE'] 
					],
            'creatives' => $statics
        ];
        $title = $arFields['NAME'];
        $objectData['ITEM_ID'] = $elementId;
        $objectData['ITEM_TYPE_ID'] = $entityTypeId;
        $objectData['ITEM_TITLE'] = "Регистрация акта: " . $title;
        $objectData['METHOD'] = "POST";
        $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

        $jsonData = json_encode($dataInvoices, JSON_UNESCAPED_UNICODE);
        if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
            $url = self::URL . "/with-statistic/";
            $jsonResponse = \KPLab\Curl::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
            // print_r($statics);
            // print_r($dataInvoices);
            // print_r($jsonResponse);


            if (!empty($jsonResponse['success'])) {
                $responseArray = json_decode($jsonResponse['success'], true);
                if ($responseArray['id'] !== "") {
                    $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                    $PROPERTY_VALUES = [
                        "id" => $responseArray['id'],
                        "state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID),
                    ];
                    \CIBlockElement::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID, $PROPERTY_VALUES);

                    self::GetIdCreatives($elementId, $arFields['ID'], $timeData);

                    \CRest::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $elementId,
                            "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                            "COMMENT" => "Регистрация акта [b]{$title}[/b] успешно прошла!"
                        ]
                    ]);
                    return $responseArray;
                } else {

                    CRest::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $elementId,
                            "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                            "COMMENT" => "Регистрация акта [b]{$title}[/b] не прошла, уже зарегистрирован!"
                        ]
                    ]);
                    return ['state' => $arFields['ID']];
                }
            } else {
                $arErrors = $jsonResponse['errorArray'];
                if ($arErrors['code'] == 'invalid-json') {
                    $statusCode = "Ошибка в запросе";
                    $detailError = $arErrors['detail'];
                }

                // Logs\File::AddMessage($dataInvoices, "Отправлен был массив для регистрации ", LOG_ORDLAB_INVOICE);

                Logs\File::AddMessage($arErrors, "Ответ с ошибками (МАССИВ)", LOG_ORDLAB_INVOICE);

                \CRest::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $elementId,
                        "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                        "COMMENT" => "Регистрация акта [b]{$title}[/b] не прошла! {$statusCode}! {$detailError}"
                    ]
                ]);
                return $jsonResponse;
            }

        }


        return false;
    }

    // GET /invoices/{id}/status - Получение информации о статусе зарегистрированной в ОРД Организации
    public static function GetStatus($elementId, $id, $IBLOCK_ID = 234, $entityTypeId = 148)
    {
        // Logs\File::AddMessage($elementId, "Запрос на получение статуса", LOG_ORDLAB_INVOICE);
        Loader::includeModule('iblock');

        $timeData = Logs\TimeData::start();
        $jsonData = "";

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $item = $factory->getItem($elementId);

        if ($item) {
            $objectData['ITEM_ID'] = $elementId;
            $objectData['ITEM_TYPE_ID'] = $entityTypeId;
            $objectData['ITEM_TITLE'] = "Получение статуса акта: " . $item->getData()['TITLE'];
            $objectData['METHOD'] = "GET";
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";
            $arOrder = ['ID' => 'DESC'];
            $arFilter = [
                "IBLOCK_ID" => $IBLOCK_ID,
                "PROPERTY_1425" => (string)$elementId,
                "ACTIVE" => "Y",
                "PROPERTY_1413" => (string)$id
            ];
            $arGroupBy = false;
            $arNavStartParams = [];
            $arSelect = ["*", "PROPERTY_*"];

            $res = \CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);

            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $arProps = $ob->GetProperties();

                if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
                    $url = self::URL . "/" . $arProps["id"]['VALUE'] . "/status/";

                    $jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

                    if (!empty($jsonResponse['success'])) {
                        $responseStr = "{" . $jsonResponse['success'] . "}";
                        $responseArray = json_decode($responseStr, true);

                        $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                        if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "") {
                            $PROPERTY_VALUES = [
                                "state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID)
                            ];
                            \CIBlockElement::SetPropertyValuesEx($arFields["ID"], $IBLOCK_ID, $PROPERTY_VALUES);
                            $currentState = Entity::getPropertyEnumValueByArray($responseArray['state'], $IBLOCK_ID);

                            \CRest::call('crm.timeline.comment.add', [
                                'fields' => [
                                    "ENTITY_ID" => $item->getId(),
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Текущий статус Акта: [b]{$currentState}[/b]!"
                                ]
                            ]);

                        }
                        return $responseArray['state'];
                    } else {
                        Logs\File::AddMessage($jsonResponse, "Ответ от ОРД на запрос " . $url . " (Не success)", LOG_ORDLAB_INVOICE);
                    }

                }

            }

        } else {
            return null;
        }
    }

    public static function GetItems($elementId, $contractId, $amount, $isVat)
    {
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
        // $result['isVat'] = $isVat;
        $result['amount'] = $amount;
        $result['creatives'] = self::GetCreatives($elementId, null);

        return $result;
    }

    public static function GetCreatives($elementId, $ID_SPISKA_AKTA, $IBLOCK_ID = 181)
    {
        // Logs\File::AddMessage($elementId, "Запрос на получение статистик по креативам ", LOG_ORDLAB_INVOICE);
        $resultCreative = [];
        Loader::includeModule('iblock');

        $arOrder = ['ID' => 'ASC'];
        $arFilter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "=PROPERTY_1087_VALUE" => $elementId,
            "ACTIVE_DATE" => "Y",
            "ACTIVE" => "Y"
        ];
        $arGroupBy = false;
        $arNavStartParams = [];
        $arSelect = ["*", "PROPERTY_*"];
        $res = \CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);

        $currentCreativeId = null; // Текущий creativeId
        $creative = null; // Текущий креатив

        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arProps = $ob->GetProperties();

            // if ($arProps['isVat']['VALUE'] == "Да") {
            //     $isVat = true;
            // } elseif ($arProps['isVat']['VALUE'] == "Нет") {
            //     $isVat = false;
            // } else {
            //     $isVat = null; // Если значение не определено
            // }

            if (!empty($arFields) && empty($arProps['id']['VALUE']) && $arProps['ID_SPISKA_AKTA']['VALUE'] == $ID_SPISKA_AKTA) {
                $creativeId = $arProps['creativeId']['VALUE'];

                // Если новый creativeId, создаем новый элемент
                if ($currentCreativeId !== $creativeId) {
                    // Сохраняем предыдущий креатив (если есть)
                    if ($creative !== null) {
                        $resultCreative[] = $creative;
                    }

                    // Создаем новый креатив
                    $creative = [
                        'creativeId' => $creativeId,
                        'platforms' => []
                    ];

                    $currentCreativeId = $creativeId;
                }

                // Собираем данные платформы
                $platforms = [
                    "platformId" => $arProps['platformId']['VALUE'],
                    "impsPlan" => (int)$arProps['impsPlan']['VALUE'],
                    "dateStartPlan" => date("Y-m-d", strtotime($arProps['dateStartPlan']['VALUE'])),
                    "dateEndPlan" => date("Y-m-d", strtotime($arProps['dateEndPlan']['VALUE'])),
                    "impsFact" => (int)$arProps['impsFact']['VALUE'],
                    "dateStartFact" => date("Y-m-d", strtotime($arProps['dateStartFact']['VALUE'])),
                    "dateEndFact" => date("Y-m-d", strtotime($arProps['dateEndFact']['VALUE'])),
                    "amount" => [
						"excludingVat" => (float)$arProps['excludingVat']['VALUE'],
						"includingVat" => (float)$arProps['includingVat']['VALUE'],
						"vat" => (float)$arProps['vat']['VALUE'],
						"vatRate" => (float)$arProps['vatRate']['VALUE'] 
					],
                    "amountPerShow" => (float)str_replace("|RUB", "", $arProps['amountPerShow']['VALUE']),
					"campaignType" => "other"
                    // "isVat" => $isVat,
                ];

                // Добавляем платформу к текущему креативу
                $creative['platforms'][] = $platforms;
            }
        }

        // Добавляем последний креатив
        if ($creative !== null) {
            $resultCreative[] = $creative;
        }

        return $resultCreative;
    }

    //Функция для присваивания ID статистикам только что созданного акта
    public static function GetIdCreatives($elementId, $ID_SPISKA_AKTA, $timeData = null, $IBLOCK_ID = 181)
    {
        $timeData = Logs\TimeData::start();
        // Logs\File::AddMessage($elementId, "Запрос на присваивания ID статистикам", LOG_ORDLAB_INVOICE);
        $loader = new LoadFromORD();
        Loader::includeModule('iblock');
        $arOrder = ['ID' => 'ASC'];
        $arFilter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "=PROPERTY_1087_VALUE" => $elementId,
            "ACTIVE_DATE" => "Y",
            "ACTIVE" => "Y"
        ];
        $arGroupBy = false;
        $arNavStartParams = [];
        $arSelect = ["*", "PROPERTY_*"];

        $res = \CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);

        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arProps = $ob->GetProperties();

            if (!empty($arFields) && $arProps['id']['VALUE'] == "" && $arProps['ID_SPISKA_AKTA']['VALUE'] == (string)$ID_SPISKA_AKTA) {
                //Получаем API ID
                $url = str_replace('invoices', 'creative-statistics/search', self::URL);
                $params = [];
                $params['creativeId'] = $arProps['creativeId']['VALUE'];
                $params['platformId'] = $arProps['platformId']['VALUE'];
                $params['limit'] = 0;
                $params['page'] = 0;
                $params['factStartDate'] = date("Y-m-d", strtotime($arProps['dateStartFact']['VALUE']));
                $params['factEndDate'] = date("Y-m-d", strtotime($arProps['dateEndFact']['VALUE']));
                // Logs\File::AddMessage($params, "Параметры для получения ID из ОРД " . $url, LOG_ORDLAB_INVOICE);
                $resultlist = $loader->GetList($timeData, $url, $IBLOCK_ID, null, $params)['data'];
                // Logs\File::AddMessage($resultlist, "Ответ из ОРД " . $url, LOG_ORDLAB_INVOICE);
                $ApiId = $resultlist[0]['id'];
                // print_r($resultlist);
                // print_r($params);
                // var_dump($ApiId);
                // print_r("\n");
                $url = str_replace('search', $ApiId . '/status', $url);
                $params = [];
                $params['id'] = $ApiId;
                $resultState = $loader->GetState($timeData, $url, $IBLOCK_ID, "", [], $params)['state'];
                // Logs\File::AddMessage($resultState, "Получен статус по Id " . $ApiId, LOG_ORDLAB_INVOICE);
                // print_r($url);
                // print_r($resultState);
                $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($resultState, $IBLOCK_ID);

                // print_r($PROPERTY_STATE_ENUM_ID);
                $PROPERTY_VALUES = [
                    "id" => $ApiId,
                    "state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID),
                ];
                \CIBlockElement::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID, $PROPERTY_VALUES);
            }


        }
    }
    // PUT /invoices/{id} - Обновление информации об Организации в ОРД
    //В теле запроса передается объект Invoice
    public static function Update($elementId, $IBLOCK_ID = 234, $entityTypeId = 148)
    { //Смарт ОРД-Маркетинг
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');

        $data = [];
        $jsonData = "";
        $url = "";
        $filter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_1428" => (string)$elementId,
        ];
        $items = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            [],
            ['*', 'PROPERTY_*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
        );
        $item_id = null;
        $itemProp_TITLE = null;
        $ordElement = null;
        $item_api_id = null;
        if ($arElement = $items->GetNextElement()) {

            $itemCreProp_TITLE = $arElement['FIELDS']['TITLE'];
            if ($arProps = $arElement->GetProperties()) {
				
                $item_api_id = $arElement['id']['VALUE'];
                $ordElement = $arProps['SMART_ORD']['VALUE'];

                if ($arProps['irRelevant']['VALUE_XML_ID'] == "true") $irRelevant = true;
                if ($arProps['irRelevant']['VALUE_XML_ID'] == "false") $irRelevant = false;

                if (!empty($arFields) && $arProps['id']['VALUE'] == "") {
                    // 	print_r("\n");

                    // var_dump($arProps);
                    // print_r("\n");

                   $dataInvoices["irRelevant"] = $irRelevant;
					$dataInvoices["contractId"] = $arProps['contractId']['VALUE'];
					$dataInvoices["number"] = $arProps['number']['VALUE'];
					$dataInvoices["clientRole"] = $arProps['clientRole']['VALUE_XML_ID'];
					$dataInvoices["contractorRole"] = $arProps['contractorRole']['VALUE_XML_ID'];
					$dataInvoices["date"] = date("Y-m-d", strtotime($arProps['date']['VALUE']));
					$dataInvoices["startDate"] = date("Y-m-d", strtotime($arProps['startDate']['VALUE']));
					$dataInvoices["endDate"] = date("Y-m-d", strtotime($arProps['endDate']['VALUE']));
						
	//                $dataInvoices["amount"] = (float)$arProps['amount']['VALUE'];
					$dataInvoices["type"] = $arProps['type']['VALUE_XML_ID'];//v4
					if($arProps['invoice']['VALUE_XML_ID']=='services'){
						$dataInvoices["amount"]["services"]["vat"] = $arProps['vat']['VALUE'];
						$dataInvoices["amount"]["services"]["excludingVat"] = $arProps['excludingVat']['VALUE'];
						$dataInvoices["amount"]["services"]["vatRate"] = $arProps['vatRate']['VALUE'];
						$dataInvoices["amount"]["services"]["includingVat"] = $arProps['includingVat']['VALUE'];
					}else{
						$dataInvoices["amount"]["commission"]["date"] = date("Y-m-d", strtotime($arProps['amount_commission_date']['VALUE']));
						$dataInvoices["amount"]["commission"]["number"] = $arProps['amount_commission_number']['VALUE'];
	
						$dataInvoices["amount"]["commission"]["vat"] = $arProps['vat']['VALUE'];
						$dataInvoices["amount"]["commission"]["excludingVat"] = $arProps['excludingVat']['VALUE'];
						$dataInvoices["amount"]["commission"]["vatRate"] = $arProps['vatRate']['VALUE'];
						$dataInvoices["amount"]["commission"]["includingVat"] = $arProps['includingVat']['VALUE'];
					}
                }
            }


            if (!is_null($data)) {
                $title = $itemCreProp_TITLE;
                $objectData['ITEM_ID'] = $elementId;
                $objectData['ITEM_TYPE_ID'] = $entityTypeId;
                $objectData['ITEM_TITLE'] = "Обновление акта: " . $title;
                $objectData['METHOD'] = "POST";
                $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

                if ($item_id !== "") {
                    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
                    if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
                        $url = self::URL . "/" . $item_api_id . "/";
                        $jsonResponse = \KPLab\Curl::put_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

                        // Logs\File::AddMessage($jsonResponse, "Ответ (МАССИВ)", LOG_ORDLAB_ORG);

                        if (!empty($jsonResponse['success'])) {
                            $responseStr = "{" . $jsonResponse['success'] . "}";

                            $responseArray = json_decode($responseStr, true);


                            $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                            if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "") {

                                \CRest::call('crm.timeline.comment.add', [
                                    'fields' => [
                                        "ENTITY_ID" => $ordElement,
                                        "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                        "COMMENT" => "Обновление данных акта [b]{$title}[/b] успешно прошло!"
                                    ]
                                ]);
                                return null;
                            }
                        } else {
                            \CRest::call('crm.timeline.comment.add', [
                                'fields' => [
                                    "ENTITY_ID" => $ordElement,
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Обновление данных акта [b]{$title}[/b] не прошло! Ошибка!"
                                ]
                            ]);
                            $responseArray = json_decode($jsonResponse['error'], true);
                            return $responseArray;
                        }
                    }
                } else {
                    \CRest::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $ordElement,
                            "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                            "COMMENT" => "ID акта [b]{$title}[/b] не существует! Сначало зарегистрируйте акт!"
                        ]
                    ]);
                }
            }
        }
    }
}