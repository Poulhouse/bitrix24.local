<?php namespace KPLab\API\Controller;

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

define("LOG_API_SYNC_UNDERWRITING_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/UnderwritingController.log");
class Underwriting extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\Controller\ActionFilter\Authentication(),
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

	public function getUnderwritingItemsAction(array $params = [])
	{
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$point = "EXTRANET_BX";
		$QUERY_STRING = $server['QUERY_STRING'];
		$url = $server['SCRIPT_URI']."?".$QUERY_STRING;

		Logs\File ::AddMessage($url, "url", LOG_API_SYNC_UNDERWRITING_CONTROLLER);
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$QUERY_STRING = $server['QUERY_STRING'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders()->toArray();
		$serverArray = $server->toArray();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}
		$recieve = json_decode($request->getInput(),true);

		parse_str($QUERY_STRING, $queryArray);

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

		$objectData['ITEM_TITLE'] = "Запрос Элементов смарта Андерайтинг: {$startDate} - {$endDate}";

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
		$arUnderwritingItems = [];
		
		if($startDate === NULL) {
			$strCountUnderwritingItemsSQL = "SELECT COUNT(*) FROM b_crm_dynamic_items_149
			WHERE DATE(CREATED_TIME) <= '{$endDate}' ORDER BY ID ASC;";

			$strUnderwritingItemsSQL = "SELECT * FROM b_crm_dynamic_items_149
			WHERE DATE(CREATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
		}
		else {
			$strCountUnderwritingItemsSQL = "SELECT COUNT(*) FROM b_crm_dynamic_items_149
			WHERE (CREATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC;";

			$strUnderwritingItemsSQL = "SELECT * FROM b_crm_dynamic_items_149
			WHERE (CREATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
		}

		$resCountUnderwritingItemsQuery = $DB->query($strCountUnderwritingItemsSQL);
		while($resCountUnderwritingItems = $resCountUnderwritingItemsQuery->Fetch()) {
			$totalUnderwritingItems = $resCountUnderwritingItems['COUNT(*)'];
		}

		$arUnderwritingItems['object'] = (string) "underwriting";
		$resUnderwritingItemsQuery = $DB->query($strUnderwritingItemsSQL);

		while($resUnderwritingItem = $resUnderwritingItemsQuery->Fetch()) {
			$idUnderwritingItem = (integer) $resUnderwritingItem['ID'];
			$titleUnderwritingItem = $resUnderwritingItem['TITLE'] != "" ? (string) $resUnderwritingItem['TITLE'] : null;
			$dateUnderwritingItem = date('Y-m-d\TH:i:s.msp', strtotime($resUnderwritingItem['CREATED_TIME']));
			$updateDateUnderwritingItem = date('Y-m-d\TH:i:s.msp', strtotime($resUnderwritingItem['UPDATED_TIME']));

			//region $scoringChklstResultUnderwritingItem
			$scoringChklstResultUnderwritingItemID = $resUnderwritingItem['UF_CRM_CHKLST']; //результат скоринга по
			// текущим данным. (тип поля - список)
			if($scoringChklstResultUnderwritingItemID !== null) {
				$oUserFieldEnum = new \CUserFieldEnum();
				$rsGender = $oUserFieldEnum::GetList(array(), array(
					"ID" => $scoringChklstResultUnderwritingItemID,
				));
				if($arGender = $rsGender->GetNext()) {
					$scoringChklstResultValue = $arGender["VALUE"];
					$scoringChklstResultXML = $arGender["XML_ID"];
				}
				$scoringChklstResultUnderwritingItem = ["id" => (string) $scoringChklstResultXML,"value" => (string) $scoringChklstResultValue];
			} else {
				$scoringChklstResultUnderwritingItem = null;
			}
			//endregion

			//region $scoringChklstRisksResultUnderwritingItem
			$scoringChklstRisksResultUnderwritingItemID = $resUnderwritingItem['UF_CRM_CHKLST_RISKS']; //результат скоринга после андеррайтинга. (тип поля - список)
			if($scoringChklstRisksResultUnderwritingItemID !== null) {
				$oUserFieldEnum = new \CUserFieldEnum();
				$rsGender = $oUserFieldEnum::GetList(array(), array(
					"ID" => $scoringChklstRisksResultUnderwritingItemID,
				));
				if($arGender = $rsGender->GetNext()) {
					$scoringChklstRisksResultValue = $arGender["VALUE"];
					$scoringChklstRisksResultXML = $arGender["XML_ID"];
				}
				$scoringChklstRisksResultUnderwritingItem = ["id" => (string) $scoringChklstRisksResultXML,"value" => (string) $scoringChklstRisksResultValue];
			} else {
				$scoringChklstRisksResultUnderwritingItem = null;
			}
			//endregion

			//region $typeUnderwritingItem
			$typeUnderwritingItemID = $resUnderwritingItem['UF_CRM_49_1702294425726']; //Тип/Вид оформления. (тип поля -
			// список)
			if($typeUnderwritingItemID !== "") {
				$oUserFieldEnum = new \CUserFieldEnum();
				$rsGender = $oUserFieldEnum::GetList(array(), array(
					"ID" => $typeUnderwritingItemID,
				));
				if($arGender = $rsGender->GetNext()) {
					$typeUnderwritingItemValue = $arGender["VALUE"];
					$typeUnderwritingItemXML = $arGender["XML_ID"];
				}
				$typeUnderwritingItem = ["id" => (string) $typeUnderwritingItemXML,"value" => (string) $typeUnderwritingItemValue];
			} else {
				$typeUnderwritingItem = null;
			}
			//endregion

			//region $assignedByLead
			$assignedByUnderwritingItemID = $resUnderwritingItem['ASSIGNED_BY_ID'];
			$strAssignedByUnderwritingItemSQL = "SELECT * FROM b_user WHERE ID='{$assignedByUnderwritingItemID}';";
			$resAssignedByUnderwritingItemQuery = $DB->query($strAssignedByUnderwritingItemSQL);
			while($resAssignedByUnderwritingItem = $resAssignedByUnderwritingItemQuery->Fetch()) {
				//Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
				$assignedByUnderwritingItemValue = $resAssignedByUnderwritingItem["LAST_NAME"] . " " . $resAssignedByUnderwritingItem["NAME"];
			}
			$assignedByUnderwritingItem = ["id" => (string) $assignedByUnderwritingItemID,"value" => (string) $assignedByUnderwritingItemValue];
			//endregion

			$differentStatusUnderwritingItem = $resUnderwritingItem['UF_CRM_DIFFERENT_CHKLST'];


			$res['id'] = $idUnderwritingItem;
			$res['title'] = $titleUnderwritingItem;
			$res['date'] = $dateUnderwritingItem;
			$res['updateDate'] = $updateDateUnderwritingItem;
			$res['assigned_by'] = (array) $assignedByUnderwritingItem;
			$res['scoringChklst'] = (array) $scoringChklstResultUnderwritingItem;
			$res['scoringChklstRisks'] = (array) $scoringChklstRisksResultUnderwritingItem;
			$res['type'] = (array) $typeUnderwritingItem;
			$res['differentStatus'] = (bool) $differentStatusUnderwritingItem;

			$arUnderwritingItems['results'][] = $res;
		}

		$totalPages = ceil($totalUnderwritingItems / $qty);
		$arUnderwritingItems['total'] = (integer) $totalUnderwritingItems;
		$arUnderwritingItems['total_pages'] = (integer) $totalPages;

		if ($offset + $qty >= $totalUnderwritingItems) {
			$arUnderwritingItems['has_more'] = false;
		} else {
			$arUnderwritingItems['has_more'] = true;
		}

		$jsonRes['success'] = $arUnderwritingItems;
		$jsonRes['error'] = "";
		Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
		return $jsonRes['success'];
	}
}