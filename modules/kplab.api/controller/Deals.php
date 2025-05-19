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

define("LOG_API_DEALS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DealsController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");


class Deals extends \Bitrix\Main\Engine\Controller
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
		return parent::prepareParams();
	}
	public function getDealsAction(array $params = []) {
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

		if(isset($queryArray['category_id'])) {
			$categoryId = $queryArray['category_id'];
		} else {
			$categoryId = null;
		}

		if(isset($queryArray['startDate'])) {
			$startDate = date('Y-m-d', strtotime($queryArray['startDate']));
		} else {
			$startDate = null;
		}
		if(isset($queryArray['endDate'])) {
			$endDate = date('Y-m-d', strtotime($queryArray['endDate']));
		} else {
			$endDate = date('Y-m-d', strtotime('now'));
		}

		$objectData['ITEM_TITLE'] = "Запрос Сделок: {$startDate} - {$endDate}";

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

		if($startDate === NULL) {
            if($categoryId === NULL)
            {
                $strCountDealsSQL = "SELECT COUNT(*) FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' ORDER BY b_crm_deal.ID ASC;";

                $strDealSQL = "SELECT * FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}'  ORDER BY b_crm_deal.ID ASC LIMIT " . $qty . " OFFSET " . $offset . ";";
            } else {
                $strCountDealsSQL = "SELECT COUNT(*) FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' ORDER BY b_crm_deal.ID ASC;";

                $strDealSQL = "SELECT * FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}'  ORDER BY b_crm_deal.ID ASC LIMIT " . $qty . " OFFSET " . $offset . ";";
            }
		} else {
            if ($categoryId === NULL)
            {
                $strCountDealsSQL = "SELECT COUNT(*) FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_deal.ID ASC;";

                $strDealSQL = "SELECT * FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_deal.ID ASC LIMIT " . $qty . " OFFSET " . $offset . ";";
            } else {
                $strCountDealsSQL = "SELECT COUNT(*) FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_deal.ID ASC;";

                $strDealSQL = "SELECT * FROM b_crm_deal
				INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID
				WHERE b_crm_deal.CATEGORY_ID = '{$categoryId}' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_deal.ID ASC LIMIT " . $qty . " OFFSET " . $offset . ";";
            }
		}

		$resCountDealsQuery = $DB->query($strCountDealsSQL);
		while($resCountDeals = $resCountDealsQuery->Fetch()) {
			$totalDeals = $resCountDeals['COUNT(*)'];
		}
		$resDealQuery = $DB->query($strDealSQL);

		$arLeads['object'] = (string) "deal";

		while($resDeal = $resDealQuery->Fetch()) {
			$idDeal = (integer) $resDeal['ID'];
			$titleDeal = $resDeal['TITLE'] != "" ? (string) $resDeal['TITLE'] : null;
			$organizationDeal = $resDeal['UF_CRM_1656841677'] != "" ? (int) $resDeal['UF_CRM_1656841677'] : null;
			$sumDeal = $resDeal['OPPORTUNITY'] != "" ? (string) $resDeal['OPPORTUNITY'] : null;
			$dateDeal = date('Y-m-d\TH:i:s.msp', strtotime($resDeal['DATE_CREATE']));
			$updateDateDeal = date('Y-m-d\TH:i:s.msp', strtotime($resDeal['DATE_MODIFY']));
			$accessFinanceDateDeal = date('Y-m-d\TH:i:s.msp', strtotime($resDeal['UF_CRM_1682505154']));
            $isOverdue = $resDeal['UF_CRM_DEAL_OVERDUE_FLAG'] != "" ? (bool) $resDeal['UF_CRM_DEAL_OVERDUE_FLAG'] : false;

			//region $sourceDeal
			$sourceDealID = $resDeal['SOURCE_ID'];
			$strSourceDealSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID='SOURCE' AND STATUS_ID='{$sourceDealID}';";
			$resSourceDealQuery = $DB->query($strSourceDealSQL);
			while($resSourceDeal = $resSourceDealQuery->Fetch()) {
				$sourceDealValue = $resSourceDeal["NAME"];
			}
			$sourceDeal = ["id" => (string) $sourceDealID,"value" => (string) $sourceDealValue];
			//endregion

			//region $stageDeal
			$stageDealID = $resDeal['STAGE_ID'];
			$oUserFieldEnum = new \CUserFieldEnum();
			$rsGender = $oUserFieldEnum::GetList(array(), array(
				"XML_ID" => $stageDealID,
			));
			if($arGender = $rsGender->GetNext())
				$stageDealValue = $arGender["VALUE"];

			$stageDeal = ["id" => (string) $stageDealID,"value" => (string) $stageDealValue];
			//endregion

			//region $categoryDeal
			$categoryDealID = $resDeal['CATEGORY_ID'];

			if($categoryDealID <> 0) {
				$strCategoryDealSQL = "SELECT * FROM b_crm_deal_category WHERE ID='{$categoryDealID}';";
				$resCategoryDealQuery = $DB->query($strCategoryDealSQL);
				while($resCategoryDeal = $resCategoryDealQuery->Fetch()) {
					$categoryDealValue = $resCategoryDeal["NAME"];
				}
			} else {
				$categoryDealValue = "Займы";
			}


			$categoryDeal = ["id" => (string) $categoryDealID,"value" => (string) $categoryDealValue];
			//endregion

			//region $assignedByDeal
			$assignedByDealID = $resDeal['ASSIGNED_BY_ID'];
			$strAssignedByDealSQL = "SELECT * FROM b_user WHERE ID='{$assignedByDealID}';";
			$resAssignedByDealQuery = $DB->query($strAssignedByDealSQL);
			while($resAssignedByDeal = $resAssignedByDealQuery->Fetch()) {
				//Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
				$assignedByDealValue = $resAssignedByDeal["LAST_NAME"] . " " . $resAssignedByDeal["NAME"];
			}
			$assignedByDeal = ["id" => (string) $assignedByDealID,"value" => (string) $assignedByDealValue];
			//endregion

			$resUF['id'] = $idDeal;
			$resUF['title'] = $titleDeal;
			$resUF['organization'] = $organizationDeal;
			$resUF['category'] = (array) $categoryDeal;
			$resUF['stage'] = (array) $stageDeal;
			$resUF['source'] = (array) $sourceDeal;
			$resUF['assigned_by'] = (array) $assignedByDeal;
			$resUF['date'] = (string) $dateDeal;
			$resUF['updateDate'] = (string) $updateDateDeal;
			$resUF['sum'] = (string) $sumDeal;
			$resUF['accessFinanceDate'] = (string) $accessFinanceDateDeal;
            $resUF['isOverdue'] = $isOverdue;

			//	}

			$arLeads['results'][] = $resUF;

		}

		$totalPages = ceil($totalDeals / $qty);
		$arLeads['total'] = (integer) $totalDeals;
		$arLeads['total_pages'] = (integer) $totalPages;

		if ($offset + $qty >= $totalDeals) {
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