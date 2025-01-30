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

define("LOG_API_LOANS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/LoansController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");


class LoansSellers extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\V2\Controller\ActionFilter\Authentication(),
		];
	}
	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		return parent::prepareParams();
	}
	public function getLoansSellersAction(array $params = []) {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$point = "BX_";
		$QUERY_STRING = $server['QUERY_STRING'];
		$url = $server['SCRIPT_URI']."?".$QUERY_STRING;
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$QUERY_STRING = $server['QUERY_STRING'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders()->toArray();
		$serverArray = $server->toArray();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}
		//Logs\File::AddMessage($serverArray,"serverArray",LOG_API_SYNC_CONTROLLER);
		$recieve = json_decode($request->getInput(),true);

		parse_str($QUERY_STRING, $queryArray);

		if(isset($queryArray['qty'])) {
			$qty = $queryArray['qty'];
		} else {
			$qty = 50;
		}
		if(isset($queryArray['page'])) {
			$offset = ($queryArray['page'] - 1) * $qty;
		} else {
			$offset = 0;
		}

		$objectData['ITEM_TITLE'] = "Запрос займов селлеров: {$startDate} - {$endDate}";

		if(strtotime($startDate) > strtotime($endDate)) {
			$errorMessage = "400 Bad Request | `endDate` must more `startDate`!";
			$this->addError(new Error($errorMessage, 400));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}
		if($qty > 50) {
			$errorMessage = "400 Bad Request | `qty` not must more 50!";
			$this->addError(new Error($errorMessage, 400));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}

		global $DB;
		$arLeads = [];
		$ENTITY_TYPE_ID = 134;

		$strCountItemsSQL = "SELECT COUNT(*) FROM b_crm_dynamic_items_134 ORDER BY b_crm_dynamic_items_134.ID ASC;";
		$strItemSQL = "SELECT * FROM b_crm_dynamic_items_134 ORDER BY b_crm_dynamic_items_134.ID ASC LIMIT ".$qty." OFFSET ". $offset .";";

		$resCountItemsQuery = $DB->query($strCountItemsSQL);
		while($resCountItems = $resCountItemsQuery->Fetch()) {
			$totalItems = $resCountItems['COUNT(*)'];
		}
		$resItemQuery = $DB->query($strItemSQL);

		$arLeads['object'] = (string) "loanSeller";

		while($resItem = $resItemQuery->Fetch()) {
			$idItem = (integer) $resItem['ID'];
			$titleItem = $resItem['TITLE'] != "" ? (string) $resItem['TITLE'] : null;
			$organizationItem = $resItem['UF_CRM_56_1686311449'] != "" ? (int) $resItem['UF_CRM_56_1686311449'] : null;
			$dateItem = date('Y-m-d\TH:i:s.msp', strtotime($resItem['CREATED_TIME']));
			$updateDateItem = date('Y-m-d\TH:i:s.msp', strtotime($resItem['UPDATED_TIME']));
			$totalDeptItem = $resItem['UF_CRM_56_1684744827969'] != "" ? (float) $resItem['UF_CRM_56_1684744827969'] : null;
			$totalDeptMKKItem = $resItem['UF_CRM_56_1704956602'] != "" ? (float) $resItem['UF_CRM_56_1704956602']
				: null;
			$totalDeptKPKItem = $resItem['UF_CRM_56_1704956681'] != "" ? (float) $resItem['UF_CRM_56_1704956681']
				: null;
			$limitCreditLineItem = $resItem['UF_CRM_56_1684744846487'] != "" ? (float) $resItem['UF_CRM_56_1684744846487'] : null;

			//region $stageItem
			$stageItemID = $resItem['STAGE_ID'];
			$strStageItemSQL = "SELECT * FROM b_crm_status WHERE STATUS_ID='{$stageItemID}';";
			$resStageItemQuery = $DB->query($strStageItemSQL);
			while($resStageItem = $resStageItemQuery->Fetch()) {
				$stageItemValue = $resStageItem["NAME"];
			}

			$stageItem = ["id" => (string) $stageItemID,"value" => (string) $stageItemValue];
			//endregion

			//region $categoryItem
			$categoryItemID = $resItem['CATEGORY_ID'];

			$strCategoryItemSQL = "SELECT * FROM b_crm_item_category WHERE ENTITY_TYPE_ID='{$ENTITY_TYPE_ID}';";
			$resCategoryItemQuery = $DB->query($strCategoryItemSQL);
			while($resCategoryItem = $resCategoryItemQuery->Fetch()) {
				$categoryItemValue = $resCategoryItem["NAME"];
			}

			$categoryItem = ["id" => (string) $categoryItemID,"value" => (string) $categoryItemValue];
			//endregion

			//region $assignedByItem
			$assignedByItemID = $resItem['ASSIGNED_BY_ID'];
			$strAssignedByItemSQL = "SELECT * FROM b_user WHERE ID='{$assignedByItemID}';";
			$resAssignedByItemQuery = $DB->query($strAssignedByItemSQL);
			while($resAssignedByItem = $resAssignedByItemQuery->Fetch()) {
				//Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
				$assignedByItemValue = $resAssignedByItem["LAST_NAME"] . " " . $resAssignedByItem["NAME"];
			}
			$assignedByItem = ["id" => (string) $assignedByItemID,"value" => (string) $assignedByItemValue];
			//endregion



			$resUF['id'] = $idItem;
			$resUF['title'] = $titleItem;
			$resUF['organization'] = $organizationItem;
			$resUF['category'] = (array) $categoryItem;
			$resUF['stage'] = (array) $stageItem;
			$resUF['assigned_by'] = (array) $assignedByItem;
			$resUF['date'] = (string) $dateItem;
			$resUF['updateDate'] = (string) $updateDateItem;
			$resUF['totalDept'] = $totalDeptItem;
			$resUF['totalDeptMKK'] = $totalDeptMKKItem;
			$resUF['totalDeptKPK'] = $totalDeptKPKItem;
			$resUF['limitCreditLine'] = $limitCreditLineItem;

			//	}

			$arLeads['results'][] = $resUF;

		}

		$totalPages = ceil($totalItems / $qty);
		$arLeads['total'] = (integer) $totalItems;
		$arLeads['total_pages'] = (integer) $totalPages;

		if ($offset + $qty >= $totalItems) {
			$arLeads['has_more'] = false;
		} else {
			$arLeads['has_more'] = true;
		}

		$jsonRes['success'] = $arLeads;
		$jsonRes['error'] = "";
		Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
		return $jsonRes['success'];
	}

	public function getRepeatLoansSellersAction(array $params = []) {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$point = "BX_";
		$QUERY_STRING = $server['QUERY_STRING'];
		$url = $server['SCRIPT_URI']."?".$QUERY_STRING;
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$QUERY_STRING = $server['QUERY_STRING'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders()->toArray();
		$serverArray = $server->toArray();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}
		//Logs\File::AddMessage($serverArray,"serverArray",LOG_API_SYNC_CONTROLLER);
		$recieve = json_decode($request->getInput(),true);

		parse_str($QUERY_STRING, $queryArray);

		if(isset($queryArray['qty'])) {
			$qty = $queryArray['qty'];
		} else {
			$qty = 50;
		}
		if(isset($queryArray['page'])) {
			$offset = ($queryArray['page'] - 1) * $qty;
		} else {
			$offset = 0;
		}

		$objectData['ITEM_TITLE'] = "Запрос повторных займов селлеров: {$startDate} - {$endDate}";

		if(strtotime($startDate) > strtotime($endDate)) {
			$errorMessage = "400 Bad Request | `endDate` must more `startDate`!";
			$this->addError(new Error($errorMessage, 400));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}
		if($qty > 50) {
			$errorMessage = "400 Bad Request | `qty` not must more 50!";
			$this->addError(new Error($errorMessage, 400));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}

		global $DB;
		$arLeads = [];
		$ENTITY_TYPE_ID = 147;

		$strCountItemsSQL = "SELECT COUNT(*) FROM b_crm_dynamic_items_147 ORDER BY b_crm_dynamic_items_147.ID ASC;";
		$strItemSQL = "SELECT * FROM b_crm_dynamic_items_147 ORDER BY b_crm_dynamic_items_147.ID DESC LIMIT {$qty} OFFSET {$offset};";

		$resCountItemsQuery = $DB->query($strCountItemsSQL);
		while($resCountItems = $resCountItemsQuery->Fetch()) {
			$totalItems = $resCountItems['COUNT(*)'];
		}
		$resItemQuery = $DB->query($strItemSQL);

		$arLeads['object'] = (string) "repeatLoanSeller";

		while($resItem = $resItemQuery->Fetch()) {
			$idItem = (integer) $resItem['ID'];
			$titleItem = $resItem['TITLE'] != "" ? (string) $resItem['TITLE'] : null;
			$dateItem = date('Y-m-d\TH:i:s.msp', strtotime($resItem['CREATED_TIME']));
			$updateDateItem = date('Y-m-d\TH:i:s.msp', strtotime($resItem['UPDATED_TIME']));
			$requestSumTranshItem = $resItem['UF_CRM_57_1684867173074'] != "" ? (float) $resItem['UF_CRM_57_1684867173074'] : null;
			$accessFinanceDateItem = $resItem['UF_CRM_57_1696463989'] != "" ? (string) date('Y-m-d', strtotime($resItem['UF_CRM_57_1696463989'])) : null;

			//region $targetTranshItem
			$targetTranshID = $resItem['UF_CRM_57_1714967077'];
			if($targetTranshID === NULL) {
				$targetTranshItem = null;
			} else {
				$strTargetTranshItemSQL = "SELECT * FROM b_user_field_enum WHERE ID='{$targetTranshID}';";
				$resTargetTranshItemQuery = $DB->query($strTargetTranshItemSQL);
				while($resTargetTranshItem = $resTargetTranshItemQuery->Fetch()) {
					$targetTranshItemValue = $resTargetTranshItem["VALUE"];
				}

				$targetTranshItem = (string) $targetTranshItemValue;
			}
			//endregion

			//region $stageItem
			$stageItemID = $resItem['STAGE_ID'];
			$strStageItemSQL = "SELECT * FROM b_crm_status WHERE STATUS_ID='{$stageItemID}';";
			$resStageItemQuery = $DB->query($strStageItemSQL);
			while($resStageItem = $resStageItemQuery->Fetch()) {
				$stageItemValue = $resStageItem["NAME"];
			}

			$stageItem = ["id" => (string) $stageItemID,"value" => (string) $stageItemValue];
			//endregion

			//region $categoryItem
			$categoryItemID = $resItem['CATEGORY_ID'];

			$strCategoryItemSQL = "SELECT * FROM b_crm_item_category WHERE ENTITY_TYPE_ID='{$ENTITY_TYPE_ID}';";
			$resCategoryItemQuery = $DB->query($strCategoryItemSQL);
			while($resCategoryItem = $resCategoryItemQuery->Fetch()) {
				$categoryItemValue = $resCategoryItem["NAME"];
			}

			$categoryItem = ["id" => (string) $categoryItemID,"value" => (string) $categoryItemValue];
			//endregion

			//region $assignedByItem
			$assignedByItemID = $resItem['ASSIGNED_BY_ID'];
			$strAssignedByItemSQL = "SELECT * FROM b_user WHERE ID='{$assignedByItemID}';";
			$resAssignedByItemQuery = $DB->query($strAssignedByItemSQL);
			while($resAssignedByItem = $resAssignedByItemQuery->Fetch()) {
				//Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
				$assignedByItemValue = $resAssignedByItem["LAST_NAME"] . " " . $resAssignedByItem["NAME"];
			}
			$assignedByItem = ["id" => (string) $assignedByItemID,"value" => (string) $assignedByItemValue];
			//endregion

			$resUF['id'] = $idItem;
			$resUF['title'] = $titleItem;
			$resUF['category'] = (array) $categoryItem;
			$resUF['stage'] = (array) $stageItem;
			$resUF['assigned_by'] = (array) $assignedByItem;
			$resUF['date'] = (string) $dateItem;
			$resUF['updateDate'] = (string) $updateDateItem;
			$resUF['accessFinanceDate'] = (string) $accessFinanceDateItem;
			$resUF['requestSumTransh'] = (float) $requestSumTranshItem;
			$resUF['targetTransh'] = $targetTranshItem;

			//	}

			$arLeads['results'][] = $resUF;

		}

		$totalPages = ceil($totalItems / $qty);
		$arLeads['total'] = (integer) $totalItems;
		$arLeads['total_pages'] = (integer) $totalPages;

		if ($offset + $qty >= $totalItems) {
			$arLeads['has_more'] = false;
		} else {
			$arLeads['has_more'] = true;
		}

		$jsonRes['success'] = $arLeads;
		$jsonRes['error'] = "";
		Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
		return $jsonRes['success'];
	}
}