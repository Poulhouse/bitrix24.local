<?php

use Bitrix\Main\Application;
use Bitrix\Main\Error;

require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");

\CBitrixComponent::includeComponentClass("kplab:generate_password");

\Bitrix\Main\Loader::includeModule('kplab.jwt');

$data['phone'] = $_POST['phone'];
//$dataJSON = json_encode($data);

$context = Application::getInstance()->getContext();
$request = $context->getRequest();
$server = $context->getServer();
//print_r($data);
$info = \KPLab\JWT\User::getId($data);

//print_r($info);

//AddMessage2Log(date('Y-m-d',$server['REQUEST_TIME']).'T'.date('H:i:s',$server['REQUEST_TIME']),'Y-m-dTH:i:s');


if($info['TYPE'] == 'COMPANY') {
	$companyId = $info['ID'];
	$company = new \CCrmCompany(false);

	$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);

	$companyBU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];



	if(isset($companyBU['UF_CRM_1699900326048']) && $companyBU['UF_CRM_1699900326048'] !== '') {
		$arFields = [
			"UF_CRM_1697853788600" => $psw,
			"UF_CRM_1699900252754" => $server['REQUEST_TIME']
		];
	} else {
		$arFields = [
			"UF_CRM_1697853788600" => $psw,
			"UF_CRM_1699900252754" => $server['REQUEST_TIME']+1,
			"UF_CRM_1699900326048" => $server['REQUEST_TIME']
		];
	}

	$companyUpdate = \CRest::Call("crm.company.update",['id'=>$companyId,'fields'=>$arFields]);

	//$company->Update($companyId,$arFields);

	$companyAU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];

	//AddMessage2Log($companyAU["UF_CRM_1699900252754"],'$companyAU - новая ');
	//AddMessage2Log($companyAU["UF_CRM_1699900326048"],'$companyAU - старая ');
	$dataG['psw'] = $psw;
	$dataG['id'] = $companyId;
	$dataG['error'] = 'Ошибок нет';


} elseif($info['TYPE'] == 'CONTACT') {
	//$this->addError(new Error('Указан не верный номер', 400));
	$dataG['error'] = 'Указан неверный номер';
} elseif($info['error']) {
	//$this->addError(new Error('Указан не верный номер', 400));
	$dataG['error'] = 'Указан неверный номер';
}

echo json_encode($dataG);
