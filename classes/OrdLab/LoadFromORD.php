<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;
use mysql_xdevapi\BaseResult;

define("LOG_ORDLAB_LOADFROMORD", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/loadFromORD.log");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");


class LoadFromORD {
    const point = "BX_ORDLAB";
    public $IBLOCK_ID_ORG;
    public $IBLOCK_ID_PLA;
    public $IBLOCK_ID_CON;
    public $IBLOCK_ID_CRE;
    public $IBLOCK_ID_STA;
    public $IBLOCK_ID_INV;

    public $CHECK_UPDATE_BP_NAME;
    public $mode;

    public function __construct($mode = null){
        $this -> IBLOCK_ID_ORG = 176;
        $this -> IBLOCK_ID_PLA = 175;
        $this -> IBLOCK_ID_CON = 177;
        $this -> IBLOCK_ID_CRE = 174;
        $this -> IBLOCK_ID_STA = 181;   //TODO: на тестовой IBLOCK = 181,на боевой IBLOCK = 181
        $this -> IBLOCK_ID_INV = 234;   //TODO: на тестовой IBLOCK = 233,на боевой IBLOCK = 234
        $this -> CHECK_UPDATE_BP_NAME = 'checkUpdate';
        if(!is_null($mode)){
            $this -> mode = 'checkUpdate';
        }else $this -> mode = 'update';
    }
    //Функция для запуска процесса в Б24
    public function LoadFromOrdFULL($actualMethod = null, $IBLOCK_ID = null, $case = null, $filterId = null): void
    {

        // Logs\File::AddMessage($actualMethod, "actualMethod", LOG_ORDLAB_LOADFROMORD);
        // Logs\File::AddMessage($IBLOCK_ID, "IBLOCK_ID", LOG_ORDLAB_LOADFROMORD);
        // Logs\File::AddMessage($case, "case", LOG_ORDLAB_LOADFROMORD);
        // Logs\File::AddMessage($filterId, "filterId", LOG_ORDLAB_LOADFROMORD);

        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');

        $page = 1;
        $limit = 0;
        $total = 0;

        // print_r("Метод $actualMethod, IBLOCK $IBLOCK_ID, case $case \n");
        if(!is_null($actualMethod))
            $result = $this->GetList($timeData, $actualMethod, $IBLOCK_ID, $filterId, null, $page);
        $total = $result['total']- ($result['limit']*$page);
        // print_r($result['total']);
        // print_r($result['limit']);
        if(!is_null($result) && !is_null($case))
            $this->ProcessList($result['res'], $IBLOCK_ID, $case, $timeData, $actualMethod);
        //var_dump($total);
        while($total>0){
            $page++;
            if(!is_null($actualMethod))
                $result = $this->GetList($timeData, $actualMethod, $IBLOCK_ID, $filterId, null, $page);
            $total = $result['total']- ($result['limit']*$page);
            if(!is_null($result) && !is_null($case))
                $this->ProcessList($result['res'], $IBLOCK_ID, $case, $timeData, $actualMethod);
        }
    }
    //Получение списка из ОРД
    public function GetList($timeData, $url, $IBLOCK_ID, $filterId = null, $params = null, $page = null){
        $params['limit'] = 0;
        if(is_null($page)){
            $params['page'] = 0;
        }else $params['page'] = $page;


        if(!is_null($filterId)){
            str_contains($url, 'platforms') ? $params['platformId'] = $filterId : $params['id'][] = $filterId;
        }

        $jsonData = json_encode($params, JSON_UNESCAPED_UNICODE);
        $objectData['ITEM_ID'] = $IBLOCK_ID;
        $objectData['ITEM_TYPE_ID'] = $IBLOCK_ID;
        $objectData['ITEM_TITLE'] = "Получение списка из ОРД, метод $url";
        $objectData['METHOD'] = "POST";
        $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/services/lists/$IBLOCK_ID/view/0/";
        $result = [];

        if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
            $jsonResponse = \KPLab\Curl::post_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);
            // var_dump($url);
            // print_r($jsonResponse);
            // Logs\File::AddMessage($jsonResponse, "Ответ списка по получению списка", LOG_ORDLAB_LOADFROMORD);
            if(!empty($jsonResponse['success'])) {
                $res = json_decode($jsonResponse['success'], true);
                $result['res'] = $res;
                // print_r($res['meta']);
                $result['total'] = $res['meta']['total'];
                $result['limit'] = $res['meta']['limit'];
                return $result;
            }
        }
        return null;
    }

    //Получение статусов из ОРД
    public static function GetState($timeData, $url, $IBLOCK_ID){
        $params['limit'] = 0;
        $params['page'] = 0;

        $jsonData = json_encode($params, JSON_UNESCAPED_UNICODE);

        $objectData['ITEM_ID'] = $IBLOCK_ID;
        $objectData['ITEM_TYPE_ID'] = $IBLOCK_ID;
        $objectData['ITEM_TITLE'] = "Получение статуса из ОРД, метод $url";
        $objectData['METHOD'] = "POST";
        $objectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/services/lists/$IBLOCK_ID/view/0/";

        if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {

            $jsonResponse = \KPLab\Curl::get_ord(ORD_TOKEN_KEY, $url, $jsonData, $objectData, $timeData, self::point);

            if(!empty($jsonResponse['success'])) {
                $responseStr = "{".$jsonResponse['success']."}";
                return json_decode($responseStr, true);

            }else{
                Logs\File::AddMessage($jsonResponse,"Пришел некорректный ответ от ОРД по статусам",LOG_ORDLAB_LOADFROMORD);
            }
        }
        return null;
    }

    //Обработка полученного результата, в зависимости кода из $IBLOCK_ID, Возможные значения: ORG, PLA, CON, CRE, STA, INV, DEL
    public function ProcessList($result, $IBLOCK_ID, $case, $timeData, $actualMethod): void
    {
        if (isset($result['data']) && is_array($result['data'])) {
            $data = $result['data'];
        } else {
            $data = $result;
        }

        switch($case){
            case "ORG":
                foreach ($data as $item) {
                    $entity = $item['entity'];
                    $organisation = $item['organisation'];

                    $isRrString = ($organisation['isRr'] === true) ? 'true' : 'false';

                    $STATE_ENUM_ID_TYPE = self::getPropertyEnumId('type', $IBLOCK_ID);
                    $STATE_ENUM_ID_ISRR = self::getPropertyEnumId('isRr', $IBLOCK_ID);
                    $STATE_ENUM_ID_STATE = self::getPropertyEnumId('state', $IBLOCK_ID);;
 					// $STATE_ENUM_ID_ISORS = self::getPropertyEnumId('isOrs', $IBLOCK_ID);;
					// print_r($entity);
                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => $organisation['name'],
                        "PROPERTY_VALUES" => [
                            'type' => $STATE_ENUM_ID_TYPE[$organisation['type']],
                            'name' => $organisation['name'],
                            'inn' => $organisation['inn'],
                            'isRr' => isset($organisation['isRr'])?$STATE_ENUM_ID_ISRR[$isRrString]:$STATE_ENUM_ID_ISRR['false'],
                            'state' => $STATE_ENUM_ID_STATE[$item['entity']['status']],
                            'id' => $organisation['id'],
                            'date' => self::formatDate($entity['created']),
                            'kpp' => $organisation['kpp'],
							//'isOrs' = $STATE_ENUM_ID_ISORS[$entity['isOrs']]
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];

                    $filter = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "PROPERTY_1013" => $organisation['id'],
                    ];

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "PLA":
                foreach ($data as $item) {
                    $entity = $item['entity'];
                    $platform = $item['platform'];
                    $isOwnedString = ($platform['isOwned'] === true) ? 'true' : 'false';

                    $STATE_ENUM_ID_TYPE = self::getPropertyEnumId('type', $IBLOCK_ID);
                    $STATE_ENUM_ID_ISOWNED = self::getPropertyEnumId('isOwned', $IBLOCK_ID);

                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => $platform['name'],
                        "PROPERTY_VALUES" => [
                            'url' => $platform['url'],
                            'isOwned' => $STATE_ENUM_ID_ISOWNED[$isOwnedString],
                            'type' => $STATE_ENUM_ID_TYPE[$platform['type']],
                            'IDENTIFIKATOR_PLOSHCHADKI_API' => $platform['platformId'],
                            'IDENTIFIKATOR_ORGANIZATSII_API' => $platform['organizationId'],
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];
                    $filter = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "PROPERTY_1518" => (string)$platform['platformId'],
                    ];

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "CON":
                foreach ($data as $item) {
                    $stateString = (string) self::GetState($timeData, str_replace("search", "", $actualMethod).$item['id']."/status", $IBLOCK_ID)['state'];
                    $isRegReportString = ($item['isRegReport'] === true) ? 'true' : 'false' ;
                    $isVatString = ($item['isVat'] === true) ? 'true' : 'false';

                    $STATE_ENUM_ID_TYPE = self::getPropertyEnumId('type', $IBLOCK_ID);
                    $STATE_ENUM_ID_SUBJECTYPE = self::getPropertyEnumId('subjectType', $IBLOCK_ID);
                    $STATE_ENUM_ID_STATE = self::getPropertyEnumId('state', $IBLOCK_ID);
                    $STATE_ENUM_ID_ISREGREPORT = self::getPropertyEnumId('isRegReport', $IBLOCK_ID);
                    $STATE_ENUM_ID_ISVAT = self::getPropertyEnumId('isVat', $IBLOCK_ID);

                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => "Договор ".$item['number'],
                        "PROPERTY_VALUES" => [
                            'number' => $item['number'],
                            'type' => $STATE_ENUM_ID_TYPE[$item['type']],
                            'subjectType' => $STATE_ENUM_ID_SUBJECTYPE[$item['subjectType']],
                            'isRegReport' => $STATE_ENUM_ID_ISREGREPORT[$isRegReportString],
                            'id' => $item['id'],
                            'date' => self::formatDate($item['date']),
                            'amount' => $item['amount'] != null ? $item['amount'] : "0",
                            'isVat' => $STATE_ENUM_ID_ISVAT[$isVatString],
                            'contractorId' => $item['contractorId'],
                            'clientId' => $item['clientId'],
                            'state' => $STATE_ENUM_ID_STATE[$stateString],
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];
                    $filter = array(
                        "IBLOCK_ID" => 177,
                        "PROPERTY_1000" => (string)$item['id'],
                    );

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "CRE":
                foreach ($data as $item) {
                    $textData = array_filter(array_column($item['creativeData'], 'textData'));
                    $mediaUrls = array_filter(array_column($item['creativeData'], 'mediaUrl'));
                    $description = array_filter(array_column($item['creativeData'], 'description'));
                    $stateString = (string)self::GetState($timeData,str_replace("search", "", $actualMethod).$item['id']."/status",$IBLOCK_ID)['state'];

                    $STATE_ENUM_ID_TYPE = self::getPropertyEnumId('type', $IBLOCK_ID);
                    $STATE_ENUM_ID_FORM = self::getPropertyEnumId('form', $IBLOCK_ID);
                    $STATE_ENUM_ID_ERIDIDTYPE = self::getPropertyEnumId('erirIdType', $IBLOCK_ID);
                    $STATE_ENUM_ID_STATE = self::getPropertyEnumId('state', $IBLOCK_ID);
                    $isSocialString = ($item['isSocial'] === true) ? 'true' : 'false';
					$isSocialQuotaString = ($item['isSocialQuota'] === true) ? 'true' : 'false';
					$STATE_ENUM_ID_ISSOCIALQUOTA = self::getPropertyEnumId('isSocialQuota', $IBLOCK_ID);
                    $STATE_ENUM_ID_ISSOCIAL = self::getPropertyEnumId('isSocial', $IBLOCK_ID);
                    $STATE_ENUM_ID_SDAEM_STATISTIKU_V_ORD = self::getPropertyEnumId('SDAEM_STATISTIKU_V_ORD', $IBLOCK_ID);

                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => "Креатив ",
                        "PROPERTY_VALUES" => [
                            'type' => $STATE_ENUM_ID_TYPE[$item['type']],
                            'form' => $STATE_ENUM_ID_FORM[$item['form']],
                            'creativeOkveds' => $item['creativeOkveds'],
                            'description' => $item['description'],
                            'erirIdType' => $STATE_ENUM_ID_ERIDIDTYPE[$item['erirIdType']],
                            'url' => $item['url'],
                            'creativeData_mediaUrl' => $mediaUrls,
                            'creativeData_textData' => reset($textData),
                            'creativeData_description' => $description,
                            'marker' => $item['marker'],
                            'selfPromotionOrganizationId' => $item['selfPromotionOrganizationId'],
                            'isSocial' => $STATE_ENUM_ID_ISSOCIAL[$isSocialString],
							'isSocialQuota' => $STATE_ENUM_ID_ISSOCIALQUOTA[$isSocialQuotaString],
                            'id' => $item['id'],
                            'erirRegisteredAt' => self::formatDate($item['erirRegisteredAt']),
                            'contractId' => $item['contractId'][0],
                            'state' => $STATE_ENUM_ID_STATE[$stateString],
                            'ktu' => $item['ktu'],
                            'SDAEM_STATISTIKU_V_ORD' => $STATE_ENUM_ID_SDAEM_STATISTIKU_V_ORD['true'],
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];
                    $filter = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "PROPERTY_999" => (string)$item['id'],
                    ];

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "STA":
                foreach ($data as $item) {
                    $arStatePl = self::GetState($timeData,str_replace("creative-statistics/search", "", $actualMethod)."platforms/".$item['platformId'],$IBLOCK_ID);
                    $stateString = (string)self::GetState($timeData,str_replace("search", "", $actualMethod).$item['id']."/status",$IBLOCK_ID)['state'];
                    $platformName = $arStatePl['platform']['name'];
                    $platformUrl = $arStatePl['platform']['url'];
                    $creative = self::GetState($timeData,str_replace("creative-statistics/search", "", $actualMethod)."creatives/".$item['creativeId'],$IBLOCK_ID);

                    $isVatString = ($item['isVat'] === true) ? 'true' : 'false';
                    $STATE_ENUM_ID_ISVAT = self::getPropertyEnumId('isVat', $IBLOCK_ID);
                    $STATE_ENUM_ID_STATE = self::getPropertyEnumId('state', $IBLOCK_ID);

                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => "Статистика по площадке $platformUrl Площадка $platformName Креатив ".$creative['creativeData'][0]['description']." ".$creative['marker'],
                        "PROPERTY_VALUES" => [
                            'creativeId' => $item['creativeId'],
                            'platformId' => $item['platformId'],
                            'impsPlan' => $item['impsPlan'],
                            'dateStartPlan' => self::formatDate($item['dateStartPlan']),
                            'dateEndPlan' => self::formatDate($item['dateEndPlan']),
                            'impsFact' => $item['impsFact'],
                            'dateStartFact' => self::formatDate($item['dateStartFact']),
                            'dateEndFact' => self::formatDate($item['dateEndFact']),
                            'amount' => $item['amount'],
                            'amountPerShow' => $item['amountPerShow'],
                            'isVat' => $STATE_ENUM_ID_ISVAT[$isVatString],
                            'marker' => $creative['marker'],
                            'id' => $item['id'],
                            'state' => $STATE_ENUM_ID_STATE[$stateString],
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];
                    $filter = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "PROPERTY_1428" => (string)$item['id'],
                    ];

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "INV":
                foreach ($data as $item) {
                    $isVatString = ($item['isVat'] === true) ? 'true' : 'false';
                    $STATE_ENUM_ID_ISVAT = self::getPropertyEnumId('isVat', $IBLOCK_ID);
                    $stateString = (string)self::GetState($timeData,str_replace("search", $item['id']."/status", $actualMethod),$IBLOCK_ID)['state'];
                    $STATE_ENUM_ID_TYPE = self::getPropertyEnumId('type', $IBLOCK_ID);

                    $STATE_ENUM_ID_CLIENTROLE = self::getPropertyEnumId('clientRole', $IBLOCK_ID);
                    $STATE_ENUM_ID_CONTRACTORROLE = self::getPropertyEnumId('contractorRole', $IBLOCK_ID);
                    $STATE_ENUM_ID_STATE = self::getPropertyEnumId('state', $IBLOCK_ID);

                    $arAddElement = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "NAME" => $item['number'],
                        "PROPERTY_VALUES" => [
                            'id' => $item['id'],
                            'contractId' => $item['contractId'],
                            'clientRole' => $STATE_ENUM_ID_CLIENTROLE[$item['clientRole']],
                            'contractorRole' => $STATE_ENUM_ID_CONTRACTORROLE[$item['contractorRole']],
                            'startDate' => self::formatDate($item['startDate']),
                            'endDate' => self::formatDate($item['endDate']),
                            'amount' => $item['amount'],
                            'isVat' => $STATE_ENUM_ID_ISVAT[$isVatString],
                            "date" => self::formatDate($item['date']),
                            "number" => $item['number'],
                            'state' => $STATE_ENUM_ID_STATE[$stateString],
                            'type' => !is_null($item['type'])??$STATE_ENUM_ID_TYPE($item['type']),
							'amount_commission_number' => $item["amount"]["commission"]["number"],
							'amount_commission_date' => $item["amount"]["commission"]["date"],
							'excludingVat' => $item["amount"]["commission"]["excludingVat"] ?? $item["amount"]["services"]["excludingVat"],
							'vat' => $item["amount"]["commission"]["vat"] ?? $item["amount"]["services"]["vat"],
							'vatRate' => $item["amount"]["commission"]["vatRate"] ?? $item["amount"]["services"]["vatRate"],
							'includingVat' => $item["amount"]["commission"]["includingVat"] ?? $item["amount"]["services"]["includingVat"],
                        ],
                        "ACTIVE" => "Y",
                        "CREATED_BY" => 1
                    ];
                    $filter = [
                        "IBLOCK_ID" => $IBLOCK_ID,
                        "PROPERTY_1413" => (string)$item['id'], //TODO: на тестовой PROPERTY_1428,на боевой PROPERTY_1413
                    ];

                    if($this->mode == 'update'){
                        self::AddOrUpdateIBlockElement($arAddElement, $filter);
                    }else if($this->mode == 'checkUpdate')self::AddOrCheckIBlockElement($arAddElement, $filter);
                }
                break;
            case "DEL":
                foreach ($data as $item) {
                    $filter = [];
                    switch($item['entity_type']){
                        case "ORG":
                            $IBLOCK_ID = $this -> IBLOCK_ID_ORG;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_1013" => (string)$item['entity_id'],
                            ];
                            break;
                        case "PLA":
                            $IBLOCK_ID = $this -> IBLOCK_ID_PLA;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_1518" => (string)$item['entity_id'],
                            ];
                            break;
                        case "CON":
                            $IBLOCK_ID = $this -> IBLOCK_ID_CON;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_1000" => (string)$item['entity_id'],
                            ];
                            break;
                        case "CRE":
                            $IBLOCK_ID = $this -> IBLOCK_ID_CRE;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_999" => (string)$item['entity_id'],
                            ];
                            break;
                        case "STA":
                            $IBLOCK_ID = $this -> IBLOCK_ID_STA;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_1428" => (string)$item['entity_id'],
                            ];
                            break;
                        case "INV":
                            $IBLOCK_ID = $this -> IBLOCK_ID_INV;
                            $filter = [
                                "IBLOCK_ID" => $IBLOCK_ID,
                                "PROPERTY_1413" => (string)$item['entity_id'],  //TODO: на тестовой PROPERTY_1428,на боевой PROPERTY_1413
                            ];
                            break;
                    }

                    $STATE_ENUM_ID_STATE = Entity::getPropertyEnumIdByArray('DELETING', $IBLOCK_ID);

                    $arUpdateProperties = [
                        'state' => $STATE_ENUM_ID_STATE,
                    ];
                    $res = \CIBlockElement::GetList(
                        [],
                        $filter,
                        false,
                        [],
                        ['*','PROPERTY_*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
                    );
                    if ($arElement = $res->GetNextElement()) {
                        $arProps = $arElement->GetProperties();
                        if($arProps['state']['VALUE_ENUM_ID']==$STATE_ENUM_ID_STATE)continue;

                        \CIBlockElement::SetPropertyValuesEx($arElement->fields['ID'], $IBLOCK_ID, $arUpdateProperties);

                        $smartId = $arProps['SMART_ORD']['VALUE'];
                        if(is_null($smartId)) $smartId = $arProps['linkItemId']['VALUE'];
                        if(is_null($smartId)) $smartId = $arProps['elementItemId']['VALUE'];
                        if(is_null($smartId)) $smartId = $arProps['ORD_ELEMENT']['VALUE'];

                        $textUpdate = "Указан в заявке на удаление: ".$arElement->fields['NAME']."\nСтатус (было => стало):";

                        foreach($arUpdateProperties as $key => $value){
                            $enum = $arProps[$key]['VALUE_ENUM_ID'];
                            if($enum != null) $textUpdate = $textUpdate."\n".$key." ".$enum." => ".$value;
                            elseif($arProps[$key]['MULTIPLE']!="Y" || !is_array($arProps[$key]['VALUE'])) $textUpdate = $textUpdate."\n".$key." ".$arProps[$key]['VALUE']." => ".$value;
                            else {
                                if (!is_array($value)) {
                                    $value = array($value);
                                }
                                foreach($arProps[$key]['VALUE'] as $i => $j){
                                    $textUpdate = $textUpdate."\n".$key." ".$j." => ".$value[$i];
                                }
                            }
                        }

                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $smartId,
                                "ENTITY_TYPE" => "DYNAMIC_148",
                                "COMMENT" => $textUpdate
                            ]
                        ]);
                    }
                }
                break;
        }
    }

    // Добавление или обновление элемента в инфоблоке (при единаразовой загрузке)
    private static function AddOrUpdateIBlockElement($arAddElement, $filter): void
    {
        $res = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            [],
            ['*','PROPERTY_*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
        );

        if ($arElement = $res->GetNextElement()) {
            if($arProps = $arElement->GetProperties()){
                $needUpdate = false;
                $arUpdateProperties = [];
                foreach ($arAddElement['PROPERTY_VALUES'] as $propertyId => $newValue) {
                    if (isset($arProps[$propertyId])) {
                        $oldValue = $arProps[$propertyId];
                        // Сравниваем значения для свойств типа "Список"
                        if ($oldValue["PROPERTY_TYPE"] == "L") {
                            $needUpdate = false;
                            if($oldValue['MULTIPLE']=="Y"){
                                if (!is_array($oldValue['VALUE_ENUM_ID'])) {
                                    $oldValue['VALUE_ENUM_ID'] = array($oldValue['VALUE_ENUM_ID']);
                                }
                                if (!is_array($newValue)) {
                                    $newValue = array($newValue);
                                }
                                foreach ($newValue as $value) {
                                    if (!in_array($value, $oldValue['VALUE_ENUM_ID'])) {
                                        $needUpdate = true;
                                        break; // Если хоть один элемент не найден, выходим из цикла
                                    }
                                }
                            }
                            elseif($oldValue['VALUE_ENUM_ID'] != $newValue) $needUpdate = true;

                            if ($needUpdate) {
                                $arUpdateProperties[$propertyId] = $newValue;
                            }

                        }

                        // Сравниваем значения для свойств типа "Привязка к элементам"
                        elseif ($oldValue["PROPERTY_TYPE"] == "E") {
                            if($propertyId!='ktu') continue;

                            $codeKtuOld = [];

                            foreach ($oldValue['VALUE'] as $numElem => $idElem){
                                $resu = \CIBlockElement::GetList(
                                    [],
                                    [
                                        "IBLOCK_ID" => $oldValue['LINK_IBLOCK_ID'],
                                        "ID" => $idElem,
                                    ],
                                    false,
                                    [],
                                    ['*','PROPERTY_*']
                                );
                                if ($Element = $resu->GetNextElement()) {
                                    if($Props = $Element->GetProperties()){
                                        $codeKtuOld[] = $Props['CODE']['VALUE'];
                                    }
                                }
                            }

                            foreach ($codeKtuOld as $value) {
                                if (!in_array($value, $newValue)) {
                                    $needUpdate = true;
                                    break; // Если хоть один элемент не найден, выходим из цикла
                                } else {
                                    $needUpdate = false;
                                }
                            }

                            if ($needUpdate) {
                                $newIdKtu = [];
                                foreach($newValue as $key => $value){
                                    $resu = \CIBlockElement::GetList(
                                        [],
                                        [
                                            "IBLOCK_ID" => $oldValue['LINK_IBLOCK_ID'],
                                            'PROPERTY_1482' => $value,//TODO: на тестовой PROPERTY_1414,на боевой PROPERTY_1482
                                        ],
                                        false,
                                        [],
                                        ['*']
                                    );
                                    if ($Element = $resu->GetNextElement()) {
                                        $newIdKtu[] = $Element->fields['ID'];
                                    }
                                }
                                $arUpdateProperties[$propertyId] = $newIdKtu;
                            }
                        }
                        else {
                            $needUpdate = false;
                            if($oldValue['MULTIPLE']=="Y" || is_array($oldValue['VALUE'])){
                                if (!is_array($oldValue['VALUE'])) {
                                    $oldValue['VALUE'] = array($oldValue['VALUE']);
                                }
                                if (!is_array($newValue)) {
                                    $newValue = array($newValue);
                                }
                                foreach ($newValue as $value) {
                                    if (!in_array($value, $oldValue['VALUE'])) {
                                        $needUpdate = true;
                                        break; // Если хоть один элемент не найден, выходим из цикла
                                    }
                                }
                            }else{
                                // Сравниваем значения для других типов свойств
                                $oldValue = (string)$oldValue['VALUE'];
                                $newValue = (string)$newValue;
                                if($oldValue!=$newValue)$needUpdate = true;
                            }
                            if ($needUpdate) {
                                $arUpdateProperties[$propertyId] = $newValue;
                            }
                        }
                    }
                }

                // Обновляем свойства, если есть что обновлять
                if (!empty($arUpdateProperties)) {
                    $smartId = $arProps['SMART_ORD']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['linkItemId']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['elementItemId']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['ORD_ELEMENT']['VALUE'];

                    $textUpdate = "Обновление полей для: ".$arElement->fields['NAME']."\nПоля (было => стало):";

                    foreach($arUpdateProperties as $key => $value){
                        $textUpdate = $textUpdate."\n".$key;
                        $enum = $arProps[$key]['VALUE_ENUM_ID'];
                        if($enum != null) $textUpdate = $textUpdate."\n".$key." ".$enum." => ".$value;
                        elseif($arProps[$key]['MULTIPLE']!="Y" && !is_array($arProps[$key]['VALUE'])) {
                            $textUpdate = $textUpdate." ".$arProps[$key]['VALUE']." => ".$value;
                        }
                        else{

                            if (!is_array($value)) {
                                $value = array($value);
                            }
                            if(!is_null($arProps[$key]['VALUE']['TEXT'])) $textUpdate = $textUpdate." ".$arProps[$key]['VALUE']['TEXT'].",";
                            else foreach($arProps[$key]['VALUE'] as $val){
                                $textUpdate = $textUpdate." ".$val.",";
                            }

                            $textUpdate = substr($textUpdate, 0, -1);
                            $textUpdate = $textUpdate." => ";

                            foreach($value as $val){
                                $textUpdate = $textUpdate." ".$val.",";
                            }

                            $textUpdate = substr($textUpdate, 0, -1);
                        }
                    }
                    \CIBlockElement::SetPropertyValuesEx($arElement->fields['ID'], $arAddElement['IBLOCK_ID'], $arUpdateProperties);

                    \CRest ::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $smartId,
                            "ENTITY_TYPE" => "DYNAMIC_148",
                            "COMMENT" => $textUpdate
                        ]
                    ]);
                }
            }
        }
        else {
            $el = new \CIBlockElement;
            $newElementId = $el->Add($arAddElement);
            var_dump($newElementId);
            print_r($arAddElement);
            print_r($newElementId);
            self::bizProc($arAddElement['IBLOCK_ID'], $newElementId, 3);
        }
    }

    // Добавление или отправить запрос менеджеру ОРД элемента в инфоблоке (при ежедневной загрузке)
    private function AddOrCheckIBlockElement($arAddElement, $filter): void
    {
        $res = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            [],
            ['*','PROPERTY_*'] // Оптимизированный запрос: больше не запрашиваем 'PROPERTY_*'
        );
        $checkElementId = 0;
        if ($arElement = $res->GetNextElement()) {
            $checkElementId = $arElement->fields['ID'];
            if($arProps = $arElement->GetProperties()){
                $needUpdate = false;
                $arUpdateProperties = [];
                foreach ($arAddElement['PROPERTY_VALUES'] as $propertyId => $newValue) {
                    if (isset($arProps[$propertyId])) {
                        $oldValue = $arProps[$propertyId];
                        // Сравниваем значения для свойств типа "Список"
                        if ($oldValue["PROPERTY_TYPE"] == "L") {
                            $needUpdate = false;
                            if($oldValue['MULTIPLE']=="Y"){
                                if (!is_array($oldValue['VALUE_ENUM_ID'])) {
                                    $oldValue['VALUE_ENUM_ID'] = array($oldValue['VALUE_ENUM_ID']);
                                }
                                if (!is_array($newValue)) {
                                    $newValue = array($newValue);
                                }
                                foreach ($newValue as $value) {
                                    if (!in_array($value, $oldValue['VALUE_ENUM_ID'])) {
                                        $needUpdate = true;
                                        break; // Если хоть один элемент не найден, выходим из цикла
                                    }
                                }
                            }
                            elseif($oldValue['VALUE_ENUM_ID'] != $newValue) $needUpdate = true;

                            if ($needUpdate) {
                                $arUpdateProperties[$propertyId] = $newValue;
                            }

                        }

                        // Сравниваем значения для свойств типа "Привязка к элементам"
                        elseif ($oldValue["PROPERTY_TYPE"] == "E") {
                            if($propertyId!='ktu') continue;

                            $codeKtuOld = [];

                            foreach ($oldValue['VALUE'] as $numElem => $idElem){
                                $resu = \CIBlockElement::GetList(
                                    [],
                                    [
                                        "IBLOCK_ID" => $oldValue['LINK_IBLOCK_ID'],
                                        "ID" => $idElem,
                                    ],
                                    false,
                                    [],
                                    ['*','PROPERTY_*']
                                );
                                if ($Element = $resu->GetNextElement()) {
                                    if($Props = $Element->GetProperties()){
                                        $codeKtuOld[] = $Props['CODE']['VALUE'];
                                    }
                                }
                            }

                            foreach ($codeKtuOld as $value) {
                                if (!in_array($value, $newValue)) {
                                    $needUpdate = true;
                                    break; // Если хоть один элемент не найден, выходим из цикла
                                } else {
                                    $needUpdate = false;
                                }
                            }

                            if ($needUpdate) {
                                $newIdKtu = [];
                                foreach($newValue as $key => $value){
                                    $resu = \CIBlockElement::GetList(
                                        [],
                                        [
                                            "IBLOCK_ID" => $oldValue['LINK_IBLOCK_ID'],
                                            'PROPERTY_1482' => $value,//TODO: на тестовой PROPERTY_1414,на боевой PROPERTY_1482
                                        ],
                                        false,
                                        [],
                                        ['*']
                                    );
                                    if ($Element = $resu->GetNextElement()) {
                                        $newIdKtu[] = $Element->fields['ID'];
                                    }
                                }
                                $arUpdateProperties[$propertyId] = $newIdKtu;
                            }
                        }
                        else {
                            $needUpdate = false;
                            if($oldValue['MULTIPLE']=="Y" || is_array($oldValue['VALUE'])){
                                if (!is_array($oldValue['VALUE'])) {
                                    $oldValue['VALUE'] = array($oldValue['VALUE']);
                                }
                                if (!is_array($newValue)) {
                                    $newValue = array($newValue);
                                }
                                foreach ($newValue as $value) {
                                    if (!in_array($value, $oldValue['VALUE'])) {
                                        $needUpdate = true;
                                        break; // Если хоть один элемент не найден, выходим из цикла
                                    }
                                }
                            }else{
                                // Сравниваем значения для других типов свойств
                                $oldValue = (string)$oldValue['VALUE'];
                                $newValue = (string)$newValue;
                                if($oldValue!=$newValue)$needUpdate = true;
                            }
                            if ($needUpdate) {
                                $arUpdateProperties[$propertyId] = $newValue;
                            }
                        }
                    }
                }

                // Обновляем свойства, если есть что обновлять
                if (!empty($arUpdateProperties)) {
                    $smartId = $arProps['SMART_ORD']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['linkItemId']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['elementItemId']['VALUE'];
                    if(is_null($smartId)) $smartId = $arProps['ORD_ELEMENT']['VALUE'];

                    //Сразу обновляем статус, если он есть
                    if(!is_null($arUpdateProperties['state'])){
                        \CIBlockElement::SetPropertyValuesEx($arElement->fields['ID'], $arAddElement['IBLOCK_ID'], $arUpdateProperties['state']);
                        $arUpdateProperties['state'] = null;
                        if(is_null($arUpdateProperties)){
                            return;
                        }
                    }

                    $textUpdate = "Различие полей полей для: ".$arElement->fields['NAME']."\nПоля (Битрикс24 <=> ОРД):";

                    foreach($arUpdateProperties as $key => $value){
                        $textUpdate = $textUpdate."\n".$key;
                        $enum = $arProps[$key]['VALUE_ENUM_ID'];
                        if($enum != null) $textUpdate = $textUpdate."\n".$key." ".$enum." <=> ".$value;
                        elseif($arProps[$key]['MULTIPLE']!="Y" && !is_array($arProps[$key]['VALUE'])) {
                            $textUpdate = $textUpdate." ".$arProps[$key]['VALUE']." <=> ".$value;
                        }
                        else{

                            if (!is_array($value)) {
                                $value = array($value);
                            }
                            if(!is_null($arProps[$key]['VALUE']['TEXT'])) $textUpdate = $textUpdate." ".$arProps[$key]['VALUE']['TEXT'].",";
                            else foreach($arProps[$key]['VALUE'] as $val){
                                $textUpdate = $textUpdate." ".$val.",";
                            }

                            $textUpdate = substr($textUpdate, 0, -1);
                            $textUpdate = $textUpdate." <=> ";

                            foreach($value as $val){
                                $textUpdate = $textUpdate." ".$val.",";
                            }

                            $textUpdate = substr($textUpdate, 0, -1);
                        }
                    }
                    if(!is_null($arUpdateProperties['state'])) \CIBlockElement::SetPropertyValuesEx($arElement->fields['ID'], $arAddElement['IBLOCK_ID'], $arUpdateProperties['state']);


//                    \CRest ::call('crm.timeline.comment.add', [
//                        'fields' => [
//                            "ENTITY_ID" => $smartId,
//                            "ENTITY_TYPE" => "DYNAMIC_148",
//                            "COMMENT" => $textUpdate
//                        ]
//                    ]);
                    $arWorkflowParameters['txt'] = $textUpdate;
                    self::bizProc($arAddElement['IBLOCK_ID'], $checkElementId, 0, $this -> CHECK_UPDATE_BP_NAME, $arWorkflowParameters);
                }
            }
        }
        else {
            $el = new \CIBlockElement;
            $newElementId = $el->Add($arAddElement);
            self::bizProc($arAddElement['IBLOCK_ID'], $newElementId, 3);
        }
    }

    // Вспомогательная функция для форматирования даты
    private static function formatDate($dateString): false|string
    {
        try {
            $date = new \DateTime($dateString);
            return $date->format(\CDatabase::DateFormatToPHP(FORMAT_DATE)); // Формат Bitrix для даты
        } catch (\Exception $e) {
            return false; // Или null, если не удалось распарсить дату
        }
    }

    private static function getPropertyEnumId($code,$IBLOCK_ID): array
    {
        $arFilter = array(
            "IBLOCK_ID" => $IBLOCK_ID,
            "CODE" => $code // Код вашего свойства типа "Список"
        );
        $rsPropsType = \CIBlockPropertyEnum::GetList(array(), $arFilter);
        while ($arPropType = $rsPropsType->Fetch()) {
            $arTypeId[$arPropType["XML_ID"]] = $arPropType["ID"];
            $arTypeName[$arPropType["XML_ID"]] = $arPropType["VALUE"];
        }
        return $arTypeId;
    }

    private static function bizProc($IBLOCK_ID, $elementId, $AUTO_EXECUTE, $bizProcName = null, $arWorkflowParameters = []): ?string
    {
        global $USER;

        $arErrorsTmp = [];
        $errorMessage = null;

        if (!is_object($USER)) {
            $USER = new \CUser();
        }

        // Бизнес процесс
        if (Loader::IncludeModule('bizproc')) {
            $arWorkflowTemplates = \CBPDocument::GetWorkflowTemplatesForDocumentType([
                'lists', 'Bitrix\Lists\BizprocDocumentLists', 'iblock_' . $IBLOCK_ID
            ]);

            foreach ($arWorkflowTemplates as $arTemplate) {
                /*
                    * AUTO_EXECUTE = 1 - запускать при создании
                    * AUTO_EXECUTE = 2 - запускать при изменении
                    * AUTO_EXECUTE = 3 - запускать при создании И изменении
                */
                if ($arTemplate['AUTO_EXECUTE'] == $AUTO_EXECUTE && is_null($bizProcName)) {
                    $wfId = \CBPDocument::StartWorkflow(
                        $arTemplate['ID'],
                        [ 'lists', 'Bitrix\Lists\BizprocDocumentLists', $elementId ],
                        array_merge($arWorkflowParameters, [ 'TargetUser' => "user_{$USER->GetID()}" ]),
                        $arErrorsTmp
                    );


                    if (count($arErrorsTmp) > 0) {
                        foreach ($arErrorsTmp as $e) {
                            $errorMessage .= "[".$e["code"]."] ".$e["message"]."";
                        }
                    }
                }else if(!is_null($bizProcName) && $bizProcName == $arTemplate['NAME']) {
                    $wfId = \CBPDocument::StartWorkflow(
                        $arTemplate['ID'],
                        [ 'lists', 'Bitrix\Lists\BizprocDocumentLists', $elementId ],
                        array_merge($arWorkflowParameters, [ 'TargetUser' => "user_{$USER->GetID()}" ]),
                        $arErrorsTmp
                    );


                    if (count($arErrorsTmp) > 0) {
                        foreach ($arErrorsTmp as $e) {
                            $errorMessage .= "[".$e["code"]."] ".$e["message"]."";
                        }
                    }
                }
            }
        }

        return $errorMessage;
    }

}
