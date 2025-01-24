<?php namespace KPLab;

use KPLab\Logs;
use \Bitrix\Main\Web\HttpClient;
use \Bitrix\Main\Web\Json;
use \KPLab\DiadocApi\DocumentAttachment;
use \KPLab\DiadocApi\MessageToPost;


define("LOG_DIADOC", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/diadoc.log");

class DiadocApi {

	protected $authToken;
	public $organizationList;
	public $organizations;
	public $myOrgId;
	public $myOrgBoxId;
	public $orgBoxId;
	public $countragentInn;
	public $message;
	public $docSmartId;

	public function __construct() {
		$this->clientId = "API-e525cc28-b4f0-4fa0-9367-ccc61147a620";
		$this->apiUrl = "https://diadoc-api.kontur.ru";
	}

	//region Авторизация с помощью логина и пароля
	public function Authenticate($login, $password)
	{
		//region Формирование http
		$http = new HttpClient(
			[
				'version' => HttpClient::HTTP_1_1
			]
		);

		$http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}", true);
		$http->setHeader("Content-Type", "application/json; charset=utf-8", true);
		$http->setHeader("Accept", "application/json", true);
		//endregion

		$queryUrl = "{$this->apiUrl}/v3/Authenticate?type=password";

		$postData = Json::encode(
			[
				'login' => $login,
				'password' => $password
			]
		);

		$authToken = $http->post($queryUrl, $postData);

		return $this->authToken = $authToken;
	}
	//endregion

	//region Получение идентификатора ящика отправителя
	public function getMyOrganizations()
	{
		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);

		$http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}, ddauth_token={$this->authToken}", true);
		$http->setHeader("Content-Type", "application/json; charset=utf-8", true);
		$http->setHeader("Accept", "application/json", true);
		//endregion

		$queryUrl = "{$this->apiUrl}/GetMyOrganizations";

		$this->organizationList = json_decode($http->get($queryUrl), true);

		$this->organizations = $this->organizationList["Organizations"];
		$this->myOrgId = $this->organizations[0]["OrgId"];
		$this->myOrgBoxId = $this->organizations[0]["Boxes"][0]["BoxId"];

		return $this;
	}

	public function getMyOrgId()
	{
		self::getMyOrganizations();
		return $this->myOrgId;
	}

	public function getMyBoxId() {

		self::getMyOrganizations();
		return $this -> myOrgBoxId;
	}
	//endregion

	//region Получение идентификатора ящика получателя
	public function getCounteragentInn() {
		return $this->countragentInn;
	}

	public function getCounteragents($signerInn, $myOrgId, $counteragentStatus = null, $afterIndexKey = null) {
		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);

		$http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}, ddauth_token={$this->authToken}", true);
		$http->setHeader("Content-Type", "application/json; charset=utf-8", true);
		$http->setHeader("Accept", "application/json", true);
		//endregion

		$queryData = "?myOrgId={$myOrgId}&query={$signerInn}"; //не забыть поставить
		$queryUrl = "{$this->apiUrl}/V2/GetCounteragents".$queryData;


		$this->counteragentList = json_decode($http->get($queryUrl), true);

		Logs\File::AddMessage($this->counteragentList,"counteragentList",LOG_DIADOC);

		$this->counteragents = $this->counteragentList["Counteragents"];
		$this->organization = $this->counteragents[0]["Organization"];
		$this->orgId = $this->organization["OrgId"];
		$this->orgBoxId = $this->organization["Boxes"][0]["BoxId"];

		Logs\File::AddMessage($this->organization,"organization",LOG_DIADOC);

		return $this;
	}

	public function getCounteragentOrgBoxId($signerInn, $myOrgId)
	{
		self ::getCounteragents($signerInn, $myOrgId, null, null);
		return $this -> orgBoxId;
	}
	//endregion

	//region Подготовка документа

	/**
	 * @return mixed
	 * Заполнение содержимого документа
	 */
	public function setContentDocumentInfoBySmartArray($filesArray) {
		$files = [];
		//Формируем пакет из Документа
		foreach ($filesArray as $file)
		{
			$fileInfo = \CFile::GetFileArray($file);
			$element["fileName"] = $fileInfo['ORIGINAL_NAME'];
			$element["filePath"] = 'https://crm.seller-capital.ru'.$fileInfo['SRC'];
			$element["filebase64"] = base64_encode(file_get_contents('https://crm.seller-capital.ru'.$fileInfo['SRC']));

			$documentAttachment = new DocumentAttachment("nonformalized", $element["filebase64"], false, $element["filePath"]);
			$documentAttachment->setMetadataItem("FileName","", true);
			$document = $documentAttachment->get();
			array_push($files, $document);
		}
		return $files;
	}

	/**
	 * @return mixed
	 * Заполнение информации о документе в MessageToPost
	 */
	public function getMessage($MessageToPost): mixed
    {
		$this -> message = (array)$MessageToPost;
		return $this -> message;
	}

	//endregion

	//region Отправка документа
	public function postMessage() {
		//region Формирование http
		$http = new HttpClient(
			[
				'version' => HttpClient::HTTP_1_1
			]
		);

		$http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}, ddauth_token={$this->authToken}", true);
		$http->setHeader("Content-Type", "application/json; charset=utf-8", true);
		$http->setHeader("Accept", "application/json", true);
		//endregion

		$queryUrl = "{$this->apiUrl}/V3/PostMessage";

		$postData = Json::encode($this->message);

		$response = json_decode($http->post($queryUrl, $postData), true);

		return $response;
	}
	//endregion

    //region Отправка приглашения к партнерским отношениям

    public function getAcquire($acquire) {
        $this->acquire = (array)$acquire;
        return $this -> acquire;
    }
    public function acquireCounteragent() {
        //region Формирование http
        $http = new HttpClient(
            [
                'version' => HttpClient::HTTP_1_1
            ]
        );

        $http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}, ddauth_token={$this->authToken}", true);
        $http->setHeader("Content-Type", "application/json; charset=utf-8", true);
        $http->setHeader("Accept", "application/json", true);
        //endregion

        $queryUrl = "{$this->apiUrl}/V3/AcquireCounteragent?myBoxId={$this->myOrgBoxId}";
        Logs\File::AddMessage($queryUrl,"queryUrl acquireCounteragent",LOG_DIADOC);

        $postData = Json::encode($this->acquire);
        Logs\File::AddMessage($postData,"postData acquireCounteragent",LOG_DIADOC);

        $response = json_decode($http->post($queryUrl, $postData), true);
        Logs\File::AddMessage($response,"response acquireCounteragent",LOG_DIADOC);

        return $response;
    }
    //endregion

	//region Проверка статуса
	public function getDocument($MessageId,$EntityId) {
		//region Формирование http
		$http = new HttpClient(
			[
				'version' => HttpClient::HTTP_1_1
			]
		);

		$http->setHeader("Authorization", "DiadocAuth ddauth_api_client_id={$this->clientId}, ddauth_token={$this->authToken}", true);
		$http->setHeader("Content-Type", "application/json; charset=utf-8", true);
		$http->setHeader("Accept", "application/json", true);
		//endregion

		$queryData = "?boxId={$this -> myOrgBoxId}&messageId={$MessageId}&entityId={$EntityId}";
		$queryUrl = "{$this->apiUrl}/V3/GetDocument".$queryData;

		$document = json_decode($http->get($queryUrl), true);
		//$document = $data;

		return $document;
	}

	public function getStatus($document)
	{
		$status = $document["DocflowStatus"];

		return $status;
	}
	public function getLastModificationTimestamp($document)
	{
		$lastModificationTimestampTicks = $document["LastModificationTimestampTicks"];

		return $lastModificationTimestampTicks;
	}
	//endregion

	public function start($docSmartId)
	{
		$MessageId = "";
		$this->docSmartId = $docSmartId;
		self::Authenticate("d.holkina@seller-capital.ru","Davit100498");
		$myOrgBoxId = self::getMyBoxId();
		$myOrgId = self::getMyOrgId();
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1060);
		$item = $factory->getItem($this->docSmartId);
		$itemId = $item->getId();
		$smart1060Data = $item->getData();
		$MessageId = $smart1060Data["UF_CRM_DIADOC_MESSAGEID"];

		if($MessageId == "") {
			$tempFile = $smart1060Data["UF_CRM_DOC"];
			$signerInn = $smart1060Data["UF_CRM_UF_SIGNER_INN"];

			$counteragentOrgBoxId = self::getCounteragentOrgBoxId($signerInn, $myOrgId);

			Logs\File::AddMessage($tempFile,"tempFilesArray",LOG_DIADOC);
			Logs\File::AddMessage($signerInn,"signerInn",LOG_DIADOC);
			Logs\File::AddMessage($counteragentOrgBoxId,"counteragentOrgBoxId",LOG_DIADOC);

			$MessageToPost = new \KPLab\DiadocApi\MessageToPost($myOrgBoxId, $counteragentOrgBoxId, $itemId);
			$MessageToPost->Get();

			Logs\File::AddMessage($MessageToPost,"MessageToPost After Get",LOG_DIADOC);


			//Формируем пакет из Документа
			$fileInfo = \CFile::GetFileArray($tempFile[0]);
			Logs\File::AddMessage($fileInfo,"fileInfo",LOG_DIADOC);
			if ($fileInfo)
			{
				// Полный путь к файлу
				$filePath = $_SERVER["DOCUMENT_ROOT"] . $fileInfo["SRC"];
				$fileName = $fileInfo['ORIGINAL_NAME'];
				$fileId = $fileInfo['ID'];

				// Проверка существования файла
				if (file_exists($filePath))
				{
					Logs\File::AddMessage($filePath,"filePath",LOG_DIADOC);
					// Чтение содержимого файла
					$fileContents = file_get_contents($filePath);

					// Преобразование содержимого в base64
					$base64File = base64_encode($fileContents);

					$documentAttachment = new \KPLab\DiadocApi\DocumentAttachment("nonformalized", $base64File, false, $fileName, $fileId);
					$documentAttachment->setMetadataItem("FileName","", true);
					$document = $documentAttachment->get();

					$MessageToPost->Add($document);
					//Logs\File::AddMessage($MessageToPost,"MessageToPost after Add",LOG_DIADOC);

					$message = self::getMessage($MessageToPost);
					Logs\File::AddMessage($message,"message",LOG_DIADOC);

					$response = $this->postMessage();
                    $MessageId = $response["MessageId"];
                    if($MessageId !== "") {
                        $messageTimeline = "Документы были переданы контрагенту в Диадок!";
                        \CRest ::call('crm.timeline.comment.add', [
                            'fields' => [
                                "ENTITY_ID" => $itemId,
                                "ENTITY_TYPE" => "DYNAMIC_1060",
                                "COMMENT" => "[b]{$messageTimeline}[/b]"
                            ]
                        ]);
                    }

					$item -> set('UF_CRM_DIADOC_MESSAGEID', $response["MessageId"]);

					$Entities = $response["Entities"];
					foreach ($Entities as $Entity)
					{
						if($Entity["EntityType"] == "Attachment") {
							$EntityId = $Entity["EntityId"];
							$item -> set('UF_CRM_DIADOC_ATTACHMENT_ENTITY_ID', $EntityId);
						}
					}
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

					if($operationResult -> isSuccess()) {
						$message = "Данные отправились!";
						\CRest ::call('crm.timeline.comment.add', [
							'fields' => [
								"ENTITY_ID" => $itemId,
								"ENTITY_TYPE" => "DYNAMIC_1060",
								"COMMENT" => "[b]{$message}[/b]"
							]
						]);
					}

					return $response;

				}
			}
		}

		return false;
	}

    public function startAcquireCounteragent($docSmartId) {
        $this->acquireTaskId = null;
        $this->docSmartId = $docSmartId;

        self::Authenticate("d.holkina@seller-capital.ru","Davit100498");

        $myOrgBoxId = self::getMyBoxId();
        $myOrgId = self::getMyOrgId();
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1060);
        $item = $factory->getItem($this->docSmartId);
        $smart1060Data = $item->getData();
        $signerInn = $smart1060Data["UF_CRM_UF_SIGNER_INN"];
        $acquire = [
            'Inn' => $signerInn,
            'MessageToCounteragent' => 'Примите приглашение к сотрудничеству'
        ];
        $this->getAcquire($acquire);
        $response = $this->acquireCounteragent();
        Logs\File::AddMessage($response,"response acquireCounteragent",LOG_DIADOC);

        return $response["TaskId"];
    }

	public function getStatusDocument($docSmartId)
	{
		$this->successStatus = false;
		$this->docSmartId = $docSmartId;
		self::Authenticate("d.holkina@seller-capital.ru","Davit100498");

		$myOrgBoxId = self::getMyBoxId();
		$myOrgId = self::getMyOrgId();

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1060);
		$item = $factory->getItem($this->docSmartId);
        $itemId = $item -> getId();
		$smart1060Data = $item->getData();
		$statusEdoID = $smart1060Data['UF_CRM_STATUS_DOC'];

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $statusEdoID,
		));

		if ($arEnum = $rsEnum->Fetch()) {
			$statusEdoValue = $arEnum['XML_ID'];
		}

		if($statusEdoValue == "edo_Success") {
			$item->setStageId('DT1060_241:SUCCESS');
			$messageTimeline = "Успешно подписано контрагентом!";
            \CRest ::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $itemId,
                    "ENTITY_TYPE" => "DYNAMIC_1060",
                    "COMMENT" => "[b]{$messageTimeline}[/b]"
                ]
            ]);

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
			return true;
		}
		else {
			$MessageId = $smart1060Data["UF_CRM_DIADOC_MESSAGEID"];
			$EntityId = $smart1060Data["UF_CRM_DIADOC_ATTACHMENT_ENTITY_ID"];

			$document = self::getDocument($MessageId,$EntityId);

			$lastModificationTimestampTicks = self::getLastModificationTimestamp($document);
			// Константы
			$ticksPerSecond = 10000000; // 1 тик = 100 наносекунд = 10^7 тиков в секунду
			$epochTicks = 621355968000000000; // Количество тиков от 01.01.0001 до 01.01.1970

			// Преобразование тиков в количество секунд
			$seconds = ($lastModificationTimestampTicks - $epochTicks) / $ticksPerSecond;

			// Преобразование в формат даты и времени
			$dateTime = \Bitrix\Main\Type\DateTime::createFromTimestamp($seconds);

			$dateTime->setDefaultTimeZone();
			$dateTime->toString();

			// Создание объекта DateTimeImmutable с указанием временной зоны UTC
			//$date = new \DateTimeImmutable($dateTime, );

			// Преобразование в формат ISO 8601
			$lastModificationDateTimeWithTimeZone = $dateTime->format('Y-m-d\TH:i:sP');

			//$dateTime->setDefaultTimeZone();

			// Форматирование даты и времени date("d.m.Y H:i:s")
			$lastModificationDateTime = $dateTime->format('d.m.Y H:i:s');

			$item -> set('UF_CRM_EDO_LASTMODIFICATION_DATETIME', $dateTime);

			$message = "[i]Время изменения статуса документа зафиксировано[/i]";

			$status = self::getStatus($document);

			$statusPrimaryStatusSeverity = $status["PrimaryStatus"]["Severity"];

			if($statusPrimaryStatusSeverity == "Info") {
				$rsEnum = \CUserFieldEnum::GetList(array(), array(
					"XML_ID" => "edo_Info",
				));
				if ($arEnum = $rsEnum->Fetch()) {
					$statusEdoID = $arEnum['ID'];
				}
				$item -> set('UF_CRM_STATUS_DOC', $statusEdoID);

				$message = "Успешно отправлено контрагенту на подпись!";
			}

			if($statusPrimaryStatusSeverity == "Success") {
				$this->successStatus = true;
				$rsEnum = \CUserFieldEnum::GetList(array(), array(
					"XML_ID" => "edo_Success",
				));
				if ($arEnum = $rsEnum->Fetch()) {
					$statusEdoID = $arEnum['ID'];
				}
				$item -> set('UF_CRM_STATUS_DOC', $statusEdoID);
				$item->setStageId('DT1060_241:SUCCESS');
				$message = "Успешно подписано контрагентом!";
			}

            \CRest ::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item -> getId(),
                    "ENTITY_TYPE" => "DYNAMIC_1060",
                    "COMMENT" => "[b]{$message}[/b]"
                ]
            ]);

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

			return $status;
		}
	}
}