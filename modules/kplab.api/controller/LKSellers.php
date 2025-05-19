<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_LKACCOUNT_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/LKAccountController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");

class LKSellers extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
		];
	}

	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		return parent ::prepareParams();
	}
	public function setAccountStateAction(array $params = [])
	{
		$timeData = Logs\TimeData ::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();
		$serverArray = $server -> toArray();
		$serverName = $serverArray['SERVER_NAME'];

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$requestArray = json_decode($request -> getInput(), true);
		Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_LKACCOUNT_CONTROLLER);

		if ($requestArray == NULL)
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}

		if (empty($requestArray['inn']))
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error('Этот запрос не поддерживается. Пустой `inn`', "invalid_request"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		} 
		else
		{
			$authorization = $server -> get('REMOTE_USER');
			$token = str_replace('BitrixAuth ', '', $authorization);

			Loader ::includeModule('iblock');

			$inn = $requestArray['inn'];
			$accountState = $requestArray['state'];
			$crmId = $requestArray['crmId'];

			//region findStateLK DB
			global $DB;
			$itemSQL = "SELECT * FROM b_user_field_enum  WHERE XML_ID='{$accountState}' ORDER BY ID ASC;";
			$resItemsQuery = $DB->query($itemSQL);
			while($resItem = $resItemsQuery->Fetch()) {
				$UF_CRM_LKSC_STATUS = (int) $resItem['ID'];
				Logs\File ::AddMessage($UF_CRM_LKSC_STATUS, "UF_CRM_LKSC_STATUS", LOG_API_SYNC_LKACCOUNT_CONTROLLER);
			}
			//endregion findStateLK DB

			/* state[XML_ID]:

			// NewAccount
			// FillingBanks
			// Prescoring
			// docsProcessing
			// dataProcessingAgreement
			// scoring
			// resultLimit
			// guarantorsAdding
			// guarantorsAgreement
			// guarantorsScoring
			// firstTranche
			// fullCabinet
			// blockedCabinet
			// authorizedCabinet
			// fillingShops
			*
			*/

			$entityTypeId = 128;
			$factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);

			//region searchItemSmartLK
			if(!isset($crmId)) {
				$requisiteList = \CRest::call(
					"crm.requisite.list",
					array("filter" => ["RQ_INN" => $inn], "select" => ["ENTITY_ID", "ENTITY_TYPE_ID"])
				)['result'][0];

				$params = [
					'filter' => [
						'=COMPANY_ID' => $requisiteList["ENTITY_ID"],
						'CATEGORY_ID' => 42
					]
				];
				$itemsLk = $factory -> getItems($params);

				foreach ($itemsLk as $element)
				{
					$lastElementLKId = $element->getId();

				}

				$itemLk = $factory -> getItem($lastElementLKId);
			}
			else {
				$itemLk = $factory -> getItem($crmId);
			}
			//endregion


            $itemLk->set("UF_CRM_LKSC_STATUS", $UF_CRM_LKSC_STATUS);

			if($accountState == "AgreementSignProcessing") {
				$itemLk->setStageId("DT128_42:UC_0NAIJ4"); //Получение Согласия
			}

            if($accountState == "GuarantorsAdding") {
                $params = [
                    'filter' => [
                        '=UF_CRM_CRMID' => $crmId,
                        'CATEGORY_ID' => 226 //ID Воронки ЛК
                    ]
                ];
                $itemsScoringTransh = $factory -> getItems($params);

                foreach ($itemsScoringTransh as $element)
                {
                    $lastElementLKId = $element->getId();
                }

                $itemScoringTransh = $factory -> getItem($lastElementLKId);
                $itemScoringTransh->set("UF_CRM_LKSC_STATUS", $UF_CRM_LKSC_STATUS);
            }
            
			if($accountState == "GuarantorsAgreement") {
				$params = [
					'filter' => [
						'=UF_CRM_CRMID' => $crmId,
						'CATEGORY_ID' => 226 //ID Воронки ЛК
					]
				];
				$itemsScoringTransh = $factory -> getItems($params);

				foreach ($itemsScoringTransh as $element)
				{
					$lastElementLKId = $element->getId();
				}

				$itemScoringTransh = $factory -> getItem($lastElementLKId);
				$itemScoringTransh->setStageId("DT128_226:UC_4P8Q15"); //Добавление поручителей
                $itemScoringTransh->set("UF_CRM_LKSC_STATUS", $UF_CRM_LKSC_STATUS);
			}

			if($accountState == "SignDocsBeforeContract") {
				$params = [
					'filter' => [
						'=UF_CRM_CRMID' => $crmId,
						'CATEGORY_ID' => 226
					]
				];
				$itemsScoringTransh = $factory -> getItems($params);

				foreach ($itemsScoringTransh as $element)
				{
					$lastElementLKId = $element->getId();
				}

				$itemScoringTransh = $factory -> getItem($lastElementLKId);
                $itemScoringTransh->set("UF_CRM_LKSC_STATUS", $UF_CRM_LKSC_STATUS);
				//$itemScoringTransh->setStageId("DT128_226:UC_7ZID5F"); //Подписание документов
			}



			if($itemScoringTransh) {
				$operationScoringTransh = $factory->getUpdateOperation($itemScoringTransh);
                $operationScoringTransh->disableAllChecks();
				//$operationScoringTransh->disableCheckAccess()->disableCheckFields()->enableSaveToHistory()->enableBizProc()->enableAutomation()->enableAfterSaveActions()->enableSaveToHistory()->enableBeforeSaveActions();
				$operationResult = $operationScoringTransh->launch();
			}
			if($itemLk) {
				$operation = $factory->getUpdateOperation($itemLk);
                $operation->disableAllChecks();
                //$operation->disableCheckAccess()->disableCheckFields()->enableSaveToHistory()->enableBizProc()->enableAutomation()->enableSaveToHistory();
				$operationResult = $operation->launch();
			}

			// Проверка на ошибки
			if ($operationResult->isSuccess()) {
				return "Элемент успешно обновлен";
			} else {
				return "Произошла ошибка: " . implode(', ', $operationResult->getErrorMessages());
			}
		}
	}
	public function wtfAction(array $params = [])
	{
		$timeData = Logs\TimeData ::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();
		$serverArray = $server -> toArray();
		$serverName = $serverArray['SERVER_NAME'];

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$requestArray = json_decode($request -> getInput(), true);
		Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_LKACCOUNT_CONTROLLER);
		if ($requestArray == NULL)
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}

		if (empty($requestArray['crmId']))
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error('Этот запрос не поддерживается. Пустой `crmId`', "invalid_request"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		else
		{
			$authorization = $server -> get('REMOTE_USER');
			$token = str_replace('BitrixAuth ', '', $authorization);

			Loader ::includeModule('iblock');

			$type = $requestArray['type'];
			$crmId = $requestArray['crmId'];

			$entityTypeId = 128;
			$factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);

			$itemLk = $factory -> getItem($crmId);

			$itemLk->setStageId("DT128_42:UC_SPUBRR"); //Брошенный ЛК

			if($itemLk) {
				$operation = $factory->getUpdateOperation($itemLk);
				$operation->disableCheckAccess()->enableSaveToHistory();
				$operationResult = $operation->launch();
			}

			// Проверка на ошибки
			if ($operationResult->isSuccess()) {
				return "Элемент успешно обновлен";
			} else {
				return "Произошла ошибка: " . implode(', ', $operationResult->getErrorMessages());
			}


		}
	}
	public function sendSMSAction(array $params = [])
	{
		$timeData = Logs\TimeData ::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$headers = $request->getHeaders()->toArray();
		$server = $context -> getServer();
		$serverArray = $server -> toArray();
		$serverName = $serverArray['SERVER_NAME'];
		$url = $server['SCRIPT_URI'];

		$objectData['METHOD'] = $server['REQUEST_METHOD'];
		$point = "SE_BX";
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$requestArray = json_decode($request -> getInput(), true);
		Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_LKACCOUNT_CONTROLLER);

		if ($requestArray == NULL)
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
			$this -> addError(new Error($errorMessage, "invalid_json"));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			return new EventResult(EventResult::ERROR, null, null, $this);
		}

		if (empty($requestArray['crmId']))
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `crmId`";
			$this -> addError(new Error($errorMessage, "invalid_request"));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		if (empty($requestArray['message']))
		{
			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `message`";
			$this -> addError(new Error($errorMessage, "invalid_request"));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		else
		{
			$errorMessage = "";
			$authorization = $server -> get('REMOTE_USER');
			$token = str_replace('BitrixAuth ', '', $authorization);

			Loader ::includeModule('iblock');

			$message = $requestArray['message'];
			$crmId = $requestArray['crmId'];
			$entityTypeId = 128;
			$documentId = "DYNAMIC_".$entityTypeId."_".$crmId;
			$arWorkflowParameters = array("message" => $message);
			$arErrorsTmp = array();
			$wfId = \CBPDocument::StartWorkflow(
				3430,
				array("crm", "Bitrix\Crm\Integration\BizProc\Document\Dynamic", $documentId),
				array_merge($arWorkflowParameters, array("TargetUser" => "user_".intval($GLOBALS["USER"]->GetID()))),
				$arErrorsTmp
			);

			if (count($arErrorsTmp) > 0)
			{
				foreach ($arErrorsTmp as $e)
					$errorMessage .= "[".$e["code"]."] ".$e["message"]."";
			}

			if ($errorMessage !== "")
			{
				Context ::getCurrent() -> getResponse() -> setStatus(500);
				$this -> addError(new Error($errorMessage, "invalid_operation"));
				$jsonRes['success'] = null;
				$jsonRes['error'] = $errorMessage;
				return new EventResult(EventResult::ERROR, null, null, $this);
			} else {
				$objectData['ITEM_TITLE'] = "SMS сообщение для $crmId";
				$jsonRes['success'] = "SMS сообщение успешно отправилось";
				$jsonRes['error'] = "";
			}

			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			return $jsonRes;
		}
	}
}