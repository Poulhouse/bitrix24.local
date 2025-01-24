<?php namespace KPLab;

use KPLab\SbisApi\DocumentAttachment;
use KPLab\Logs;
use \Bitrix\Main\Web\HttpClient;
use \Bitrix\Main\Web\Json;


define("LOG_SBIS", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/sbis.log");

class SbisApi {

	private $login;
	private $pass;
	private $account;
	private $sessionId;
	private $counteragent;
	private $sgn;
	private $pathSgn;
	public $docSmartId;
	public $documentId;

	public function __construct() {
		$this->apiAuthUrl = "https://online.sbis.ru/auth/service/";
		$this->apiUrl = "https://online.sbis.ru/service/?srv=1";
		$this->login = "a.boger@seller-capital.ru";
		$this->pass = "Admin123!!@45*";
		$this->account = "4316181";

		$this->sgn = "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";
		$this->pathSgn = "BB902233A18F0F1BF107401C5A81BF5D8D9B97A0";

		$this->myOrganization = [
			"СвЮЛ" => [
				"ИНН" => "6685079610",
				"КПП" => "668501001"
			]
		];
	}

	//region Авторизация с помощью логина и пароля
	public function Authenticate()
	{
		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"Content-Type" => "application/json-rpc;charset=utf-8"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiAuthUrl;

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.Аутентифицировать',
				'params' => [
					"Параметр" => [
						"Логин" => $this->login,
						"Пароль" => $this->pass,
						"НомерАккаунта" => $this->account,
					]
				],
				"id" => null
			]
		);

		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		$this->sessionId = $responseArray["result"];

		return $this->sessionId;
	}
	//endregion

	//region Получение информации о получателе
	public function getCounteragent($filter) {

		$this->counteragent = $filter;

		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"X-SBISSessionID" => $this->sessionId,
			"Content-Type" => "application/json; charset=utf-8",
			"Accept" => "*/*, application/json-rpcD"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiUrl;

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.ИнформацияОКонтрагенте',
				'params' => [
					"Участник" => $this->counteragent
				],
				"id" => null
			]
		);
		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		$this->counteragentId = $responseArray["result"]["Идентификатор"];
		return $this->counteragentId;
	}
	private function getTypeCounteragent() {
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1034);
		$item = $factory->getItem($this->docSmartId);
		$smart1034Data = $item->getData();
		$sellerId = $smart1034Data['COMPANY'];
	}
	//endregion

	//region Создание вложения
	public function addAttachment($documentId) {
		$this -> attachments = null;
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1034);
		$item = $factory->getItem($this->docSmartId);
		$smart1034Data = $item->getData();
		$tempFile = $smart1034Data["UF_CRM_DOC"];
		$fileInfo = \CFile::GetFileArray($tempFile);
		if ($fileInfo)
		{
			// Полный путь к файлу
			$filePath = $_SERVER["DOCUMENT_ROOT"] . $fileInfo["SRC"];
			$fileName = $fileInfo['ORIGINAL_NAME'];
			$fileId = $fileInfo['ID'];

			// Проверка существования файла
			if (file_exists($filePath))
			{
				// Чтение содержимого файла
				$fileContents = file_get_contents($filePath);

				// Преобразование содержимого в base64
				$base64File = base64_encode($fileContents);

				$this -> attachments = [
					[
						"Файл" => [
							"Имя" => $fileName,
							"ДвоичныеДанные" => $base64File
						]
					]
				];
			}
		}

		if(is_null($this -> attachments)) {
			return false;
		}

		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"X-SBISSessionID" => $this->sessionId,
			"Content-Type" => "application/json; charset=utf-8",
			"Accept" => "*/*, application/json-rpcD"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiUrl;

		$ar_Document = [
			"Идентификатор" => $documentId,
			"Этап" => [
				"Название" => "Отправка",
				"Действие" => [
	               ["Название" => "Отправить"]
                ]
			],
			"Вложение" => $this->attachments
		];

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.ЗаписатьВложение',
				'params' => [
					"Документ" => $ar_Document
				],
				"id" => null
			]
		);
		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		$this->attachmentsArray = $responseArray["result"];

		return $this->attachmentsArray;
	}
	//endregion

	//region Выполнить действие
	public function addAction($documentId, $attachmentId, $attachmentLink, $attachmentTitle) {
		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"X-SBISSessionID" => $this->sessionId,
			"Content-Type" => "application/json; charset=utf-8",
			"Accept" => "*/*, application/json-rpcD"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiUrl;

		$ar_Document = [
			"Идентификатор" => $documentId,
			"Этап" => [
				"Вложение" => [
					"Идентификатор" =>$attachmentId,
					"Подпись" => [
						[
							"Файл" => [
								"ДвоичныеДанные" => $this->sgn,
							]
						]
					]
				],
				"Действие" => [
                    [
                        "Название" => "Отправить",
	                    "Комментарий" => "",
	                    "Сертификат" => [
	                    	[
			                    "Алгоритм" => "ГОСТ Р 34.10-2012 256",
			                    "ДвоичныеДанные" => "",
			                    "ДействителенПо" => "04.12.2037 09.04.05",
			                    "ДействителенС" => "04.10.2023 09.04.05",
			                    "Должность" => "ДИРЕКТОР",
			                    "ИНН" => "6685079610",
			                    "Издатель" => '7702235133, 1037700013020, Центральный банк Российской Федерации, Банк России, "ул. Неглинная, д. 12", г. Москва, 77 г. Москва, RU',
			                    "Квалифицированный" => "Да",
			                    "Ключ" => [
			                    	"Активирован" => "Да",
				                    "СпособАктивации" => "",
				                    "Тип" => "Клиентский"
			                    ],
			                    "КодСтраны" => "RU",
			                    "Название" => 'ООО МКК "СОДЕЙСТВИЕ XXI"',
			                    "ОГРНИП" => "",
			                    "Отпечаток" => "BB902233A18F0F1BF107401C5A81BF5D8D9B97A0",
			                    "СерийныйНомер" => "40601D0026BD0E6347A9B8C8651D0055",
			                    "ФИО" => "БОГЕР АНДРЕЙ ИВАНОВИЧ"
		                    ]
	                    ],
	                ]
                ],
				"Название" => "Отправка",
			],
		];

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.ВыполнитьДействие',
				'params' => [
					"Документ" => $ar_Document
				],
				"id" => null
			]
		);
		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		//$this->attachmentsArray = $responseArray["result"]["Вложение"];

		return $responseArray;
	}
	//endregion

	//region Создание документа
	public function createDocument() {

		$this->dateDocument = date("d.m.Y");//"20.07.2024";
		$date = new \DateTime(date("d.m.Y H:i:s"));
		$this->numberDocument = (string) $date->getTimestamp();
		$this->typeDocument = "КоррИсх";
		$this->Comment = "Здесь обычно указывают примечание";

		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"X-SBISSessionID" => $this->sessionId,
			"Content-Type" => "application/json; charset=utf-8",
			"Accept" => "*/*, application/json-rpcD"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiUrl;

		$ar_Document = [
			"Дата" => $this->dateDocument,
			"Номер" => $this->numberDocument,
			"Контрагент" => $this->counteragent,
			"НашаОрганизация" => $this->myOrganization,
			"Примечание" => $this->Comment,
			"Тип" => $this->typeDocument
		];

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.ЗаписатьДокумент',
				'params' => [
					"Документ" => $ar_Document
				],
				"id" => null
			]
		);
		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		$this->documentId = $responseArray["result"]["Идентификатор"];
		$this->documentDateTimeCreated = $responseArray["result"]["ДатаВремяСоздания"];

		return $this->documentId;
	}
	//endregion

	//region Подготовить действие
	public function prepareAction($attachment) {

		$this->dateDocument = date("d.m.Y");//"20.07.2024";
		$date = new \DateTime(date("d.m.Y H:i:s"));
		$this->numberDocument = (string) $date->getTimestamp();
		$this->typeDocument = "КоррИсх";
		$this->Comment = "Здесь обычно указывают примечание";

		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"X-LogEntireTask" => "True",
			"X-SBISSessionID" => $this->sessionId,
			"Content-Type" => "application/json; charset=utf-8",
			"Accept" => "*/*, application/json-rpcD"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiUrl;

		$ar_Document = [
			"Идентификатор" => $this->documentId,
			"Отпечаток" => "BB902233A18F0F1BF107401C5A81BF5D8D9B97A0",
		];

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.ПодготовитьДействие',
				'params' => [
					"Документ" => [
						"Идентификатор" => $this->documentId,
						"Этап" => [
							"Действие" => [
								"Название" => "Отправить",
								"Сертификат" => [
									[
										"Алгоритм" => "ГОСТ Р 34.10-2012 256",
										"ДвоичныеДанные" => "",
										"ДействителенПо" => "04.12.2037 09.04.05",
										"ДействителенС" => "04.10.2023 09.04.05",
										"Должность" => "ДИРЕКТОР",
										"ИНН" => "6685079610",
										"Издатель" => '7702235133, 1037700013020, Центральный банк Российской Федерации, Банк России, "ул. Неглинная, д. 12", г. Москва, 77 г. Москва, RU',
										"Квалифицированный" => "Да",
										"Ключ" => [
											"Активирован" => "Да",
											"СпособАктивации" => "",
											"Тип" => "Клиентский"
										],
										"КодСтраны" => "RU",
										"Название" => 'ООО МКК "СОДЕЙСТВИЕ XXI"',
										"ОГРНИП" => "",
										"Отпечаток" => "BB902233A18F0F1BF107401C5A81BF5D8D9B97A0",
										"СерийныйНомер" => "40601D0026BD0E6347A9B8C8651D0055",
										"ФИО" => "БОГЕР АНДРЕЙ ИВАНОВИЧ"
									]
								],
							],
							"Название" => "Отправка"
						],
						"Вложение" => [
							['Идентификатор' => $attachment['Идентификатор']]
						]
					]
				],
				"id" => null
			]
		);
		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);

		return $responseArray["result"];
	}
	//endregion

	public function start($docSmartId,$signerId)
	{
		$documentId = "";
		$this->docSmartId = $docSmartId;
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1034);
		$item = $factory->getItem($this->docSmartId);
		$smart1034Data = $item->getData();
		$documentId = $smart1034Data["UF_CRM_SBIS_DOCUMENTID"];

		if($documentId == "")
		{
			self ::Authenticate();

			$rsEnum = \CUserFieldEnum::GetList(array(), array(
				"ID" => $smart1034Data["UF_SIGNER_TYPE"]
			));
			if ($arEnum = $rsEnum->Fetch()) {
				$this->counteragentType = $arEnum['XML_ID'];
			}

			if($this->counteragentType == "FL") {
				$PRESET_ID = 3;
			}
			if($this->counteragentType == "UL") {
				$PRESET_ID = 1;
			}
			if($this->counteragentType == "IP") {
				$PRESET_ID = 2;
			}

			$INN = $smart1034Data["UF_SIGNER_INN"];
			$KPP = $smart1034Data["UF_SIGNER_KPP"];

			$requisite = new \Bitrix\Crm\EntityRequisite();

			if($PRESET_ID == 2 || $PRESET_ID == 3) {
				// Сначала проверяем PRESET_ID = 3
				$presetIds = [3, 2];
				foreach ($presetIds as $presetId) {
					$res = $requisite->getList([
						'filter' => [
							'ENTITY_ID' => $signerId,
							'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
							'PRESET_ID' => $presetId
						]
					]);
					if ($requisiteFields = $res->fetch()) {
						// Если найден хотя бы один результат, выходим из цикла
						break;
					}
				}
				// Если найдены реквизиты, обрабатываем их
				if ($requisiteFields) {
					$RQ_LAST_NAME = $requisiteFields['RQ_LAST_NAME'];
					$RQ_FIRST_NAME = $requisiteFields['RQ_FIRST_NAME'];
				} else {
					// Обработка случая, когда реквизиты не найдены
					$RQ_LAST_NAME = null;
					$RQ_FIRST_NAME = null;
				}
			}

			//////////Тестовые реквизиты ЮЛ
			$INN = "6674154590";
			$KPP = "661245001";
			//////////

			//ИП
			if($PRESET_ID == 2) {
				$filter = [
					"СвФЛ" => [
						"ИНН" => $INN,
						"Имя" => $RQ_FIRST_NAME,
						"Фамилия" => $RQ_LAST_NAME
					]
				];
			}
			//ЮЛ
			if($PRESET_ID == 1) {

				$filter = [
					"СвЮЛ" => [
						"ИНН" => $INN,
						"КПП" => $KPP
					]
				];
			}
			//ФЛ
			if($PRESET_ID == 3) {
				$filter = [
					"СвФЛ" => [
						"ИНН" => $INN,
						"Имя" => $RQ_FIRST_NAME,
						"Фамилия" => $RQ_LAST_NAME,
						"ЧастноеЛицо" => "Да"
					]
				];
			}

			self ::getCounteragent($filter);
			$documentId = self ::createDocument();
			$item -> set('UF_CRM_SBIS_DOCUMENTID', $documentId);

			$dateTime = \Bitrix\Main\Type\DateTime::createFromUserTime($this->documentDateTimeCreated);
			$dateTime->setDefaultTimeZone();
			$dateTime->toString();
			$dateTime->format('d.m.Y H:i:s');
			$item -> set('UF_CRM_EDO_LASTMODIFICATION_DATETIME', $dateTime);

			$attachments = self ::addAttachment($this -> documentId);
			$attachmentId = $attachments[0]['Идентификатор'];
			$item -> set('UF_CRM_SBIS_ATTACHMENT_ID', $attachmentId);
			$action = self ::addAction($attachmentId);


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
				$message = "Документ успешно отправлен в СБИС!";
				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_1034",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}
		}
		return $this;
	}
	public function exit()
	{
		//region Формирование http
		$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
		$headers = [
			"Content-Type" => "application/json-rpc;charset=utf-8"
		];
		$http->setHeaders($headers);
		//endregion

		$queryUrl = $this->apiAuthUrl;

		$postData = Json::encode(
			[
				'jsonrpc' => '2.0',
				'method' => 'СБИС.Выход',
				'params' => [],
				"id" => null
			]
		);

		$responseJson = $http->post($queryUrl, $postData);
		$responseArray = json_decode($responseJson, true);
		$this->exitResponse = $responseArray["result"];

		return $this->exitResponse;
	}

	//region Получение статуса документа
	public function getStatusDocument($docSmartId) {
		$this->Authenticate();

		$this->successStatus = false;
		$this->docSmartId = $docSmartId;

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1034);
		$item = $factory->getItem($this->docSmartId);
		$smart1034Data = $item->getData();
		$statusEdoID = $smart1034Data['UF_CRM_STATUS_DOC'];
		$this->documentId = $smart1034Data['UF_CRM_SBIS_DOCUMENTID'];

		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $statusEdoID,
		));

		if ($arEnum = $rsEnum->Fetch()) {
			$statusEdoValue = $arEnum['XML_ID'];
		}

		$this->statusEdoValue = $statusEdoValue;

		if($statusEdoValue == "edo_Success") {
			$item->setStageId('DT1034_214:SUCCESS');
			$message = "Успешно подписано контрагентом!";

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

				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_1034",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}
			return true;
		}
		else {
			//region Формирование http
			$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);
			$headers = [
				"X-LogEntireTask" => "True",
				"X-SBISSessionID" => $this->sessionId,
				"Content-Type" => "application/json; charset=utf-8",
				"Accept" => "*/*, application/json-rpcD"
			];
			$http->setHeaders($headers);
			//endregion

			$queryUrl = $this->apiUrl;

			$ar_Document = [
				"Идентификатор" => $this->documentId,
			];

			$postData = Json::encode(
				[
					'jsonrpc' => '2.0',
					'method' => 'СБИС.ПрочитатьДокумент',
					'params' => [
						"Документ" => $ar_Document
					],
					"id" => null
				]
			);
			$responseJson = $http->post($queryUrl, $postData);
			$responseArray = json_decode($responseJson, true);

			$this->responseArray = $responseArray;

			$this->state = $responseArray["result"]["Этап"][0];

			if($this->state['Название'] == "Отправка") {
				$rsEnum = \CUserFieldEnum::GetList(array(), array(
					"XML_ID" => "edo_New",
				));
				if ($arEnum = $rsEnum->Fetch()) {
					$statusEdoID = $arEnum['ID'];
				}
				$item -> set('UF_CRM_STATUS_DOC', $statusEdoID);

				$message = "Ожидается отправки контрагенту на подпись!";
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

				\CRest ::call('crm.timeline.comment.add', [
					'fields' => [
						"ENTITY_ID" => $item -> getId(),
						"ENTITY_TYPE" => "DYNAMIC_1034",
						"COMMENT" => "[b]{$message}[/b]"
					]
				]);
			}


			return $this->state;

		}
		return false;
	}
	//endregion Получение статуса документа
}