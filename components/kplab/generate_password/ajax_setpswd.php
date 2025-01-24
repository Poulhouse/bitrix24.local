<?php
use Bitrix\Main\Application;
use Bitrix\Main\Error;

require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");

\CBitrixComponent::includeComponentClass("kplab:generate_password");

\Bitrix\Main\Loader::includeModule('kplab.jwt');

$companyId = $_POST['companyId'];
//$dataJSON = json_encode($data);

$context = Application::getInstance()->getContext();
$request = $context->getRequest();
$server = $context->getServer();

$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);
$arFields = [
	"UF_CRM_1697853788600" => $psw
];

$companyUpdate = \CRest::Call("crm.company.update",['id'=>$companyId,'fields'=>$arFields]);

$dataG['psw'] = $psw;
$dataG['id'] = $companyId;
$dataG['error'] = 'Ошибок нет';

echo json_encode($dataG);

