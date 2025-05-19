<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;
use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Diag\Debug;


define("LOG_ORDLAB_ORG", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/organization.log");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");

class Platforms {
    const point = "BX_ORDLAB";
    const URL = "https://api.ord-lab.ru/api/v4/platforms";
    // POST /platforms - Регистрация Площадки в ОРД
    //В теле запроса передается объект Platform без id
    /*
        {
            "externalId": "myPlatform",
            "isOwned": false,
            "name": "Моя Площадка",
            "organizationId": "000025be-a797-48cd-86d4-9da826f24522",
            "type": "site",
            "url": "http://myplatform.org"
        }
    */
    public static function Registration($elementId, $organizationId, $IBLOCK_ID = 175, $entityTypeId = 148)
    {

        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');
        $dataPlatform = [];
        $jsonData = "";
        $url = self::URL;

        $arFilter = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_1007" => (string)$elementId,
            "ACTIVE" => "Y"
        ];
        $arSelect = ["*"];

        $res = \CIBlockElement::GetList("",$arFilter,false,false,$arSelect);
        $k = 0;

        while ($element = $res->GetNextElement()) {
            $arFields = $element->GetFields();
            $arProps = $element->GetProperties();
            if($arProps['isOwned']['VALUE_XML_ID'] == "true") $isOwned = true;
            if($arProps['isOwned']['VALUE_XML_ID'] == "false") $isOwned = false;

            if(!empty($arFields)) {

                $itemPlaProp_id = $arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'];
                $title = $arFields['NAME'];
                $objectData['ITEM_ID'] = $elementId;
                $objectData['ITEM_TYPE_ID'] = $entityTypeId;
                $objectData['ITEM_TITLE'] = "Регистрация платформы: ".$title;
                $objectData['METHOD'] = "POST";
                $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";print_r($itemPlaProp_id);
                if($itemPlaProp_id == "")
                {
                    $url = $arProps['url']['VALUE'][0];
                    if($arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'] !== "") $dataPlatform['platformId'] = $arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'];
                    $dataPlatform['externalId'] = "platform".$arFields['ID'];
                    $dataPlatform['isOwned'] = $isOwned;
                    $dataPlatform['type'] = $arProps['type']['VALUE_XML_ID'];
                    $dataPlatform['name'] = (string)$arFields['NAME'];
                    $dataPlatform['organizationId'] = $organizationId;
                    if($url !== '')	$dataPlatform['url'] = $url;

                    $jsonData = json_encode($dataPlatform, JSON_UNESCAPED_UNICODE);

                    if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
                    {
                        $url = self::URL . "/";
                        $jsonResponse = \KPLab\Curl ::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
                        if (!empty($jsonResponse['success']))
                        {
                            $responseArray = json_decode($jsonResponse['success'], true);

                            $itemPlaId = $arFields['ID'];

                            $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                            if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
                            {
                                $PROPERTY_VALUES = [
                                    "IDENTIFIKATOR_PLOSHCHADKI_API" => $responseArray['data']['entity']['id'],
                                    // "state" => array("VALUE" => $PROPERTY_STATE_ENUM_ID),
                                ];
                                \CIBlockElement ::SetPropertyValuesEx($itemPlaId, $IBLOCK_ID, $PROPERTY_VALUES);
                                \CRest ::call('crm.timeline.comment.add', [
                                    'fields' => [
                                        "ENTITY_ID" => $elementId,
                                        "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                        "COMMENT" => "Регистрация площадки [b]{$title}[/b] успешно прошла!"
                                    ]
                                ]);
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
                                    "ENTITY_ID" => $elementId,
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Регистрация площадки [b]{$title}[/b] не прошла! {$statusCode}! {$detailError}"
                                ]
                            ]);
                        }

                    }
                } else {

                    \CRest ::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $elementId,
                            "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                            "COMMENT" => "Регистрация площадки [b]{$title}[/b] не прошла, уже зарегистрирована!"
                        ]
                    ]);
                }
            }
            $k++;
        }



        return false;
    }

    // PUT /platforms/{id} - Обновление информации об Организации в ОРД
    //В теле запроса передается объект Platform
    public static function Update($elementId, $organizationId, $IBLOCK_ID = 175, $entityTypeId = 148) { //Смарт ОРД-Маркетинг
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');

        $dataPlatform = [];
        $jsonData = "";
        $url = "";

        $itemPla = Entity::getItem($IBLOCK_ID, $elementId);

        $itemPlaProp_id = $itemPla['PROPERTIES']['id']['VALUE'];
        $arProps = $itemPla['PROPERTIES'];
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $item = $factory->getItem($elementId);

        if ($item)
        {
            $title = $item -> getData()['TITLE'];
            $objectData['ITEM_ID'] = $elementId;
            $objectData['ITEM_TYPE_ID'] = $entityTypeId;
            $objectData['ITEM_TITLE'] = "Обновление площадки: ".$title;
            $objectData['METHOD'] = "POST";
            $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$entityTypeId}/details/{$elementId}/";

            if($itemPlaProp_id !== "")
            {

                if($arProps['isOwned']['VALUE_XML_ID'] == "true") $isOwned = true;
                if($arProps['isOwned']['VALUE_XML_ID'] == "false") $isOwned = false;

                $url = $arProps['url']['VALUE'][0];
                if($arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'] !== "") $dataPlatform['platformId'] = $arProps['IDENTIFIKATOR_PLOSHCHADKI_API']['VALUE'];
                $dataPlatform['externalId'] = "platform".$itemPla['FIELDS']['ID'];
                $dataPlatform['isOwned'] = $isOwned;
                $dataPlatform['type'] = $arProps['type']['VALUE_XML_ID'];
                $dataPlatform['name'] = (string)$itemPla['FIELDS']['NAME'];
                $dataPlatform['organizationId'] = $organizationId;
                if($url !== '')	$dataPlatform['url'] = $url;

                $jsonData = json_encode($dataPlatform, JSON_UNESCAPED_UNICODE);
                if (defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY"))
                {
                    $url = self::URL."/".$itemPlaProp_id."/";
                    $jsonResponse = \KPLab\Curl::put_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

                    // Logs\File::AddMessage($jsonResponse,"Ответ (МАССИВ)",LOG_ORDLAB_ORG);

                    if (!empty($jsonResponse['success']))
                    {
                        $responseStr = "{" . $jsonResponse['success'] . "}";

                        $responseArray = json_decode($responseStr, true);

                        $itemPlaId = $itemPla['FIELDS']['ID'];

                        $PROPERTY_STATE_ENUM_ID = Entity::getPropertyEnumIdByArray($responseArray['state'], $IBLOCK_ID);

                        if ($responseArray['id'] !== "" && $PROPERTY_STATE_ENUM_ID !== "")
                        {

                            \CRest ::call('crm.timeline.comment.add', [
                                'fields' => [
                                    "ENTITY_ID" => $item -> getId(),
                                    "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                    "COMMENT" => "Обновление данных площадки [b]{$title}[/b] успешно прошло!"
                                ]
                            ]);
                            return null;
                        }
                    } else {
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $item -> getId(),
                                "ENTITY_TYPE" => "DYNAMIC_{$entityTypeId}",
                                "COMMENT" => "Обновление данных площадки [b]{$title}[/b] не прошло! Ошибка!"
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
                        "COMMENT" => "ID площадки [b]{$title}[/b] не существует! Сначало зарегистрируйте площадку!"
                    ]
                ]);
            }
        }
    }


}
