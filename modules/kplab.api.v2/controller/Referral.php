<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\EventResult;
use Bitrix\Main\Event;
use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\Web\JWT;
use KPLab\JWT\Controller\ActionFilter\Authentication;
use KPLab\Logs;

\CBitrixComponent::includeComponentClass("kplab:scpreward");
\Bitrix\Main\Loader::includeModule('rest');

define("LOG_REFERRAL", $_SERVER['DOCUMENT_ROOT']."/local/referral.log");

class Referral extends \Bitrix\Main\Engine\Controller
{
	private $referral;

	public function getDefaultPreFilters()
	{
		return [
			new Authentication()
		];
	}

	protected function prepareParams()
	{
		//$this->referral = new \KPLab\JWT\Referral();
		return parent::prepareParams();
	}

	public function referralGetInfoAction(array $params = [])
	{
		$res = array();

		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		\Bitrix\Main\Loader::IncludeModule('crm');

		$idReferral = json_decode($request->getInput(),true)['id'];

		$statusValidate = self::validateStatusToken($server, $idReferral);

		if($statusValidate === TRUE) {
			$resultReferrals = \CRest::call('lists.referral.get')['result'];

			foreach ($resultReferrals as $resultReferral) {
				if($resultReferral['ID_REFERRAL'] == $idReferral)
				{
					// Считаем сумму вознагрождений за предыдущий месяц
					$sellerTotalSum = self ::getSumScp($resultReferral['SUM_DEALS'], $resultReferral['ID_REFERRAL'], $server['REQUEST_TIME']);
					$res = $resultReferral;
				}
			}

			if(!empty($res)) {
				$result['all_deals'] = $res['COUNT_DEALS'];
				$result['all_leads'] = $res['COUNT_LEADS'];
				$result['all_sum_scp'] = $res['SUM_SCP_KB'];
				$result['inn'] = $res['INN_REFERRAL'];
				$result['qr'] = $res['QR_LINK'];
				$result['referral_link'] = $res['REFERRAL_LINK'];
				$result['last_sum_scp'] = $sellerTotalSum;

				return $result;
			} else {
				$this->addError(new Error('No referral info', 0));
				return null;
			}
		} else {
			$this->addError(new Error('Token is incorrect or invalid', 400));
			return null;
		}

	}

	public static function getSumScp($referralSumDeal, $referralId,$requestTime) {
		$leadDateFrom = "";
		$leadDateTo = "";

		$curM = date('n',$requestTime);
		$curY = date('Y',$requestTime);

		$lastM = date('m',strtotime('01-'.($curM-1).'-'.$curY));

		$tasksFrom = "01-".$curM.'-'.$curY;
		$tasksTo = "31-".$curM.'-'.$curY;

		$dateFromTo = [
			"FROM" => [
				"LEAD" => $leadDateFrom,
				"DEAL" => $tasksFrom
			],
			"TO" => [
				"LEAD" => $leadDateTo,
				"DEAL" => $tasksTo
			]
		];

		$sellerTotalSum = 0;
		if($referralSumDeal > 0) {
			//получаем информацию о каждом селлере в период, по ID реферала(КОМПАНИИ/КОНТАКТА)
			$sellers = \KPlabReports_2::getSellerByReferralId($referralId, $dateFromTo);

			foreach ($sellers as $k => $seller)
			{
				$sellerTotalSum = $sellerTotalSum + $seller['SUM_SCP_KB_LEAD'];
			}
		}

		$sellerTotalSum = number_format($sellerTotalSum, 2, '.', ' ');

		return $sellerTotalSum;
	}

	public static function validateStatusToken($server, $userId) {
		$authorization = $server->get('REMOTE_USER');

		$authText = stristr($authorization, 'Bearer');

		if($authText === false) {
			$token = $authorization;
		} else {
			$token = str_replace('Bearer ', '', $authorization);
		}

		$responseDecodeJWT = JWT::decode($token,"3lsxt0qfwbns0wve",["HS256"]);

		if($responseDecodeJWT == $userId) {
			return true;
		} else {
			return false;
		}
	}

