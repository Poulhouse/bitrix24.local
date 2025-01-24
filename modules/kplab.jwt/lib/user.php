<?php namespace KPLab\JWT;

use Bitrix\Main\Web\JWT;
use \Bitrix\Main\Application;
use \Bitrix\Main\Error;
use \Bitrix\Recyclebin\Internals\Models\RecyclebinTable;
use \Bitrix\Recyclebin\Recyclebin;
use \Bitrix\Main\Loader;
use KPLab\Logs;

//require_once ($_SERVER['DOCUMENT_ROOT'] .'/crest/crest.php');

define("LOG_USER_JWT", $_SERVER['DOCUMENT_ROOT']."/local/classes/jwt.user.log");
class User
{
	public static function getNameAndLastName(array $filter)
	{
		$result = \CUser::GetList('', '', $filter);

		while ($row = $result->Fetch()) {
			$data[$row['ID']] = $row['NAME'] . ' ' . $row['LAST_NAME'];
		}

		if(!empty($data)){
			$res = $data;
		} else {
			$res = [];
		}

		return $res;
	}

	public static function getId(array $filter) {

		\Bitrix\Main\Loader::IncludeModule('crm');

		$arFilter = [
			'UF_CRM_1706612347' => $filter['phone'], //Login LK
			'UF_CRM_1697853788600' => $filter['password']
		];

		$arSelect = ["ID","UF_CRM_1706612347","UF_CRM_1697853788600"];

		$arParams  = [
			'filter' => $arFilter,
			'select' => $arSelect
		];

		$inf = \CRest::Call("crm.company.list",$arParams)['result'];
		//Logs\File::AddMessage($inf,"inf", LOG_USER_JWT);


		if($inf)
		{

			foreach ($inf as $companyInfo)
			{
				$info['ID'] = $companyInfo['ID'];
				$info['TYPE'] = 'COMPANY';
				$info['error'] = '';
				return $info;
			}
		} else
		{
			$info['TYPE'] = 'CONTACT';
			$info['error'] = 'Указан неверный номер';
			return $info;
		}

	}

