<?php
use \Bitrix\Main\Web\JWT,
	\Bitrix\Rest\RestException;
use \KPLab\Logs;
\Bitrix\Main\Loader::includeModule('rest');
define("LOG_RESTTEST", $_SERVER['DOCUMENT_ROOT']."/logs/rest_test.log");
class RestTest extends \IRestService
{
	public static function OnRestServiceBuildDescription()
	{
		return array(
			'lists' => array(
				'lists.referral.get' => array(
					'callback' => array(__CLASS__, 'referralGetList'),
					'options' => array(),
				),
				'lists.seller.get' => array(
					'callback' => array(__CLASS__, 'sellerGetList'),
					'options' => array(),
				),
			),
			\CRestUtil::GLOBAL_SCOPE => array(
				'lk.auth.login' => array(
					'callback' => array(__CLASS__, 'authLogin'),
					'options' => array(),
				),
				'lk.auth.regInfo' => array(
					'callback' => array(__CLASS__, 'getRegisterInfo'),
					'options' => array(),
				)
			)
		);
	}

	public static function authLogin($query, $nav, \CRestServer $server) {
		if ($query['error'])
		{
			throw new RestException(
				'Message',
				'ERROR_CODE',
				\CRestServer::STATUS_PAYMENT_REQUIRED
			);
		}
		if(isset($query['id'])  ) {
			$q['id'] = $query['id'];
			if (isset($query['login']) && isset($query['password']))  {
				$q['login'] = $query['login'];
				$q['password'] = $query['password'];
			}
		} else {
			return [
				'error' => 'ERROR_METHOD_NOT_FOUND',
				'error_description' => 'Method not found! ' . __LINE__,
			];
		}




		$token['session'] = JWT::encode($q,"3lsxt0qfwbns0wve","HS256");
		return $token;
	}

	public static function getRegisterInfo($query, $nav, \CRestServer $server) {
		if ($query['error'] || empty($query['jwt_token']))
		{
			throw new RestException(
				'Message',
				'ERROR_CODE',
				\CRestServer::STATUS_PAYMENT_REQUIRED
			);
		}
		$jwt_token = $query["jwt_token"];
		$key = "3lsxt0qfwbns0wve";
		$authInfo[] = JWT::decode($jwt_token,$key,array('HS256'));

		return $authInfo;
	}

