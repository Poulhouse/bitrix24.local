<?php namespace KPLab\JWT\Controller;

use \Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use \KPLab\JWT\Controller\ActionFilter\Authentication;
use \Bitrix\Main\Application;
use Bitrix\Main\Web\JWT;

require_once ($_SERVER['DOCUMENT_ROOT'] .'/crest/crest.php');

class User extends \Bitrix\Main\Engine\Controller
{
	private $user;

	public function getDefaultPreFilters()
	{
		return [
			new Authentication()
			/*new ActionFilter\HttpMethod(
				[
					ActionFilter\HttpMethod::METHOD_GET,
					ActionFilter\HttpMethod::METHOD_POST
				]
			)*/
		];
	}

	protected function prepareParams()
	{
		$this->user = new \KPLab\JWT\User();
		return parent::prepareParams();
	}

	public function getIdAction(array $params = [])
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		$authorization = $server->get('REMOTE_USER');
		$token = str_replace('Bearer ', '', $authorization);

		$responseDecodeJWT = JWT::decode($token,"3lsxt0qfwbns0wve",["HS256"]);
		$userid = $responseDecodeJWT;

		return $userid;
	}

	public function getProfileAction(array $params = [])
	{
		$request = Application::getInstance()->getContext()->getRequest();
		$data = json_decode($request->getInput(),true);

		return $this->user->getProfile($data);
	}

	public function codePswAction(array $params = [])
	{
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$server = $context->getServer();

		$data = json_decode($request->getInput(),true);

		//date('d.m.Y H:i:s',$server['REQUEST_TIME']);

		$info = \KPLab\JWT\User::getId($data);

		//AddMessage2Log(date('Y-m-d',$server['REQUEST_TIME']).'T'.date('H:i:s',$server['REQUEST_TIME']),'Y-m-dTH:i:s');

		if($info['TYPE'] == 'COMPANY') {
			$companyId = $info['ID'];
			$company = new \CCrmCompany(false);

			$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);

			$companyBU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];

			if(isset($companyBU['UF_CRM_1699900326048']) && $companyBU['UF_CRM_1699900326048'] !== '') {
				$arFields = [
					"UF_CRM_1697853788600" => $psw,
					"UF_CRM_1699900252754" => $server['REQUEST_TIME'],
					"UF_CRM_1706777861" => "Y"
				];
			} else {
				$arFields = [
					"UF_CRM_1697853788600" => $psw,
					"UF_CRM_1699900252754" => $server['REQUEST_TIME']+1,
					"UF_CRM_1699900326048" => $server['REQUEST_TIME'],
					"UF_CRM_1706777861" => "Y"
				];
			}

			$companyUpdate = \CRest::Call("crm.company.update",['id'=>$companyId,'fields'=>$arFields]);

			//$company->Update($companyId,$arFields);

			$companyAU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];

			//AddMessage2Log($companyAU["UF_CRM_1699900252754"],'$companyAU - новая ');
			//AddMessage2Log($companyAU["UF_CRM_1699900326048"],'$companyAU - старая ');

			return $psw;
		} elseif($info['TYPE'] == 'CONTACT') {
			$this->addError(new Error('Указан неверный номер', 400));
			return null;
		} elseif($info['error']) {
			$this->addError(new Error('Указан неверный номер', 400));
			return null;
		}
	}


/*
	public function registerAction(array $params = [])
	{
		$request = Application::getInstance()->getContext()->getRequest();

		return $this->user->uRegistration($request["username"],$request["phone"],$request["password"]);
	}
*/

}