	public function getSellersAction(array $params = [])
	{
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader::IncludeModule('crm');
		$res = array();
		$generalSumDeal = 0;
		$referral = json_decode($request->getInput(),true);
		//\KPLab\Logs\File::AddMessage($referral,"referral",LOG_REFERRAL);

		if(!isset($referral['id'])) {
			http_response_code(400);
			return 'Required field `id` failed';
		}
		else {
			$statusValidate = self::validateStatusToken($server, $referral['id']);

			$filter = $referral['filter'];

			$requestTime = $server['REQUEST_TIME'];
			//\KPLab\Logs\File::AddMessage($statusValidate,"statusValidate",LOG_REFERRAL);
			if($statusValidate === true)
			{
				$curM = date('m', $requestTime);
				$curY = date('Y', $requestTime);

				if (isset($filter['date']) && $filter['date']['start'] !== "" && $filter['date']['end'] !== "")
				{
					$leadDateStart = $filter['date']['start']."T00:00:00";
					$leadDateEnd = $filter['date']['end']."T23:59:59";

					$tasksFrom = "01-".$curM.'-'.$curY;
					$tasksTo = "31-".$curM.'-'.$curY;

				} elseif ($filter['date'] !== "all")
				{
					$leadDateStart = $curY . '-' . $curM . '-' . '01';
					$leadDateEnd = $curY . '-' . $curM . '-' . '31';
				} else
				{
					$leadDateStart = "";
					$leadDateEnd = "";
				}

				$dateFromTo = [
					"FROM" => [
						"LEAD" => $leadDateStart,
						"DEAL" => $tasksFrom
					],
					"TO" => [
						"LEAD" => $leadDateEnd,
						"DEAL" => $tasksTo
					]
				];

				$referralId = $referral['id'];
				//
				if (isset($filter['status']) && !empty($filter['status']))
				{
					$status = $filter['status'];
				} else
				{
					$status = "";
				}

				//\KPLab\Logs\File::AddMessage([$referralId,$dateFromTo,$status],"НаВходе",LOG_REFERRAL);

				//$sellers = \KPlabReports_2::getSellerByReferralId($referralId, $dateFromTo);
/*
				if($status == 'issued') {
					$arParams = array(
						'order' => ['PROPERTY_DATE_LEAD' => 'ASC'],
						'leadDate' => [
							'DATE_FROM' => $leadDateStart,
							'DATE_TO' => $leadDateEnd
						],
						'statusLead' => $status,
						'referralId' => $referralId
					);
				}
*/

				$arParams = array(
					'order' => ['PROPERTY_DATE_LEAD' => 'ASC'],
					'leadDate' => [
						'DATE_FROM' => $leadDateStart,
						'DATE_TO' => $leadDateEnd
					],
					'period' => [
						'DATE_FROM' => $leadDateStart,
						'DATE_TO' => $leadDateEnd
					],
					'statusLead' => $status,
					'referralId' => $referralId
				);

				//\KPLab\Logs\File::AddMessage($arParams,"arParams",LOG_REFERRAL);

				$sellers = \CRest ::call('lists.seller.get', $arParams)['result'];
				//\KPLab\Logs\File::AddMessage($sellers,"sellers",LOG_REFERRAL);
				


				foreach ($sellers as $k => $seller)
				{
					$i = $k + 1;
					$sellerTotalSum = $sellerTotalSum + $seller['SUM_SCP_KB_LEAD'];
				}

				$sellerTotalSum = number_format($sellerTotalSum, 2, '.', ' ');

				$total = $i;

				$count = $total;

				//$result['count2'] = $count;

				if (isset($referral['limit']))
				{
					$navLimit = $referral['limit'];
				} else
				{
					$navLimit = 50;
				}

				if (!isset($referral['start']) || $referral['start'] <= 50)
				{
					$navStart = 1;
					$key = 1;
					if ($navLimit == 1)
					{
						$limit = 0;
					} else
					{
						$limit = $navLimit;
					}
				} else
				{
					$navStart = $referral['start'];
					$key = $navStart;
					if ($navLimit == 1)
					{
						$limit = 0;
					} else
					{
						$limit = $navLimit;
					}
				}

				$downPoint = $navStart + $navLimit;

				if ($total < 50)
				{
					$navLimit = $count;
					$downPoint = $navStart + $navLimit;
				}

				if ($navStart <= $total)
				{
					$c = 0;
					while ($key - 1 < $downPoint)
					{
						if (isset($sellers[$key - 1]))
						{

							if ($sellers[$key - 1]["STATUS"] == "Новый") $sellersStatus = "new";
							if ($sellers[$key - 1]["STATUS"] == "В обработке") $sellersStatus = "in_processed";
							if ($sellers[$key - 1]["STATUS"] == "Выдан") $sellersStatus = "issued";
							if ($sellers[$key - 1]["STATUS"] == "Отказ") $sellersStatus = "refused";
							if ($sellers[$key - 1]["STATUS"] == "") $sellersStatus = "refused";

							$sellersId = $sellers[$key - 1]["ID"];
							$sellersName = $sellers[$key - 1]["NAME"];
							$sellersDateLead = $sellers[$key - 1]["DATE_LEAD"];
							$sellersDateDeal = $sellers[$key - 1]["DATA_DOGOVORA_ZAYMA"];
							$sellersSumDeal = $sellers[$key - 1]["SUMMA_PO_DOGOVORU"];
							$sellersSumSCP = $sellers[$key - 1]["SUM_SCP_KB_LEAD"];

							if (($sellersStatus == "new") || ($sellersStatus == "in_processed") || ($sellersStatus == "refused") ) {
								$res[$c]['status'] = $sellersStatus;
								$res[$c]['seller_id'] = $sellersId;
								$res[$c]['name'] = null;
								$res[$c]['dateLead'] = $sellersDateLead;
								$res[$c]['dateDeal'] = null;
								$res[$c]['sumDeal'] = null;
								$res[$c]['sumSCP'] = null;
							} else {
								$res[$c]['status'] = $sellersStatus;
								$res[$c]['seller_id'] = $sellersId;
								$res[$c]['name'] = $sellersName;
								$res[$c]['dateLead'] = $sellersDateLead;
								$res[$c]['dateDeal'] = $sellersDateDeal;
								$res[$c]['sumDeal'] = $sellersSumDeal;
								$res[$c]['sumSCP'] = $sellersSumSCP;
							}


							//$generalSumDeal += $sellersSumDeal;
						}

						$count = $navLimit;
						$key++;
						$c++;
					}
				}
				else
				{
					$res = [];
					$count = 0;
				}

				$result['result'] = $res;

				if ($total > $downPoint)
				{
					//$result['count'] = $count;
					$result['next'] = $downPoint;
				} elseif ($total <= $downPoint)
				{
					//$result['count'] = $downPoint-$total;
				}

				unset($result['count']);
				$result['total'] = $total;
				$result['sumDeals'] = $sellerTotalSum;

				if (empty($result['result']))
				{
					//unset($result['next']);
					//unset($result['total']);
					//unset($result['sumDeals']);
				}
				return $result;
			} else {
				$this->addError(new Error('Token is incorrect or invalid', 400));
				return null;
			}
		}
	}
}