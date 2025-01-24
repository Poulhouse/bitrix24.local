<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Web\JWT;
use KPLab\OneC\Controller\ActionFilter\Authentication;
use KPLab\Logs;

define("LOG_ONEC_SYNC_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/syncController.log");
define("TOKEN_ONEC_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwb2ludCI6IjFDX0JYIn0.pjioDJHkvil35XIgncYS4FZZso0wx4Vodi-P-Ul7uYc");

class OneCSync extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new Authentication(),
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

	public function syncAction() {

		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		//Logs\File::AddMessage($request,"request", LOG_ONEC_SYNC_CONTROLLER);

		$response = $context -> getResponse();
		$server = $context -> getServer();
		$point = "1C_BX";
		$url = "https://crm.seller-capital.ru/api/OneCSync/";
		$headers = $request->getHeaders();
		//$recieve = json_decode($request->getInput(),true);
		//Logs\File::AddMessage($recieve,"recieve", LOG_ONEC_SYNC_CONTROLLER);

		$strJson = $request->getInput();
		try {
			$data = \Bitrix\Main\Web\Json::decode($strJson);
		} catch (\Exception $e) {
			echo 'Caught exception: ',  $e->getMessage(), "\n";
			ss_SocNetMessageAdd(1, 483, $e->getMessage()."Ошибка чтения JSON при получении данных из АК-Кредит - ".$strJson);

			$jsonRes['success'] = "";
			$jsonRes['error'] = $e->getMessage()."Ошибка чтения JSON при получении данных из АК-Кредит - ".$strJson;
		}
		$jsonRes['success'] = '{"status":"success"}';
		$jsonRes['error'] = "";

		$objectData['ITEM_TITLE'] = $data['Наименование'] . " | " . $data['Номер']. " от " . $data['Дата'];
		$objectData['ITEM_TYPE_ID'] = "";
		$objectData['METHOD'] = $server['REQUEST_METHOD'];
		$objectData['INIT_OBJECT_URL'] = "";


		if($jsonRes['error'] == "") {
			$res = Logs\IBlock::setData($url, $strJson, $jsonRes, $objectData, $timeData, $point, $headers);
			return $jsonRes;
		} else {
			Logs\IBlock::setData($url, $strJson, $jsonRes, $objectData, $timeData, $point, $headers);
			return false;
		}

	}
}