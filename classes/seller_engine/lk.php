<?php namespace KPLab;

define("LOG_LK", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/seller_engine_lk.log");
define('API_KEY','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU');
define("TOKEN_LK", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");

use AllowDynamicProperties;
use Bitrix\Main\Web\HttpClient;
use \KPLab\Logs;

class SellerCapitalLK
{
	public $apiTestUrl;
	public $apiUrl;
	public $internalTestUrl;
	public $internalUrl;
	public $point;

	public $itemDatatitle;
	public $companyId;
	public $entityTypeId;
	public $CURLObjectData;
	public $multipartFormData;
	public $post_data;
	public $sellerInn;
	public $resSellerGetByInnStatus;
	public $sellerId;
	public $smartLKId;
	public $sellerPhone;
	public $sellerFirstName;
	public $sellerLastName;
	public $lastName;
	public $firstName;
	public $secondName;
	public $sellerPatronymic;
	public $isPartner;
	public $isSeller;
	public $accountStatus;
	public $get_SellerGetByInn_testUrl;
	public $post_SellerCreateMinimal_testUrl;
	public $post_lkUserCreate_testUrl;
    public $arrayPingResponse;
    public $domain;
    public $typeOfClient;

    public function __construct($isTest = false)
    {
        // Определяем текущий домен
        $this->domain = $_SERVER['HTTP_HOST'];

        if ($isTest !== false) {
            $this -> apiUrl = "https://api.dev.seller-capital.ru/";
            $this -> internalUrl = "https://internal.dev.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.dev.seller-capital.ru/lk/user/Authenticate";
        } else {
            $this -> apiUrl = "https://api.seller-capital.ru/";
            $this -> internalUrl = "https://internal.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.seller-capital.ru/lk/user/Authenticate";
        }

        $this -> point = "BX_SE";
        $this -> errorMessage = null;
        $this->isSeller = false;
        $this->isPartner = false;
    }
	protected function getSellerInn($ENTITY_ID) {
		global $DB;
		$RQItemSQL = "SELECT * FROM b_crm_requisite WHERE ENTITY_ID='{$ENTITY_ID}' ORDER BY ID ASC;";

		$resRQItemsQuery = $DB->query($RQItemSQL);
		while($resRQItem = $resRQItemsQuery->Fetch()) {
			$this->sellerInn = $resRQItem['RQ_INN'];
		}
	}
    protected function getSellerRQ($ENTITY_ID) {
        global $DB;
        $RQItemSQL = "SELECT * FROM b_crm_requisite WHERE ENTITY_ID='{$ENTITY_ID}' ORDER BY ID ASC;";

        $resRQItemsQuery = $DB->query($RQItemSQL);
        while($resRQItem = $resRQItemsQuery->Fetch()) {
            $this->sellerInn = $resRQItem['RQ_INN'];
            $this->sellerLastName = $resRQItem['RQ_LAST_NAME'];
            $this->sellerFirstName = $resRQItem['RQ_FIRST_NAME'];
            $this->sellerSecondName = $resRQItem['RQ_SECOND_NAME'];
        }
    }
	protected function getInfoSmart($smartId) {
		global $DB;

		$entityTypeId = 128;
		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
		$item = $factory -> getItem($smartId);
		if($item) {
			$accountStatusId = $item->getData()['UF_CRM_LKSC_STATUS'];

			$itemSQL = "SELECT * FROM b_user_field_enum  WHERE ID='{$accountStatusId}' ORDER BY ID ASC;";
			$resItemsQuery = $DB->query($itemSQL);
			while($resItem = $resItemsQuery->Fetch()) {
				$this->accountStatus = (string) $resItem['XML_ID'];
			}

			$this->sellerId = $item->getData()['UF_CRM_LKSC_SELLERID'];
            $this->itemDatatitle = $item->getData()['TITLE'];

            $roleClientArrayIds = $item->getData()['UF_CRM_ROLE_OF_THE_CLIENT'];
            foreach($roleClientArrayIds as $roleClientId) {
                $itemSQL = "SELECT * FROM b_user_field_enum  WHERE ID='{$roleClientId}' ORDER BY ID ASC;";
                $resItemsQuery = $DB->query($itemSQL);
                while($resItem = $resItemsQuery->Fetch()) {
                    $this->roleClient[] = (string) $resItem['XML_ID'];
                }
            }
            foreach($this->roleClient as $role) {
                if($role == "IsSeller") $this->isSeller = true;
                if($role == "IsPartner") $this->isPartner = true;
            }
            $this->passwordRecoveryToken = $item->getData()['UF_CRM_29_1720813039867'];
            $this->passwordForMigration = $item->getData()['UF_CRM_PASSWORD_FOR_MIGRATION'];

            $typeClientArrayId = $item->getData()['UF_CRM_29_TIP_KLIENTA'];
            $itemSQL = "SELECT * FROM b_user_field_enum  WHERE ID='{$typeClientArrayId}' ORDER BY ID ASC;";
            $resItemsQuery = $DB->query($itemSQL);
            while($resItem = $resItemsQuery->Fetch()) {
                $this->typeClient[] = (string) $resItem['XML_ID'];
            }

            foreach($this->typeClient as $type) {
                if($type == "SZ") $this->typeOfClient = "SZ";
                if($type == "UL") $this->typeOfClient = "UL";
                if($type == "IP") $this->typeOfClient = "IP";
            }
		}
	}
	protected function UpdateInfoSmartLK($smartLKId, $params) {
		$entityTypeId = 128;
		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
		$item = $factory -> getItem($smartLKId);
		$item->set("UF_CRM_LKSC_SELLERID", $params["sellerId"]);
		$operation = $factory->getUpdateOperation($item);
		$operationResult = $operation->launch();
	}

	public function getCompany($companyId,$smartLKId) {

		Logs\File::AddMessage([$companyId,$smartLKId],"getCompany properties",LOG_LK);
		global $DB;
		$this->smartLKId = $smartLKId;
        $this->getInfoSmart($this->smartLKId);

		$this->companyId = $companyId;
		$this->entityTypeId = \CCrmOwnerType::Company;

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
		$item = $factory -> getItem($this->companyId);

		if($item) {
			$this->itemDatatitle = $item->getData()['TITLE'];
			$itemSQL = "SELECT * FROM b_crm_field_multi WHERE ENTITY_ID='COMPANY' AND ELEMENT_ID='{$companyId}' AND TYPE_ID='PHONE' AND VALUE_TYPE='MOBILE' ORDER BY ID ASC;";
			$resItemsQuery = $DB->query($itemSQL);
			while($resItem = $resItemsQuery->Fetch()) {
				$this->sellerPhone = (string) $resItem['VALUE'];
			}
            $this->getSellerInn($companyId);
			$this->getSellerRQ($companyId);
		}
		else {
			$this->errorMessage = "Не найдена Компания с ID:{$this->companyId}";
		}

		return $this;
	}

	public function getContact($contactId) {

		$this->itemId = $contactId;
		$this->entityTypeId = \CCrmOwnerType::Contact;

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
		$item = $factory -> getItem($this->itemId);

		if($item) {
			$this->itemDatatitle = $item->getData()['TITLE'];
            $this->getSellerInn($contactId);
		}
		else {
			$this->errorMessage = "Не найден Контакт с ID:{$this->itemId}";
		}

		return $this;
	}

	public function GET_SellerGetByInn_testUrl() {
		self::setCURLObjectData($this->companyId);
		$this->CURLObjectData['METHOD'] = "GET";
		$this->CURLObjectData['ITEM_TITLE'] = "ЛК[Проверка на существование селлера по инн]: ". $this->itemDatatitle."|".$this->sellerInn;
		$this->get_SellerGetByInn_Url = $this->apiUrl . "Seller/GetByInn?inn={$this->sellerInn}";
		$jsonResponse = static::requestGET($this->get_SellerGetByInn_Url);

		if($jsonResponse['http_code'] == 404) {
			$this->resSellerGetByInnStatus['text'] = 'Селлер не найден!';
			$this->resSellerGetByInnStatus['code'] = $jsonResponse['http_code'];

			$jsonResponse = static::POST_SellerCreateMinimal_testUrl();
		}
		elseif($jsonResponse['http_code'] == 200) {
			$this->resSellerGetByInnStatus['text'] = 'Селлер найден!';
			$this->resSellerGetByInnStatus['code'] = $jsonResponse['http_code'];
			$this->resSellerGetByInnStatus['response'] = $jsonResponse['success'];

			$arrayResponse = json_decode($jsonResponse['success'], true);
			$this->resSellerGetByInnStatus['sellerId'] = $arrayResponse['id'];
			$this->sellerId = $arrayResponse['id'];
			$params = ["sellerId" => $this->sellerId];
			self::UpdateInfoSmartLK($this->smartLKId, $params);
			$jsonResponse = static::POST_lkUserCreate_testUrl();
		}

		return $jsonResponse;
	}

	public function POST_SellerCreateMinimal_testUrl() {
        self::setCURLObjectData($this->companyId);
		$this->CURLObjectData['ITEM_TITLE'] = "ЛК[Создание селлера]: ". $this->itemDatatitle;
		$this->CURLObjectData['METHOD'] = "POST";
		$this->jsonData = json_encode(["inn"=>$this->sellerInn,"name"=>$this->itemDatatitle]);
		$this->post_SellerCreateMinimal_Url = $this->apiUrl . "Seller/CreateMinimal";
		$jsonResponse = static::requestPOST($this->post_SellerCreateMinimal_Url);
		$this->sellerId = $jsonResponse['success']['id'];
		$params = ["sellerId" => $this->sellerId];
		self::UpdateInfoSmartLK($this->smartLKId, $params);
		$jsonResponse = static::POST_lkUserCreate_testUrl();
		return $jsonResponse;
	}

    public function POST_ForgotPassword($passwordRecoveryToken) {
        $timeData = Logs\TimeData::start();
        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';

        self::setCURLObjectData($this->companyId);
        $this->CURLObjectData['ITEM_TITLE'] = "ЛК[Смена пароля]: ". $this->itemDatatitle;
        $this->CURLObjectData['METHOD'] = "POST";
        $this->jsonData = json_encode(["passwordRecoveryToken"=>$passwordRecoveryToken,"newPassword"=>$this->passwordForMigration]);

        $this->post_ForgotPassword_url = $this->internalUrl . "Lk/User/ForgotPassword";

        $headersRequest = [
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json-patch+json"
        ];

        $point = "BX_SE";

        $logData = [
            'objectData' => $this->CURLObjectData,
            'methodName' => __FUNCTION__,
            'controllerName' => get_class($this),
            'method' => 'POST',
            'timeData' => $timeData,
            'point' => $point
        ];
        /*$jsonResponse = static::requestPOST($this->post_ForgotPassword_url,true, true, false, false, true);
        return $jsonResponse;*/

        $jsonResponse = \KPLab\ApiRequest::sendRequest($this->post_ForgotPassword_url, 'POST', $headersRequest, $this->jsonData, $logData);
        return $jsonResponse;
    }

	public function POST_lkUserCreate_testUrl() {
        $timeData = Logs\TimeData::start();
        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';

        $headersRequest = [
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json-patch+json"
        ];

		self::setCURLObjectData($this->companyId);
		self::getInfoSmart($this->smartLKId);
        $requisiteData = self::getRequisiteData((int)$this->companyId, 4); // 4 - тип сущности для компании
        if (!empty($requisiteData['RQ_LAST_NAME']) && !empty($requisiteData['RQ_FIRST_NAME'])) {
            $this->lastName = $requisiteData['RQ_LAST_NAME'];
            $this->firstName = $requisiteData['RQ_FIRST_NAME'];
            $this->secondName = $requisiteData['RQ_SECOND_NAME'];
        }
		$this->CURLObjectData['ITEM_TITLE'] = "ЛК[Создание аккаунта ЛК и привязка к селлеру]: ". $this->itemDatatitle ." | ".$this->sellerId;
		$this->CURLObjectData['METHOD'] = "POST";

		$this->jsonData = json_encode([
            "lastName" => (string) $this->lastName,
            "firstName" => (string) $this->firstName,
            "patronymic" => (string) $this->secondName,
			"phone" => (string) $this->sellerPhone,
			"legalEntityInn" => (string) $this->sellerInn,
			"crmId" => (string) $this->smartLKId,
            "isPartner" => (bool) $this->isPartner,
            "isSeller" => (bool) $this->isSeller,
            "typeOfClient" => (string) $this->typeOfClient
		]);
		$this->post_lkUserCreate_Url = $this->internalUrl . "lk/user/Create";

        $point = "BX_SE";

        $logData = [
            'objectData' => $this->CURLObjectData,
            'methodName' => __FUNCTION__,
            'controllerName' => get_class($this),
            'method' => 'POST',
            'timeData' => $timeData,
            'point' => $point
        ];

		/*$arrayResponse = $this->requestPOST($this->post_lkUserCreate_Url, true, true, false, false, false);
		Logs\File::AddMessage($arrayResponse,"arrayResponse from LK",LOG_LK);
		return $arrayResponse;*/

        $jsonResponse = \KPLab\ApiRequest::sendRequest($this->post_lkUserCreate_Url, 'POST', $headersRequest, $this->jsonData, $logData);
        return $jsonResponse;
	}

    public static function getRequisiteData($entityId, int $entityTypeId): ?array
    {
        try {
            $req = new \Bitrix\Crm\EntityRequisite();
            $result = $req->getList([
                'filter' => ['ENTITY_ID' => $entityId, 'ENTITY_TYPE_ID' => $entityTypeId],
                'select' => ['*', 'UF_*']
            ])->fetch();

            if ($result) {
                return $result;
            } else {
                Logs\File::AddMessage("Реквизиты не найдены для ENTITY_ID {$entityId}", "getRequisiteData warning", LOG_LK);
                return null;
            }
        } catch (\Exception $e) {
            Logs\File::AddMessage($e->getMessage(), "getRequisiteData error", LOG_LK);
            return null;
        }
    }

    public function POST_PingSE() {
        $timeData = Logs\TimeData::start();
        $point = "BX_SE";
        $TOKEN_LK = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU";
        self::setCURLObjectData($this->companyId);
        self::getInfoSmart($this->smartLKId);

        $this->jsonData = "{}";

        $this->post_pingUrl = $this->apiUrl . "LegalEntity/GetInfoFromKontur?inn=".$this->sellerInn;
        //Logs\File::AddMessage($this->post_pingUrl,"post_pingUrl from LK",LOG_LK);

        $this->CURLObjectData['ITEM_TITLE'] = "ЛК[ПИНГ SE]: ". $this->itemDatatitle ." | ".$this->sellerId;
        $this->CURLObjectData['METHOD'] = "GET";
        $this->CURLObjectData['URL_REQUEST'] = $this->post_pingUrl;

        // Формируем логируемые данные
        $logData = [
            'objectData' => $this->CURLObjectData,
            'methodName' => __FUNCTION__,
            'controllerName' => get_class($this),
            'method' => 'GET',
            'timeData' => $timeData,
            'point' => $point
        ];

        // Используем KPLab\ApiRequest для выполнения GET-запроса
        $headersRequest = [
            'Authorization: Bearer ' . $TOKEN_LK,
            'Content-Type: application/json'
        ];
        // Вызов метода sendRequest с передачей $logData
        $this->arrayPingResponse = \KPLab\ApiRequest::sendRequest($this->post_pingUrl, 'GET', $headersRequest, null, $logData);


        /*if (defined("TOKEN_LK")) {
            $jsonResponse = \KPLab\Curl::get_LK(TOKEN_LK, $this->post_pingUrl, $this->jsonData, $this->CURLObjectData,
                $timeData, $this->point);
            $this->arrayPingResponse = $jsonResponse;
        }*/
        Logs\File::AddMessage($this->arrayPingResponse,"arrayPingResponse ПОСЛЕ from LK",LOG_LK);

        return $this->arrayPingResponse;
    }

	protected function setCURLObjectData($itemId) {
		$this->CURLObjectData['ITEM_ID'] = $itemId;
		$this->CURLObjectData['ITEM_TYPE_ID'] = $this->entityTypeId;
		$this->CURLObjectData['ITEM_TITLE'] = "ЛК: ". $this->itemDatatitle;
		$this->CURLObjectData['INIT_OBJECT_URL'] = "https://{$this->domain}/crm/type/{$this->entityTypeId}/details/{$itemId}/";
		return $this->CURLObjectData;
	}

	public function documentCreate($smartEdoId) {
		$emulation = false;
		$timeData = Logs\TimeData::start();
		$this->entityTypeId = 1060;
		self::setCURLObjectData($smartEdoId);

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId);
		$item = $factory->getItem($smartEdoId);
		$smart1060Data = $item->getData();

		$this->itemDatatitle = $smart1060Data['TITLE'];
		$this->sellerInn = $smart1060Data['UF_CRM_SELLERS_INN'];
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $smart1060Data["UF_CRM_EDO_DOCTYPE"]
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$this->edoDocType = $arEnum['XML_ID'];
		}
		$this->CURLObjectData['ITEM_TITLE'] = "SE[Создание документа в SE]: ". $this->itemDatatitle;
		$this->CURLObjectData['METHOD'] = "POST";

		if (defined("TOKEN_LK")) {
			$multipartData = true;
			$itemDatatitle = urlencode($this->itemDatatitle);
			$httpHeaders = array(
				"key" => TOKEN_LK,
				"accept" => "*/*",
				"Content-Type" => 'multipart/form-data'
			);
			$queryData = "?inn={$this->sellerInn}&type={$this->edoDocType}&title={$itemDatatitle}";
			$queryUrl = $this->apiUrl . "Document/Create".$queryData;

			$jsonResponse = \KPLab\Curl::postWithQueryUrl(
				TOKEN_LK,
				$queryUrl,
				$this->CURLObjectData,
				$timeData,
				$this->point,
				$emulation,
				$httpHeaders,
				null,
				$multipartData
			);

			if($jsonResponse['success'] !== null)
			{

				$document = $jsonResponse['response'];
				$document = json_decode($document, true);
				$documentSEId = $document["id"];
				$item -> set('UF_CRM_SE_DOCUMENT_ID', $documentSEId);

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
					$message = "Документ успешно создан в SE! {$documentSEId}";

				}
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_1060",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
				return $document["id"];
			}

		}

		return null;

	}

	public function setDocumentState($smartEdoId,$State) {
		$emulation = false;
		$timeData = Logs\TimeData::start();
		$this->entityTypeId = 1060;
		self::setCURLObjectData($smartEdoId);

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId);
		$item = $factory->getItem($smartEdoId);
		$smart1060Data = $item->getData();

		$this->itemDatatitle = $smart1060Data['TITLE'];
		$this->documentSEId = $smart1060Data['UF_CRM_SE_DOCUMENT_ID'];
		/*$this->statusDocId = $smart1034Data['UF_CRM_STATUS_DOC'];
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $smart1034Data['UF_CRM_STATUS_DOC']
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$statusDoc = $arEnum['XML_ID'];
		}
		if($statusDoc == "edo_Success") $this->statusDocValue = "Assigned";*/
		$this->CURLObjectData['ITEM_TITLE'] = "SE[Передача статуса документа в SE]: ". $this->itemDatatitle;
		$this->CURLObjectData['METHOD'] = "POST";
		
		if (defined("TOKEN_LK")) {
			$httpHeaders = array(
				"key" => TOKEN_LK,
				"accept" => "*/*",
			);
			$queryData = "?documentId={$this->documentSEId}&state={$State}";
			$queryUrl = $this->apiUrl . "Document/SetDocumentState".$queryData;
			
			$jsonResponse = \KPLab\Curl::postWithQueryUrl(
				TOKEN_LK,
				$queryUrl,
				$this->CURLObjectData,
				$timeData,
				$this->point,
				$emulation,
				$httpHeaders
			);

			//Logs\File::AddMessage($jsonResponse,"jsonResponse",LOG_LK);

			if($jsonResponse['success'] !== null) {
				$message = "Статус {$State} успешно отправлен в SE! ";
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_1060",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}

		}

		return $jsonResponse;
	}

	public function setAccountState() {
		$emulation = false;
		$timeData = Logs\TimeData::start();
		$this->entityTypeId = 128;
        $this->setCURLObjectData($this->smartLKId);

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this->entityTypeId);
		$item = $factory->getItem($this->smartLKId);
		$smart128Data = $item->getData();
		$this->itemDatatitle = $smart128Data['TITLE'];
		/*$this->statusDocId = $smart1034Data['UF_CRM_STATUS_DOC'];
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $smart1034Data['UF_CRM_STATUS_DOC']
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$statusDoc = $arEnum['XML_ID'];
		}
		if($statusDoc == "edo_Success") $this->statusDocValue = "Assigned";*/
		$this->CURLObjectData['ITEM_TITLE'] = "ЛК[Передача статуса ЛК в SE]: ". $this->itemDatatitle;
		$this->CURLObjectData['METHOD'] = "POST";

		if (defined("TOKEN_LK")) {
			$httpHeaders = array(
				"key" => TOKEN_LK,
				"accept" => "application/json",
			);
			$queryData = "?accountStatus={$this->accountStatus}&id={$this->sellerId}";
			$queryUrl = "{$this->internalUrl}Lk/User/SetAccountState".$queryData;

			$jsonResponse = \KPLab\Curl::postWithQueryUrl(
				TOKEN_LK,
				$queryUrl,
				$this->CURLObjectData,
				$timeData,
				$this->point,
				$emulation,
				$httpHeaders
			);

			//Logs\File::AddMessage($jsonResponse,"jsonResponse",LOG_LK);

			if($jsonResponse['success'] !== null) {
				$message = "Статус {$this->accountStatus} успешно отправлен в ЛК! ";
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_128",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}

		}

		return $jsonResponse;
	}
    public function updateLkUser() {
        $this->entityTypeId = 128;
        self::setCURLObjectData($this->smartLKId);
        self::getInfoSmart($this->smartLKId);

        $this->CURLObjectData['ITEM_TITLE'] = "ЛК[Обновление пользователя]: ". $this->itemDatatitle;
        $this->CURLObjectData['METHOD'] = "POST";

        $this->jsonData = json_encode([
            "id" => $this->sellerId,
            "phone" => (string) $this->sellerPhone,
            "legalEntityInn" => (string) $this->sellerInn,
            "crmId" => (string) $this->smartLKId,
            "firstName" => (string) $this->sellerFirstName,
            "lastName" => (string) $this->sellerLastName,
            "patronymic" => (string) $this->sellerSecondName,
            "isPartner" => (bool) $this->isPartner,
            "isSeller" => (bool) $this->isSeller
        ], JSON_UNESCAPED_UNICODE);
        Logs\File::AddMessage($this->jsonData,"jsonData",LOG_LK);
        $this->post_lkUserUpdateUrl = $this->internalUrl . "lk/user/Update";

        $arrayResponse = $this->requestPOST($this->post_lkUserUpdateUrl, true, true, false, false, false);
        Logs\File::AddMessage($arrayResponse,"arrayResponse from LK",LOG_LK);

        return $arrayResponse;
    }

	public function setOpdDate($smartEdoId, $signerId, $opdDate) {
		$emulation = false;
		$PRESET_ID = 2;
		$this->docSmartId = $smartEdoId;
		$timeData = Logs\TimeData::start();
		$this->entityTypeId = \CCrmOwnerType::Company;
		self::setCURLObjectData($signerId);


		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1060);
		$item = $factory->getItem($this->docSmartId);
		$smart1060Data = $item->getData();
		$SELLERS_INN = $smart1060Data['UF_CRM_SELLERS_INN'];

		$dateTime = new \DateTime($opdDate, new \DateTimeZone('UTC'));
		$formattedOpdDate = $dateTime->format('Y-m-d\TH:i:s.v\Z');

		Logs\File::AddMessage($formattedOpdDate,"formattedOpdDate",LOG_LK);

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $smart1060Data["UF_CRM_UF_SIGNER_TYPE"]
		));
		if ($arEnum = $rsEnum->Fetch()) {
			$this->counteragentType = $arEnum['XML_ID'];
		}

		Logs\File::AddMessage($this->counteragentType,"counteragentType",LOG_LK);

		if($this->counteragentType == "FL") {
			$PRESET_ID = 3;
			$url = $this->apiUrl . "Person/CreateOrUpdate?legalEntityInn={$SELLERS_INN}";
		}
		if($this->counteragentType == "UL") {
			$PRESET_ID = 1;
			$url = $this->apiUrl . "LegalEntity/CreateOrUpdate";
		}
		if($this->counteragentType == "IP") {
			$PRESET_ID = 2;
			$url = $this->apiUrl . "LegalEntity/CreateOrUpdate";
		}
		Logs\File::AddMessage($url,"url setOpdDate",LOG_LK);

		$INN = $smart1060Data["UF_CRM_UF_SIGNER_INN"];
		$KPP = $smart1060Data["UF_CRM_UF_SIGNER_KPP"];

		$this->CURLObjectData['ITEM_TITLE'] = "SE[Передача даты согласия в SE]: ". $this->itemDatatitle;
		$this->CURLObjectData['METHOD'] = "POST";

		if (defined("TOKEN_LK")) {
			$httpHeaders = array(
				"key" => TOKEN_LK,
				"accept" => "*/*",
			);
			$data = [
				"inn" => $INN,
				"opdDate" => $formattedOpdDate
			];
			Logs\File::AddMessage([$smartEdoId,$signerId,$formattedOpdDate,$INN],"inData",LOG_LK);
			$jsonResponse = \KPLab\Curl::postWithQueryUrl(
				TOKEN_LK,
				$url,
				$this->CURLObjectData,
				$timeData,
				$this->point,
				$emulation,
				$httpHeaders,
				$data
			);

			Logs\File::AddMessage($jsonResponse,"jsonResponse",LOG_LK);

			if($jsonResponse['success'] !== null) {
				$message = "Дата согласия {$opdDate} для ИНН: {$INN} успешно передана в SE! ";
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $smartEdoId,
						"ENTITY_TYPE" => "DYNAMIC_1060",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}

		}

		return $jsonResponse;
	}


	protected function requestPOST($url, $key, $internal, $emulation = false, $ping = false, $headerResponse = true) {
		$timeData = Logs\TimeData::start();
		if(!$internal) {
			if(!$key) {
				if (defined("TOKEN_LK")) {
					$jsonResponse = \KPLab\Curl::postApiSE_LK_Bearer(TOKEN_LK, $url, $this->jsonData, $this->CURLObjectData,
						$timeData, $this->point, $emulation, $ping, $headerResponse);
				}
			} else {
				if (defined("API_KEY")) {
					$jsonResponse = \KPLab\Curl::postApiSE_LK(API_KEY, $url, $this->jsonData, $this->CURLObjectData,
						$timeData, $this->point, $emulation, $ping, $headerResponse);
				}
			}
		} else {
			if(!$key)
			{
				if (defined("TOKEN_LK"))
				{
					$jsonResponse = \KPLab\Curl ::postInternalSE_LK_Bearer(TOKEN_LK, $url, $this -> jsonData, $this -> CURLObjectData,
						$timeData, $this -> point, $emulation, $ping, $headerResponse);
				}
			} else {
				if (defined("API_KEY"))
				{
					$jsonResponse = \KPLab\Curl ::postInternalSE_LK(API_KEY, $url, $this -> jsonData, $this ->
					CURLObjectData,
						$timeData, $this -> point, $emulation, $ping, $headerResponse);
				}
			}
		}
		if(!$ping) {
			$dataRequestArray = json_decode($this->jsonData, true);

			if($jsonResponse['error']) {
				$errorMessageAr = explode("--", $jsonResponse['error']);
				$errorMessageJson = substr_replace($errorMessageAr[2], "", 1, 0);
				$errorMessage = substr_replace($errorMessageJson, "{", 0, 0);
				$errorMessageLen = strlen($errorMessage);
				$errorMessage = substr_replace($errorMessage, "}", $errorMessageLen, 0);
				$errorMessageArray = json_decode($errorMessage, true);
				Logs\File::AddMessage($errorMessage, "errorMessage", LOG_LK);

				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $dataRequestArray['crmId'],
						"ENTITY_TYPE" => "DYNAMIC_128",
						"COMMENT" => "[b][color=red]".$errorMessageArray['title'] . " | " . $errorMessageArray['detail']
							."[/color][/b]"
					]
				]);
			}
		}

		return $jsonResponse;

	}
	protected function requestGET($url, $key = false, $internal = false) {
		$timeData = Logs\TimeData::start();
		if (defined("TOKEN_LK")) {
			$jsonResponse = \KPLab\Curl::get_LK(TOKEN_LK,$url,$this->jsonData,$this->CURLObjectData,$timeData,$this->point);
			return $jsonResponse;
		}
	}
}