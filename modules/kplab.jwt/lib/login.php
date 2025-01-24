<?php namespace KPLab\JWT;

use Bitrix\Main\Web\JWT;
use KPLab\JWT\User;

class Login {

	public static function getUserToken($user)
	{
		$userData = \KPLab\JWT\User::getId($user);

		\Bitrix\Main\Loader::IncludeModule('crm');

		$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$userData['user_id'])))))), 0, 6);

		$arFilter = [
			'ID' => $userData['user_id']
		];

		$arSelect = ["ID","UF_CRM_1697853788600"];

		$companyInfo = \Bitrix\Crm\CompanyTable::GetList([
			'select' => $arSelect,
			'filter' => $arFilter
		])->fetch();

		if($companyInfo['UF_CRM_1697853788600'] !== $psw) {
			return 'No matches found';
		} else {
			return JWT::encode($userData,"3lsxt0qfwbns0wve","HS256");
		}
	}
}
