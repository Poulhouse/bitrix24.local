<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use \KPLab\Logs;
use \KPLab\SellerEngine;

define("LOG_API_DOC_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DocumentsController.log");

class Documents extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\Controller\ActionFilter\Authentication(),
		];
	}

	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		return parent ::prepareParams();
	}

	public function postBackAction(array $params = [])
	{
		$data = null;
		$timeData = Logs\TimeData ::start();

		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();
		$serverArray = $server->toArray();
		$serverName = $serverArray['SERVER_NAME'];

		$point = "SE_BX";
		$url = $server['SCRIPT_URI'];
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		$headers = $request->getHeaders();
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}


		\Bitrix\Main\Loader ::IncludeModule('crm');

		$requestArray = json_decode($request -> getInput(), true);

		if ($requestArray == NULL)
		{
			$errorMessage = 'Тело запроса не удалось декодировать как JSON.';

			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат проверки паспорта: {$errorMessage}";

			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_json"));
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);

			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		if($requestArray["status"] == "error") {
			$errorMessage = $requestArray["error"];
			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат проверки паспорта: {$errorMessage}";

			Context ::getCurrent() -> getResponse() -> setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_request"));
			Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		if(isset($requestArray['passports'])){
			if (empty($requestArray['passports']))
			{
				$errorMessage = 'Этот запрос не поддерживается. Пустой `passports`';

				$jsonRes['success'] = null;
				$jsonRes['error'][] = $errorMessage;
				$objectData['ITEM_TITLE'] = "Результат проверки паспорта: {$errorMessage}";

				Context ::getCurrent() -> getResponse() -> setStatus(400);
				$this -> addError(new Error($errorMessage, "invalid_request"));
				Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
				return new EventResult(EventResult::ERROR, null, null, $this);
			}
			elseif (!empty($requestArray['passports']))
			{

				$surname = $requestArray['passports'][0]["surname"];
				$name = $requestArray['passports'][0]["name"];
				$patronymic = $requestArray['passports'][0]["patronymic"];
				$title = $surname . " " . $name ." " . $patronymic;
				$objectData['ITEM_TITLE'] = "Результат проверки паспорта: {$title}";

				$authorization = $server -> get('REMOTE_USER');
				$token = str_replace('BitrixAuth ', '', $authorization);

				Loader ::includeModule('iblock');
				if($serverName == "crm.sodeistvie.su") {
					$IBLOCK_ID = 183;
					$arOrder = ['ID' => 'ASC'];
					$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1112" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
					$arGroupBy = false;
					$arNavStartParams = [];
					$arSelect = ["*", "PROPERTY_*"];
					$res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);
				}
				elseif ($serverName == "testcrm.sodeistvie.su") {
					$IBLOCK_ID = 188;
					$arOrder = ['ID' => 'ASC'];
					$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_1109" => $token, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y"];
					$arGroupBy = false;
					$arNavStartParams = [];
					$arSelect = ["*", "PROPERTY_*"];
					$res = \CIBlockElement ::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelect);
				}
				while ($ob = $res -> GetNextElement())
				{
					$arProps = $ob -> GetProperties();
					$data = self::addPassportData($this, $requestArray);
				}
				if($data === null) {
					$dataArray["status"] = "success";
					$dataArray["data"] = null;
					$dataArray["errors"] = [];
				} else {
					$dataArray = $data;
				}
				$jsonRes['success'] = json_encode($dataArray);
				$jsonRes['error'] = "";

				Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
			}
		}

		return $data;
	}
	
	public static function addPassportData($controller, $requestArray)
	{
		$data = null;
		$entityTypeId = \CCrmOwnerType::Company;
		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId);
		if (!$factory)
		{
			Context ::getCurrent() -> getResponse() -> setStatus(500);
			$controller -> addError(new Error('Ошибка на сервере', "invalid_server"));
			return new EventResult(EventResult::ERROR, null, null, $controller);
		}
		foreach ($requestArray['passports'] as $k => $passport)
		{
			if(empty($passport['documentPackageId'])) {
				Context ::getCurrent() -> getResponse() -> setStatus(400);
				$controller -> addError(new Error('Этот запрос не поддерживается. Пустой `documentPackageId`', "invalid_json"));
				return new EventResult(EventResult::ERROR, null, null, $controller);
			}

			$packageId = $passport['documentPackageId'];

			Logs\File::AddMessage($packageId,"documentPackageId",LOG_API_DOC_CONTROLLER);

			$parameters = [
				'select' => ['*','UF_*'],
				'filter' => [
					'UF_DOCUMENT_PACKAGE_ID' => $packageId
				]
			];

			$items = \CRest::call("crm.company.list", $parameters)['result'];

			if(empty($items)) {
				$data[$k][$packageId] = 'not_found';

				Context ::getCurrent() -> getResponse() -> setStatus(404);
				$controller -> addError(new Error("Данный documentPackageId ({$packageId}) - не найден", "invalid_json"));
				return new EventResult(EventResult::ERROR, null, null, $controller);
			}
			else {

				foreach ($items as $item)
				{
					$companyId = $item['ID'];

					if (!empty($companyId))
					{
						$companyTitle = $item['TITLE'];
						$companyJuristForm = $item['UF_CRM_1684145100226'];
						Logs\File ::AddMessage($companyJuristForm, "companyJuristForm", LOG_API_DOC_CONTROLLER);
						$rsEnum = \CUserFieldEnum ::GetList(array(), array(
							"ID" => $companyJuristForm,
						));

						if ($arEnum = $rsEnum -> Fetch())
						{
							Logs\File ::AddMessage($arEnum['XML_ID'], "companyJuristForm XML_ID", LOG_API_DOC_CONTROLLER);
							if ($arEnum['XML_ID'] == "ORG")
							{
								Context ::getCurrent() -> getResponse() -> setStatus(500);
								$controller -> addError(new Error('Ошибка на сервере (Организация не может иметь реквизит паспорт физ-лица)',
									"invalid_server"));
								return new EventResult(EventResult::ERROR, null, null, $controller);
								//$PRESET_ID = 1;
							}
							elseif ($arEnum['XML_ID'] == "IP") {
								$PRESET_ID = 2;
							}
							elseif ($arEnum['XML_ID'] == "FL") {
								$PRESET_ID = 3;
							}
						}

						$resRQItem = \CRest::call("crm.requisite.list",[
							"order"=>["DATE_CREATE" => "ASC"],
							"filter"=>["XML_ID"=>$packageId,"ENTITY_ID"=>$companyId],
							"select"=>[ "*" ]
						])['result'];

						if(!empty($resRQItem)) {
							$resultRequisite = \CRest ::call(
								'crm.requisite.update',
								[
									"id" => $resRQItem[0]['ID'],
									"fields" => [
										'TITLE' => $companyTitle,
										'NAME' => $passport['surname'] . " " . $passport['name'] . " " . $passport['patronymic'],
										'RQ_NAME' => $passport['surname'] . " " . $passport['name'] . " " . $passport['patronymic'],
										'RQ_FIRST_NAME' => $passport['name'],
										'RQ_LAST_NAME' => $passport['surname'],
										'RQ_SECOND_NAME' => $passport['patronymic'],
										'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
										'RQ_IDENT_DOC_SER' => $passport['passportSeries'],
										'RQ_IDENT_DOC_NUM' => $passport['passportNumber'],
										'RQ_IDENT_DOC_DATE' => $passport['passportIssuedAt'],
										'RQ_IDENT_DOC_ISSUED_BY' => $passport['passportIssuer'],
										'RQ_IDENT_DOC_DEP_CODE' => $passport['passportIssuerCode'],
										'UF_CRM_1647929611' => $passport['birthPlace'],
										'UF_CRM_1684493639' => $passport['birthday']
									]
								]
							);
							Logs\File ::AddMessage($resRQItem[0]['ID'], "resRQItemID", LOG_API_DOC_CONTROLLER);
							$registrationAddressFias = $passport["registrationAddressFias"];
							$arAddress['ENTITY_ID'] = $resRQItem[0]['ID'];//id requisite
							$arAddress['TYPE_ID'] = 4;//Адрес регистрации
							$arAddress['ENTITY_TYPE_ID'] = \CCrmOwnerType::Requisite;//Реквизит 8
							$arAddress['ANCHOR_ID'] = $companyId;// ID Компании
							$arAddress['POSTAL_CODE'] = $registrationAddressFias['postal_code'];// Индекс
							$arAddress['COUNTRY'] = $registrationAddressFias['country'];// Страна
							$arAddress['PROVINCE'] = $registrationAddressFias['region_with_type'];// регион
							$arAddress['REGION'] = $registrationAddressFias['city_district_with_type'];// Район
							$arAddress['CITY'] = $registrationAddressFias['city_with_type'];// Город
							$arAddress['ADDRESS_1'] = $registrationAddressFias['street_with_type'] . ", " .
								$registrationAddressFias['house_type']." ".$registrationAddressFias['house']; //Улица, дом, корпус, строение.
							$arAddress['ADDRESS_2'] = $registrationAddressFias['flat_type']." ".$registrationAddressFias['flat'];// Квартира / офис.
							$arAddress['COUNTRY_CODE'] = 643;// Код страны
							$resultAddress = \CRest ::call('crm.address.add', ['fields' => $arAddress]);

							self::addressUpdate($companyId, \CCrmOwnerType::Company, $registrationAddressFias['street_with_type'], 'STREET',4);
							self::addressUpdate($companyId, \CCrmOwnerType::Company, $registrationAddressFias['house_type']." ".$registrationAddressFias['house'], 'BUILDING',4);
						}
						else {
							$resultRequisite = \CRest ::call(
								'crm.requisite.add',
								[
									'fields' => [
										'ENTITY_TYPE_ID' => 4,//4 - is company in CRest::call('crm.enum.ownertype');
										'ENTITY_ID' => $companyId,//company id
										'PRESET_ID' => $PRESET_ID, //Организация
										'TITLE' => $companyTitle,
										'XML_ID' => $packageId,
										'ACTIVE' => 'Y',
										'NAME' => $passport['surname'] . " " . $passport['name'] . " " . $passport['patronymic'],
										'RQ_NAME' => $passport['surname'] . " " . $passport['name'] . " " . $passport['patronymic'],
										'RQ_FIRST_NAME' => $passport['name'],
										'RQ_LAST_NAME' => $passport['surname'],
										'RQ_SECOND_NAME' => $passport['patronymic'],
										'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
										'RQ_IDENT_DOC_SER' => $passport['passportSeries'],
										'RQ_IDENT_DOC_NUM' => $passport['passportNumber'],
										'RQ_IDENT_DOC_DATE' => $passport['passportIssuedAt'],
										'RQ_IDENT_DOC_ISSUED_BY' => $passport['passportIssuer'],
										'RQ_IDENT_DOC_DEP_CODE' => $passport['passportIssuerCode'],
										'UF_CRM_1647929611' => $passport['birthPlace'],
										'UF_CRM_1684493639' => $passport['birthday']
									]
								]
							);
							Logs\File ::AddMessage($resultRequisite['result'], "resultRequisiteAdd",
								LOG_API_DOC_CONTROLLER);

							if (!empty($resultRequisite['result']))
							{
								$registrationAddressFias = $passport["registrationAddressFias"];
								$arAddress['ENTITY_ID'] = $resultRequisite['result'];//id requisite
								$arAddress['TYPE_ID'] = 4;//Адрес регистрации
								$arAddress['ENTITY_TYPE_ID'] = \CCrmOwnerType::Requisite;//Реквизит 8
								$arAddress['ANCHOR_ID'] = $companyId;// ID Компании
								$arAddress['POSTAL_CODE'] = $registrationAddressFias['postal_code'];// Индекс
								$arAddress['COUNTRY'] = $registrationAddressFias['country'];// Страна
								$arAddress['PROVINCE'] = $registrationAddressFias['region_with_type'];// регион
								$arAddress['REGION'] = $registrationAddressFias['city_district_with_type'];// Район
								$arAddress['CITY'] = $registrationAddressFias['city_with_type'];// Город
								$arAddress['ADDRESS_1'] = $registrationAddressFias['street_with_type'] . ", " .
									$registrationAddressFias['house_type']." ".$registrationAddressFias['house']; //Улица, дом, корпус, строение.
								$arAddress['ADDRESS_2'] = $registrationAddressFias['flat_type']." ".$registrationAddressFias['flat'];// Квартира / офис.
								$arAddress['COUNTRY_CODE'] = 643;// Код страны
								$resultAddress = \CRest ::call('crm.address.add', ['fields' => $arAddress]);

								self::addressUpdate($companyId, \CCrmOwnerType::Company, $registrationAddressFias['street_with_type'], 'STREET',4);
								self::addressUpdate($companyId, \CCrmOwnerType::Company, $registrationAddressFias['house_type']." ".$registrationAddressFias['house'], 'BUILDING',4);
							}
						}
					}
				}
			}
		}

		return $data;
	}

	public static function addressUpdate($id, $entityTypeId, $dataField, $nameField, $typeId) {
		global $DB;
		$Address = new \Bitrix\Location\Controller\Address;


		$resAddrList = CRest::call('crm.address.list', array(
			'filter' => array('ANCHOR_ID' => $id, 'ANCHOR_TYPE_ID' => $entityTypeId),
			'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','ANCHOR_TYPE_ID','LOC_ADDR_ID')
		))['result'];

		if(empty($resAddrList)) return false;

		foreach($resAddrList as $i => $addrItem){
			if($addrItem['TYPE_ID'] == $typeId)
			{
				$LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];

				$beforeStrSQL = "SELECT * FROM b_location_addr_fld WHERE ADDRESS_ID = ".$LOC_ADDR_ID;
				$beforeResults = $DB->Query($beforeStrSQL);

				if($dataField !== "" && $nameField == "STREET")
				{
					$streetBool = false;
					if (intval($beforeResults->SelectedRowsCount())>0)
					{
						while ($location_addr_fld = $beforeResults->Fetch()){
							if($location_addr_fld['TYPE'] == 340)
								$streetBool = true;
						}

					}

					$streetTMP = str_replace(" ", "", $dataField);
					$streetTMP = str_replace(".", "", $streetTMP);
					$streetTMP = str_replace(",", "", $streetTMP);
					$streetUPPER = strtoupper($streetTMP);


					if(!$streetBool)
					{
						$strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.",340,'".$dataField."','".$streetUPPER."')";
						//AddMessage2Log($strSQL, 'SQL STREET');

					} else
					{
						$strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 340";
						//AddMessage2Log($strSQL, 'SQL STREET UPDATE');
					}
					$DB->Query($strSQL);

				}

				if($dataField !== "" && $nameField == "BUILDING")
				{
					$houseBool = false;
					if (intval($beforeResults->SelectedRowsCount())>0)
					{
						while ($location_addr_fld = $beforeResults->Fetch()){
							if($location_addr_fld['TYPE'] == 400)
								$houseBool = true;
						}

					}
					//$house = $dataField.' д.';
					$houseTMP = str_replace(" ", "", $dataField);
					$houseTMP = str_replace(".", "", $houseTMP);
					$houseTMP = str_replace(",", "", $houseTMP);
					$houseUPPER = strtoupper($houseTMP);

					if(!$houseBool)
					{
						$strSQL = "INSERT INTO b_location_addr_fld VALUES (" . $LOC_ADDR_ID . ",400,'" . $dataField . "','" . $houseUPPER . "')";
						//AddMessage2Log($strSQL, 'SQL BUILDING');
					} else {
						$strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 400";
						//AddMessage2Log($strSQL, 'SQL BUILDING UPDATE');
					}

					$DB->Query($strSQL);
				}
				//AddMessage2Log($strSQL, 'SQLALL');
			}

			//$DB->Query("INSERT INTO b_location_addr_fld (ADDRESS_ID, TYPE, VALUE, VALUE_NORMALIZED) VALUES ({$LOC_ADDR_ID},400,'{$house}','{$houseUPPER}')");

			$addrId = $Address->findById($addrItem['LOC_ADDR_ID']);
			$resAddress[$i] = $addrId['fieldCollection'];

			if(!empty($resAddress[$i][340]) && !empty($resAddress[$i][400])) {
				\CRest::call('crm.address.update',	array(
					'fields' => array(
						'TYPE_ID' => $addrItem['TYPE_ID'],
						'ENTITY_TYPE_ID' => $addrItem['ENTITY_TYPE_ID'],
						'ENTITY_ID' => $addrItem['ENTITY_ID'],
						'LOC_ADDR_ID' => $addrItem['LOC_ADDR_ID'],
						'POSTAL_CODE' => $resAddress[$i][50],//Почтовый индекс
						'COUNTRY' => $resAddress[$i][100],//Страна
						'PROVINCE' => $resAddress[$i][200],//Регион
						'REGION' => $resAddress[$i][210],//Район
						'CITY' => $resAddress[$i][300],//Город+Населенный пункт
						'STREET' => $resAddress[$i][340],//Улица
						'BUILDING' => $resAddress[$i][400],//Номер дома
						'ADDRESS_1' => $resAddress[$i][340].', '.$resAddress[$i][400],
						'ADDRESS_2' => $resAddress[$i][600]
					)
				));
			}
		}

		return true;
	}
}