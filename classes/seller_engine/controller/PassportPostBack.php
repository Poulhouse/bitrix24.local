<?php namespace KPLab\SellerEngine\Controller;

use KPLab\Logs;
use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter\Base;
use KPLab\API\Authentication;

\Bitrix\Main\Loader::includeModule('rest');
define("LOG_SE_PASSPORT_PB", $_SERVER['DOCUMENT_ROOT']."/local/classes/seller_engine/passport_pb.log");

class PassportPostBack extends \Bitrix\Main\Engine\Controller
{
	private $passport;

	public function getDefaultPreFilters()
	{
		return [
			new Authentication()
		];
	}

	protected function prepareParams()
	{
		return parent ::prepareParams();
	}

	public function passportPostBackAction(array $params = [])
	{
		return false;
	}
}