	public function generateCodePsw($phoneNumber)
	{
		$data['phone'] = $phoneNumber;
		$info = \KPLab\JWT\User::getId($data);
		if($info['TYPE'] == 'COMPANY') {
			$companyId = $info['ID'];
			$company = new \CCrmCompany(false);

			$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);

			$arFields = [
				"UF_CRM_1697853788600" => $psw
			];
			$company->Update($companyId,$arFields);
			return $psw;
		} elseif($info['TYPE'] == 'CONTACT') {
			return null;
		} elseif($info['error']) {
			return null;
		}
	}

	public static function getProfile(array $filter) {

		\Bitrix\Main\Loader::IncludeModule('crm');



		$arFilter = [
			'=ID' => $filter['id']
		];
		$arSelect = ["ID","LEAD_ID","TITLE","PHONE","EMAIL","ADDRESS","COMPANY_TYPE","UF_CRM_1595595411835"];

		$companyInfo = \Bitrix\Crm\CompanyTable::GetList([
			'select' => $arSelect,
			'filter' => $arFilter
		])->fetch();



		$result["id"] = $companyInfo['ID'];
		$result["fullName"] = $companyInfo['UF_CRM_1595595411835'];
		$result["shortName"] = $companyInfo['TITLE'];
		$result["lastName"] = null;/*str_replace(' ', '', $leadInfo['LAST_NAME']);*/
		$result["firstname"] = null;/*str_replace(' ', '', $leadInfo['NAME']);*/
		$result["secondname"] = null;/*str_replace(' ', '', $leadInfo['SECOND_NAME']);*/
		$result["phone"] = $companyInfo['PHONE'];
		$result["email"] = $companyInfo['EMAIL'];

		return $result;
	}

	public static function deleteItems1Agent($id,$counters) {
		$entityTypeId = $id;

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

		$items1 = $factory->getItems(['limit'=>$counters,'offset'=>1]);
		//$items2 = $factory->getItems(['limit'=>$counters,'offset'=>$counters+1]);

		//AddMessage2Log("Агент включился");
		foreach ($items1 as $el1)
		{

			$item1 = $factory->getItem($el1->getId());
			//AddMessage2Log($el1->getId(),"deleteItems1Agent");

			if (isset($item1))
			{
				// Step 1: get operation
				$operation1 = $factory->getDeleteOperation($item1);

				// Step 2: config operation (optional)
				$operation1
					->disableCheckAccess()
				;

				// Step 3: launch operation
				$operationResult1 = $operation1->launch();

				if ( $operationResult1->isSuccess() )
				{
					/**
					 * Operation success
					 */
					//AddMessage2Log("Агент удалил {$el['id']}");
				}
				else
				{
					/**
					 * Operation failed with error
					 *
					 * @operationResult->getErrors();
					 * @operationResult->getErrorMessages();
					 */
					AddMessage2Log($operationResult1->getErrorMessages());
				}
			}
		}


		//AddMessage2Log("Агент выключился");
		//AddMessage2Log("Агент Выключился");
		return "\KPLab\JWT\User::deleteItems1Agent({$id},{$counters});";
	}

	public static function deleteItems2Agent($id,$counters) {
		$entityTypeId = $id;

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

		$items1 = $factory->getItems(['limit'=>$counters,'offset'=>$counters+1]);

		//$items2 = $factory->getItems(['limit'=>$counters,'offset'=>$counters+1]);

		//AddMessage2Log("Агент включился");
		foreach ($items1 as $el1)
		{

			$item1 = $factory->getItem($el1->getId());
			//AddMessage2Log($el1->getId(),"deleteItems2Agent");
			if (isset($item1))
			{
				// Step 1: get operation
				$operation1 = $factory->getDeleteOperation($item1);

				// Step 2: config operation (optional)
				$operation1
					->disableCheckAccess()
				;

				// Step 3: launch operation
				$operationResult1 = $operation1->launch();

				if ( $operationResult1->isSuccess() )
				{
					/**
					 * Operation success
					 */
					//AddMessage2Log("Агент удалил {$el['id']}");
				}
				else
				{
					/**
					 * Operation failed with error
					 *
					 * @operationResult->getErrors();
					 * @operationResult->getErrorMessages();
					 */
					AddMessage2Log($operationResult1->getErrorMessages());
				}
			}
		}


		//AddMessage2Log("Агент выключился");
		//AddMessage2Log("Агент Выключился");
		return "\KPLab\JWT\User::deleteItems2Agent({$id},{$counters});";
	}

	public static function deleteItems3Agent($id,$counters) {
		$entityTypeId = $id;

		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

		$offset = 2*$counters+2;

		$items1 = $factory->getItems(['limit'=>$counters,'offset'=>$offset]);

		AddMessage2Log("Агент3 включился");
		foreach ($items1 as $el1)
		{

			$item1 = $factory->getItem($el1->getId());
			//AddMessage2Log($el1->getId(),"deleteItems3Agent");
			if (isset($item1))
			{
				// Step 1: get operation
				$operation1 = $factory->getDeleteOperation($item1);

				// Step 2: config operation (optional)
				$operation1
					->disableCheckAccess()
				;

				// Step 3: launch operation
				$operationResult1 = $operation1->launch();

				if ( $operationResult1->isSuccess() )
				{
					/**
					 * Operation success
					 */
					//AddMessage2Log("Агент удалил {$el['id']}");
				}
				else
				{
					/**
					 * Operation failed with error
					 *
					 * @operationResult->getErrors();
					 * @operationResult->getErrorMessages();
					 */
					AddMessage2Log($operationResult1->getErrorMessages());
				}
			}
		}


		//AddMessage2Log("Агент выключился");
		AddMessage2Log("Агент3 Выключился");
		return "\KPLab\JWT\User::deleteItems3Agent({$id},{$counters});";
	}
	
	public static function deleteFromRecycle($MODULE_ID,$limit) {

		Loader::includeModule('recyclebin');

		//AddMessage2Log("Агент включился");
		$MODULE = "crm";
		
		if($MODULE_ID == 1) {
			$MODULE = "crm";
		}
		if($MODULE_ID == 2) {
			$MODULE = "tasks";
		}

		AddMessage2Log($MODULE_ID,"Модуль={$MODULE}");

		$list = RecyclebinTable::getList([
				'limit'  => $limit,
				'filter' => ['MODULE_ID' =>  $MODULE ],
				'order' => ['ID' => 'ASC'],
				'select' => ['ID']
			]
		)->fetchAll();

		//AddMessage2Log($list,"deleteFromRecyclebinCRM list limit={$limit}");

		foreach ($list as $item) {
			Recyclebin::remove(intval($item['ID']), ['skipAdminRightsCheck' => true]);
		}

		//AddMessage2Log("Агент выключился");
		//AddMessage2Log("Агент Выключился");
		return "\KPLab\JWT\User::deleteFromRecycle({$MODULE_ID},{$limit});";
	}


}