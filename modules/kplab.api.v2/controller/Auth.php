<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\Web\JWT;
use KPLab\JWT\Controller\ActionFilter\Authentication;

class Auth extends \Bitrix\Main\Engine\Controller
{
	private $login;

	public function getDefaultPreFilters()
	{
		return [
			new Authentication()
		];
	}

	protected function prepareParams()
	{
		$this->login = new \KPLab\JWT\Login();
		return parent::prepareParams();
	}

	public function jwtAction(array $params = [])
	{
		\Bitrix\Main\Loader::IncludeModule('crm');

		$request = Application::getInstance()->getContext()->getRequest();

		$data = json_decode($request->getInput(),true);

		if (empty($data['phone']) || empty($data['password']))
		{
			$this->addError(new Error('Data is empty.', 0));
			return null;
		}

		$companyId = null;

		$info = \KPLab\JWT\User::getId($data);

		if($info['TYPE'] == 'COMPANY')
		{
			$companyId = $info['ID'];

			if ($companyId === null)
			{
				$this -> addError(new Error('Неверный логин или пароль', 400));
				return null;
			}
			//AddMessage2Log($info, "API companyId jwtAction");

			// Ищем ID контрагента с таким же номером телефона
			/*$_arFilter = [
				'=PHONE' => $data['phone']
			];

			$_arSelect = ["ID"];

			$companyId = \Bitrix\Crm\CompanyTable::GetList([
				'select' => $_arSelect,
				'filter' => $_arFilter
			])->fetch()['ID'];
			*/
			//AddMessage2Log($companyId, "API companyId jwtAction");

			$psw = substr(preg_replace('~\D+~', '', md5(md5(md5(md5(preg_replace('~\D+~', '', $companyId)))))), 0, 6);

			//AddMessage2Log($psw, "API Psw jwtAction");

			// Ищем контрагента с таким же паролем
			$__arFilter = [
				'=UF_CRM_1697853788600' => $data['password']
			];

			$__arSelect = ["ID", "UF_CRM_1697853788600"];

			$companyPsw = \Bitrix\Crm\CompanyTable ::GetList([
				'select' => $__arSelect,
				'filter' => $__arFilter
			]) -> fetch();

			//AddMessage2Log($companyPsw, "API companyPsw jwtAction");

			if ($companyPsw['UF_CRM_1697853788600'] !== $psw)
			{
				$this -> addError(new Error('Неверный логин или пароль', 0));
				return null;
			} else
			{
				return JWT ::encode($companyPsw['ID'], "3lsxt0qfwbns0wve", "HS256");
			}
			
		} elseif($info['TYPE'] == 'CONTACT') {
			$this->addError(new Error($info['error'], 400));
			return null;
		} elseif($info['error']) {
			$this->addError(new Error($info['error'], 400));
			return null;
		}
	}
}