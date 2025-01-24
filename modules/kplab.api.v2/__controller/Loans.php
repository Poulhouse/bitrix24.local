<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\Web\JWT;
use KPLab\JWT\Controller\ActionFilter\Authentication;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use KPLab\Logs;

\CBitrixComponent::includeComponentClass("kplab:scpreward");
\Bitrix\Main\Loader::includeModule('rest');
define("LOG_LoansController", $_SERVER['DOCUMENT_ROOT']."/local/classes/LoansController.log");
class Loans extends \Bitrix\Main\Engine\Controller
{
	private $loans;

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
	public function onBeforeAction(\Event $event) {
		return null;
	}

	protected function prepareParams()
	{
		//$this->loans = new \KPLab\JWT\Loans();
		return parent::prepareParams();
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

	public function getLoansAction(array $params = [])
	{
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$res = array();
		$loan = json_decode($request->getInput(),true);

		$filter = $loan['filter'];
		$validateStatus = self::validateStatusToken($server, $loan['id']);

		$requestTime = $server['REQUEST_TIME'];

		if($validateStatus === true) {
			$curM = date('m',$requestTime);
			$curY = date('Y',$requestTime);

			if(isset($filter['date']) && $filter['date'] == 'all'){
				$userId = $loan['id'];
				$parameters = [
					'entityTypeId' => 188,
					'order' => ['ID' => 'ASC'],
					'filter' => [
						[
							"logic" => "OR",
							["stageId" => "DT188_28:NEW"],
							["stageId" => "DT188_28:CLIENT"],
							["stageId" => "DT188_28:UC_EQ8KZU"]
						],
						[
							"logic" => "OR",
							["companyId" => $userId],
							["contactId" => $userId],
							["ufCrm15_1703078261" => $userId]
						],
						'ufCrm15SsFilial' => 17611
					],
					"select" => ['*'],
					'start' => -1
				];
			}else
			{
				if (isset($filter['date']) && $filter['date']['start'] !== "" && $filter['date']['end'] !== "")
				{
					$dogDateStart = $filter['date']['start'];
					$dogDateEnd = $filter['date']['end'];
				} else
				{
					$dogDateStart = '01-01-1997';
					$dogDateEnd = '31-' . $curM . '-' . $curY;
				}
				$userId = $loan['id'];

				//AddMessage2Log($userId,"userId");

				$parameters = [
					'entityTypeId' => 188,
					'order' => ['ID' => 'ASC'],
					'filter' => [
						[
							"logic" => "OR",
							["stageId" => "DT188_28:NEW"],
							["stageId" => "DT188_28:CLIENT"],
							["stageId" => "DT188_28:UC_EQ8KZU"]
						],
						[
							"logic" => "OR",
							["companyId" => $userId],
							["contactId" => $userId],
							["ufCrm15_1703078261" => $userId]
						],
						">=ufCrm15_1679925201" => $dogDateStart,
						"<=ufCrm15_1679925201" => $dogDateEnd,
						'ufCrm15SsFilial' => 17611
					],
					"select" => ['*'],
					'start' => -1
				];
				//AddMessage2Log($parameters,"parameters");
			}

			$leads = \CRest::Call("crm.item.list", $parameters)['result']['items'];

			//AddMessage2Log($leads,"leads");

			$total = count($leads);

			if(isset($loan['limit'])) {
				$navLimit = $loan['limit'];
			} else {
				$navLimit = 50;
			}

			if(!isset($loan['start']) || $loan['start'] <= 50) {
				$navStart = 1;
				$key = 1;
				if($navLimit == 1) {
					$limit = 0;
				} else {
					$limit = $navLimit;
				}
			} else {
				$navStart = $loan['start'];
				$key = $navStart;
				if($navLimit == 1) {
					$limit = 0;
				} else {
					$limit = $navLimit;
				}
			}

			$downPoint = $navStart + $navLimit;

			if($navStart <= $total) {
				$c = 0;
				while ($key < $downPoint) {
					if(isset($leads[$key-1])) {
						if(number_format((float) str_replace("|RUB","",$leads[$key-1]['ufCrm15_1679907467']), 2,"."," ") == 0.00) {
							$sumProsrocheno = null;
						} else {
							$sumProsrocheno = number_format((float) str_replace("|RUB","",$leads[$key-1]['ufCrm15_1679907467']), 2,"."," ");
						}
						$res[$c]['numberDog'] = $leads[$key-1]["ufCrm15SsNomer"];
						$res[$c]['sumDog'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsSummadogovora"]), 2,"."," ");
						$res[$c]['dateDog'] = date('d.m.Y', strtotime($leads[$key-1]["ufCrm15_1679925201"]));
						$res[$c]['nextPayDay'] = date('d.m.Y', strtotime($leads[$key-1]["ufCrm15SsNextpayday"]));
						$res[$c]['nextPaySum'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsNextpaysumma"]), 2,"."," ");
						$res[$c]['prosrochenoDays'] = $leads[$key-1]['ufCrm15_1679907525'];
						$res[$c]['sumProsrocheno'] = $sumProsrocheno;
						$res[$c]['ostatok'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsNominal"]), 2,"."," ");
					}
					$count = $navLimit;
					$key++;
					$c++;
				}
			} else {
				$res = [];
				$count = 0;
			}

			$result['result'] = $res;

			if($total > $downPoint) {
				$result['count'] = $count;
				$result['next'] = $downPoint;
			} elseif($total <= $downPoint ){
				if($total >= 50) {
					$result['count'] = $downPoint-$total;
				}  else {
					$result['count'] = count($res);
				}
			}
			$result['total'] = $total;

			if(empty($result['result'])) {
				unset($result['count']);
				unset($result['next']);
				unset($result['total']);
			}
		} else {
			$this->addError(new Error('Token is incorrect or invalid.', 400));
			return null;
		}

		return $result;

	}

	public static function getLoansByIntervalAction(array $params = [])
	{
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$res = array();
		$loan = json_decode($request->getInput(),true);
		$filter = $loan['filter'];

		$requestTime = $server['REQUEST_TIME'];

		$curM = date('m',$requestTime);
		$curY = date('Y',$requestTime);
		if(isset($filter['date']) && $filter['date'] == 'all'){
			$userId = $filter['id'];
			$parameters = [
				'entityTypeId' => 188,
				'order' => ['ID' => 'ASC'],
				'filter' => [
					[
						"logic" => "OR",
						["stageId" => "DT188_28:NEW"],
						["stageId" => "DT188_28:CLIENT"],
						["stageId" => "DT188_28:UC_EQ8KZU"]
					],
					[
						"logic" => "OR",
						["companyId" => $userId],
						["contactId" => $userId],
						["ufCrm15_1703078261" => $userId]
					],
					'ufCrm15SsFilial' => 17611
				],
				"select" => ['*','ufCrm15_1679925201'],
				'start' => -1
			];
		}else{
			if(isset($filter['date']) && $filter['date']['start'] !== "" && $filter['date']['end'] !== "") {
				$dogDateStart = $filter['date']['start'];
				$dogDateEnd = $filter['date']['end'];
			} else {
				$dogDateStart = '01-'.$curM.'-'.$curY;
				$dogDateEnd = '31-'.$curM.'-'.$curY;
			}
			$userId = $filter['id'];



			$parameters = [
				'entityTypeId' => 188,
				'order' => ['ID' => 'ASC'],
				'filter' => [
					[
						"logic" => "OR",
						["stageId" => "DT188_28:NEW"],
						["stageId" => "DT188_28:CLIENT"],
						["stageId" => "DT188_28:UC_EQ8KZU"]
					],
					[
						"logic" => "OR",
						["companyId" => $userId],
						["contactId" => $userId],
						["ufCrm15_1703078261" => $userId]
					],
					">=ufCrm15_1679925201" => $dogDateStart,
					"<=ufCrm15_1679925201" => $dogDateEnd,
					'ufCrm15SsFilial' => 17611
				],
				"select" => ['*','ufCrm15_1679925201'],
				'start' => -1
			];
		}


		$restListSellers = \CRest::Call("crm.item.list", $parameters);
		$leads = $restListSellers['result']['items'];

		//AddMessage2Log($restListSellers);

		$total = count($leads);

		if(isset($loan['limit'])) {
			$navLimit = $loan['limit'];
		} else {
			$navLimit = 50;
		}

		if(!isset($loan['start']) || $loan['start'] <= 50) {
			$navStart = 1;
			$key = 1;
			if($navLimit == 1) {
				$limit = 0;
			} else {
				$limit = $navLimit;
			}
		} else {
			$navStart = $loan['start'];
			$key = $navStart;
			if($navLimit == 1) {
				$limit = 0;
			} else {
				$limit = $navLimit;
			}
		}

		$downPoint = $navStart + $navLimit;

		if($navStart <= $total) {
			$c = 0;
			while ($key < $downPoint) {
				if(isset($leads[$key-1])) {
					if(number_format((float) str_replace("|RUB","",$leads[$key-1]['ufCrm15_1679907467']), 2,"."," ") == 0.00) {
						$sumProsrocheno = null;
					} else {
						$sumProsrocheno = number_format((float) str_replace("|RUB","",$leads[$key-1]['ufCrm15_1679907467']), 2,"."," ");
					}
					$res[$c]['numberDog'] = $leads[$key-1]["ufCrm15SsNomer"];
					$res[$c]['sumDog'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsSummadogovora"]), 2,"."," ");
					$res[$c]['dateDog'] = date('d.m.Y', strtotime($leads[$key-1]["ufCrm15_1679925201"]));
					$res[$c]['nextPayDay'] = date('d.m.Y', strtotime($leads[$key-1]["ufCrm15SsNextpayday"]));
					$res[$c]['nextPaySum'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsNextpaysumma"]), 2,"."," ");
					$res[$c]['prosrochenoDays'] = $leads[$key-1]['ufCrm15_1679907525'];
					$res[$c]['sumProsrocheno'] = $sumProsrocheno;
					$res[$c]['ostatok'] = number_format((float) str_replace("|RUB","",$leads[$key-1]["ufCrm15SsNominal"]), 2,"."," ");
				}
				$count = $navLimit;
				$key++;
				$c++;
			}
		} else {
			$res = [];
			$count = 0;
		}


		$result['result'] = $res;

		if($total > $downPoint) {
			$result['count'] = $count;
			$result['next'] = $downPoint;
		} elseif($total <= $downPoint ){
			if($total >= 50) {
				$result['count'] = $downPoint-$total;
			}  else {
				$result['count'] = count($res);
			}

		}
		$result['total'] = $total;

		if(empty($result['result'])) {
			unset($result['count']);
			unset($result['next']);
			unset($result['total']);
		}

		return $result;

	}

	public function getLoansLimitsAction(array $params = [])
	{
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$res = array();
		$user = json_decode($request->getInput(),true);
		$userId = $user['id'];

		$validateStatus = self::validateStatusToken($server, $userId);

		//AddMessage2Log($validateStatus,'$validateStatus');

		if( $validateStatus === TRUE) {
			$company = \Crest::Call("crm.company.get",array('id'=>$userId))['result'];
			$contact = \Crest::Call("crm.contact.get",array('id'=>$userId))['result'];

			//AddMessage2Log($company['ID'],'$company[ID]');
			//AddMessage2Log($contact,'$contact');

			$arSelect = ['*'];
			$arOrder = null;
			if($company !== null) {
				$arFilterCompany = [
					'companyId' => $company['ID']
				];
				$arParamsCompany = [
					'entityTypeId'=>134,
					'select' => $arSelect,
					'order' => $arOrder,
					'filter' => $arFilterCompany
				];
				$itemType134Company = \Crest::Call("crm.item.list", $arParamsCompany)['result']['items'][0];
                //AddMessage2Log($itemType134Company,'itemType134Company');
                //Logs\File::AddMessage($itemType134Company,"itemType134Company", LOG_LoansController);

				$res['selectLimit'] = round((float) str_replace("|RUB","",$itemType134Company["ufCrm56_1684744827969"]),2);
				$res['soglasLimit'] = round((float) str_replace("|RUB","",$itemType134Company["ufCrm56_1684744846487"]),2);
				$res['dostupLimit'] = round((float) str_replace("|RUB","",$itemType134Company["ufCrm56_1684744875738"]),2);
                $res['repeatTranche'] = (bool) true;
                if($itemType134Company['ufOscRepeatTranche'] == 0) {$res['repeatTranche'] = (bool) false;}

			} elseif($contact !== null) {
				$arFilterContact = [
					'contactId' => $contact['ID']
				];
				$arParamsContact = [
					'entityTypeId'=>134,
					'select' => $arSelect,
					'order' => $arOrder,
					'filter' => $arFilterContact
				];
				$itemType134Contact = \Crest::Call("crm.item.list", $arParamsContact)['result']['items'][0];



				$res['selectLimit'] = round((float) str_replace("|RUB","",$itemType134Contact["ufCrm56_1684744827969"]),2);
				$res['soglasLimit'] = round((float) str_replace("|RUB","",$itemType134Contact["ufCrm56_1684744846487"]),2);
				$res['dostupLimit'] = round((float) str_replace("|RUB","",$itemType134Contact["ufCrm56_1684744875738"]),2);
                $res['repeatTranche'] = (bool) true;
                if($itemType134Contact['ufOscRepeatTranche'] == 0) {$res['repeatTranche'] = (bool) false;}
                //AddMessage2Log($itemType134Contact,'$itemType134Contact');
			} else {
				$this->addError(new Error('No data limits.', 400));
				return null;
			}

			return $res;
		} else {
			$this->addError(new Error('Token is incorrect or invalid.', 400));
			return null;
		}


	}

	public function setOrderNewTranshAction(array $params = [])
	{
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$timestamp = date("Y-m-d");//T10:06:19+05:00
		$date = new \DateTime($timestamp);
		$date->modify('+1 day');
		$res = array();
		$data = json_decode($request->getInput(),true);

		if(empty($data))
		{
			$this->addError(new Error('Пустой запрос', 400));
			return null;
		}


		AddMessage2Log($data,'$data Повторный транш');

		$userId = $data['id'];
		$sumTransh = $data['sum'];

		$company = \Crest::Call("crm.company.get",array('id'=>$userId))['result'];

		$arSelect = ['id','companyId','ufCrm56_1684820676574','ufCrm56_1699534520','ufCrm56_1686288649629','ufCrm56_1684917532850','ufCrm56_1684868548991','ufCrm56_1684822287575',"ufCrm56_1684917581455"];
		$arOrder = ['id' => 'ASC'];
//		$req = new \Bitrix\Crm\EntityRequisite();
//		$rsCompany = $req->getList(array(
//			'filter' => array(
//				'ENTITY_ID' => $company['ID']
//			),
//			'select' => array('RQ_INN', 'RQ_COMPANY_NAME', 'RQ_COMPANY_FULL_NAME',
//				'RQ_OGRN', 'RQ_KPP', 'RQ_COMPANY_REG_DATE', 'RQ_OKPO', 'RQ_OKTMO',
//				'RQ_DIRECTOR', 'RQ_ACCOUNTANT', 'RQ_ADDR'),
//		));

		if(!empty($company))
		{
			$arFilterCompany = [
				'companyId' => $company['ID']//,
				//'ufCrm56_1684841075' => $rq['RQ_INN']
			];

			$arParamsCompany = [
				'entityTypeId' => 134,
				'select' => $arSelect,
				'order' => $arOrder,
				'filter' => $arFilterCompany
			];

			$itemType134Company = \Crest::Call("crm.item.list", $arParamsCompany)['result']['items'][0];
			//AddMessage2Log($itemType134Company,'$itemType134Company');

			if(empty($itemType134Company)) {
				$this->addError(new Error('Ошибка в данных', 400));
				return null;
			}

			$arParams_Company = [
				'entityTypeId'=>134,
				'id'=>$itemType134Company["id"],
				'fields' => [
					'ufCrm56_1686288649629' => "Пополнение оборотных средств (ЛК)", //Прошлая цель из СП Сопровождение
					'ufCrm56_1699534520' => 'Y', //Повторный из лк
					'ufCrm56_1684917532850' => $date->format('Y-m-d'), //Дата получения займа(Текущая дата + 1)
					'ufCrm56_1684868548991' => $sumTransh, //Сумма повторного транша из ЛК
					'ufCrm56_1684822287575' => 'Y', //Создать заявку на повторный транш!
					'ufCrm56_1684917581455' => 11996
				]
			];

			$res = \Crest::Call("crm.item.update", $arParams_Company);
		} else {

			$this->addError(new Error('Ошибка в данных', 400));
			return null;
/*
			$contact = \Crest::Call("crm.contact.get",array('id'=>$userId))['result'];

			//AddMessage2Log($contact['ID'],'$contact[ID]');

			$rsContact = $req->getList(array(
				'filter' => array(
					'ENTITY_ID' => $contact['ID']
					//'ENTITY_TYPE_ID' => \CCrmOwnerType::Contact,
					//'PRESET_ID' => 1
				),
				'select' => array('RQ_INN', 'RQ_COMPANY_NAME', 'RQ_COMPANY_FULL_NAME',
					'RQ_OGRN', 'RQ_KPP', 'RQ_COMPANY_REG_DATE', 'RQ_OKPO', 'RQ_OKTMO',
					'RQ_DIRECTOR', 'RQ_ACCOUNTANT', 'RQ_ADDR'),
			));

			$rq = $rsContact->fetch();

			$arFilterContact = [
				'ufCrm56_1684841075' => $rq['RQ_INN']
			];

			$arParamsContact = [
				'entityTypeId' => 134,
				'select' => $arSelect,
				'order' => $arOrder,
				'filter' => $arFilterContact
			];

			$itemType134Contact = \Crest::Call("crm.item.list", $arParamsContact)['result']['items'][0];



			$arParams_Contact = [
				'entityTypeId'=>134,
				'id'=>$itemType134Contact["id"],
				'fields' => [
					'ufCrm56_1686288649629' => "Пополнение оборотных средств (ЛК)", //Прошлая цель из СП Сопровождение
					'ufCrm56_1699534520' => 'Y', //Повторный из лк
					'ufCrm56_1684917532850' => $date->format('Y-m-d'), //Дата получения займа(Текущая дата + 1)
					'ufCrm56_1684868548991' => $sumTransh, //Сумма повторного транша из ЛК
					'ufCrm56_1684822287575' => 'Y', //Создать заявку на повторный транш!
					'ufCrm56_1684917581455' => 11996
				]
			];
			$res = \Crest::Call("crm.item.update", $arParams_Contact);
*/
		}
		return true;
	}



}