	public static function referralGetList($query, $nav, \CRestServer $server)
	{
		if ($query['error'])
		{
			throw new \Bitrix\Rest\RestException(
				'Message',
				'ERROR_CODE',
				\CRestServer::STATUS_PAYMENT_REQUIRED
			);
		}

		$arSelect = $query['select']?:['ID','NAME','CODE','ACTIVE_DATE','ACTIVE','IBLOCK_ID','IBLOCK_TYPE_ID'];
		$arOrder = $query['order']?:['ID' => 'ASC'];

		Bitrix\Main\Loader::includeModule('iblock');

		//$arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM");
		$arFilter = array("IBLOCK_ID"=>166, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
		$res = CIBlockElement::GetList($arOrder, $arFilter, false, array(), $arSelect);

		if (!$res)
		{
			return [
				'error' => 'ERROR_METHOD_NOT_FOUND',
				'error_description' => 'Method not found! ' . __LINE__,
			];
		}
		$result = array();
		while($el = $res->GetNextElement())
		{

			$arFields = $el->GetFields();
			$arProps = $el->GetProperties();
			$SUM_SCP_KB = 0;
			$SUM_DEALS = 0;
			if($arProps['SUM_SCP_KB']['VALUE'] !== "") $SUM_SCP_KB = str_replace('|RUB', '', $arProps['SUM_SCP_KB']['VALUE']);
			if($arProps['SUM_DEALS']['VALUE'] !== "") $SUM_DEALS = str_replace('|RUB', '', $arProps['SUM_DEALS']['VALUE']);

			$arProp['ASSIGN'] = $arProps['ASSIGN']['VALUE'];
			$arProp['COUNT_DEALS'] = $arProps['COUNT_DEALS']['VALUE'];
			$arProp['COUNT_LEADS'] = $arProps['COUNT_LEADS']['VALUE'];
			$arProp['ID_REFERRAL'] = $arProps['ID_REFERRAL']['VALUE'];
			$arProp['ID_SDELKI_SCP'] = $arProps['ID_SDELKI_SCP']['VALUE'];
			$arProp['INN_REFERRAL'] = $arProps['INN_REFERRAL']['VALUE'];
			$arProp['QR_KOD'] = $arProps['QR_KOD']['VALUE'];
			$arProp['QR_LINK'] = $arProps['QR_LINK']['VALUE'];
			$arProp['REFERRAL_LINK'] = $arProps['REFERRAL_LINK']['VALUE'];
			$arProp['SCP_KB'] = $arProps['SCP_KB']['VALUE'];
			$arProp['SUM_SCP_KB'] = number_format($SUM_SCP_KB,2,'.',' ');
			$arProp['SUM_DEALS'] = number_format($SUM_DEALS,2,'.',' ');
			$result[] = array_merge($arProp, $arFields);
		}

		return $result;
	}

	public static function sellerGetList($query, $nav, \CRestServer $server)
	{

		if ($query['error'])
		{
			throw new \Bitrix\Rest\RestException(
				'Message',
				'ERROR_CODE',
				\CRestServer::STATUS_PAYMENT_REQUIRED
			);
		}

		Bitrix\Main\Loader::includeModule('iblock');

		$leadFrom = date('2000-01-01');
		$leadTo = date('Y-m-d');
		$curM = date('m');
		$curY = date('Y');
		$dogDateStart = $curY.'-'.$curM.'-'.'01';
		$dogDateEnd = $curY.'-'.$curM.'-'.'31';

		$arSelect = $query['select']?:['ID','NAME','CODE','IBLOCK_ID'];
		$arOrder = $query['order']?:['ID' => 'ASC'];
		$arPeriod = $query['period'];
		$referralId = $query['referralId']?:[null];
		$arLeadDates = $query['leadDate'];
		$arStatusLead = $query['statusLead'];

		//\KPLab\Logs\File::AddMessage($query,"sellerGetList query",LOG_RESTTEST);



		if(!empty($arStatusLead)) {
			if($arStatusLead[0] == 'new') {
				$statusLead = "Новый";
			}elseif($arStatusLead[0] == 'in_processed') {
				$statusLead = "В обработке";
			}elseif( $arStatusLead[0] == 'issued') {
				$statusLead = "Выдан";
				//AddMessage2Log($statusLead, 'kplab.jwt sellerGetList $statusLead');
			}elseif( $arStatusLead[0] == 'refused') {
				$statusLead = "Отказ";
			} else {
				$statusLead = "";
			}
		} else {
			$statusLead = "";
		}

		$result = array();

		if($referralId == null) {
			if(!empty($arPeriod)) {
				if($arLeadDates['DATE_FROM'] == "" && $arLeadDates['DATE_TO'] == "") {
					if($statusLead == "")
					{
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							">=PROPERTY_DATA_VYDACHI_DZ" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_VYDACHI_DZ" => $arPeriod['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
					else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);

						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
				else {
					if($statusLead == "")
					{
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							//"::SUB_LOGIC" => "AND",
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO'],
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
					else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO'],
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
			}
			else {
				if($arLeadDates['DATE_FROM'] == "" && $arLeadDates['DATE_TO'] == "")
				{
					if($statusLead == "")
					{
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
					else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"PROPERTY_STATUS_VALUE" => $statusLead
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
				else {
					if($statusLead == "")
					{
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
					else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
			}
		}
		else {
			//\KPLab\Logs\File::AddMessage($referralId,"referralId не null",LOG_RESTTEST);
			if(!empty($arPeriod))
			{
				//\KPLab\Logs\File::AddMessage($arPeriod,"arPeriod !empty",LOG_RESTTEST);
				if($arLeadDates['DATE_FROM'] == "" && $arLeadDates['DATE_TO'] == "")
				{
					//\KPLab\Logs\File::AddMessage($statusLead,"statusLead 1",LOG_RESTTEST);
					if($statusLead == "") {
						$arFilterDeal = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilterDeal,
							false,
							array(),
							$arSelect
						);
					}
					else {
						$arFilterDeal = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilterDeal,
							false,
							array(),
							$arSelect
						);
						//Logs\File::AddMessage($statusLead,"statusLead Sellers",LOG_RESTTEST);
						//Logs\File::AddMessage($arPeriod['DATE_FROM'],"ОТ Sellers",LOG_RESTTEST);
						//Logs\File::AddMessage($arPeriod['DATE_TO'],"ДО Sellers",LOG_RESTTEST);
						//Logs\File::AddMessage($res,"result Sellers",LOG_RESTTEST);
					}
				}
				else {
					//\KPLab\Logs\File::AddMessage($statusLead,"statusLead 2",LOG_RESTTEST);
					if($statusLead == ""){
						$arFilterLead = array(
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$resLead = CIBlockElement::GetList(
							$arOrder,
							$arFilterLead,
							false,
							array(),
							$arSelect
						);

						$arFilterDeal = array(
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
						);
						$resDeal = CIBlockElement::GetList(
							$arOrder,
							$arFilterDeal,
							false,
							array(),
							$arSelect
						);

						if (!$resLead && !$resDeal)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}

						while($el = $resDeal->GetNextElement())
						{
							$arFields = $el->GetFields();
							$arProps = $el->GetProperties();

							$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
							$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
							$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
							$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
							$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
							$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
							$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
							$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
							$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
							$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
							$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
							$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
							if($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE']){
								$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) *intval($arProps['SCP_KB_LEAD']['VALUE'])/100;
							} else {
								$arProp['SUM_SCP_KB_LEAD'] = 0;
							}
							$resultDeals[] = array_merge($arProp, $arFields);
						}
						while($el = $resLead->GetNextElement())
						{
							$arFields = $el->GetFields();
							$arProps = $el->GetProperties();

							$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
							$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
							$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
							$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
							$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
							$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
							$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
							$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
							$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
							$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
							$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
							$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
							if($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE']){
								$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) *intval($arProps['SCP_KB_LEAD']['VALUE'])/100;
							} else {
								$arProp['SUM_SCP_KB_LEAD'] = 0;
							}
							$resultLeads[] = array_merge($arProp, $arFields);
						}
						$result = $resultLeads;
					}
					elseif($statusLead == "Выдан")
					{
						$arFilterDeal = array(
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
							"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
						);
						//AddMessage2Log($arFilterDeal, 'kplab.jwt sellerGetList $arFilterDeal Выдан $statusLead ');

						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilterDeal,
							false,
							array(),
							$arSelect
						);
						//AddMessage2Log($resDeal, 'kplab.jwt sellerGetList $resDeal Выдан $statusLead ');
						//$res = $resDeal;// array_merge($resLead,$resDeal);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}

					}
					else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
			}
			else {
				if($arLeadDates['DATE_FROM'] == "" && $arLeadDates['DATE_TO'] == "")
				{
					if($statusLead == "") {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
					} else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							"PROPERTY_STATUS_VALUE" => $statusLead
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
					}
				}
				else {
					if($statusLead == "")
					{
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					} else {
						$arFilter = array(
							"::LOGIC" => "AND",
							"IBLOCK_ID" => 167,
							"=PROPERTY_REFERRAL_ID" => $referralId,
							"PROPERTY_STATUS_VALUE" => $statusLead,
							">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
							"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
						);
						$res = CIBlockElement::GetList(
							$arOrder,
							$arFilter,
							false,
							array(),
							$arSelect
						);
						if (!$res)
						{
							return [
								'error' => 'ERROR_METHOD_NOT_FOUND',
								'error_description' => 'Method not found! ' . __LINE__,
							];
						}
					}
				}
			}
		}



		if(!empty($result)) {
			return $result;
		} else
		{
			while ($el = $res -> GetNextElement())
			{
				$arFields = $el -> GetFields();
				$arProps = $el -> GetProperties();

				$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
				$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
				$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
				$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
				$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
				$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
				$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
				$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
				$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
				$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
				$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
				$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
				if ($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'])
				{
					$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) * intval($arProps['SCP_KB_LEAD']['VALUE']) / 100;
				} else
				{
					$arProp['SUM_SCP_KB_LEAD'] = 0;
				}
				$result[] = array_merge($arProp, $arFields);
			}


			return $result;
		}
	}
}
