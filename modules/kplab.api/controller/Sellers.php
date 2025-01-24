<?php namespace KPLab\API\Controller;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/SellersController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");

class Sellers extends \Bitrix\Main\Engine\Controller
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
        return parent::prepareParams();
    }

	/**
	 * @throws ArgumentNullException
	 * @throws LoaderException
	 * @throws ArgumentOutOfRangeException
	 */
	public function setAction(array $params = []): string|EventResult
	{
		//region Подготовка к обработке запроса
        $timeData = Logs\TimeData::start();

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
        $requestArray = json_decode($request->getInput(),true);
		//endregion
		//region Обработка ошибок
        if($requestArray == NULL) {
	        $errorMessage = 'Тело запроса не удалось декодировать как JSON.';
	        $jsonRes['success'] = null;
	        $jsonRes['error'][] = $errorMessage;
	        $objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$errorMessage}";
	        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error($errorMessage, "invalid_json"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
        if(empty($requestArray['sellerInn'])) {
	        $errorMessage = 'Этот запрос не поддерживается. Пустой `sellerInn`';
	        $jsonRes['success'] = null;
	        $jsonRes['error'][] = $errorMessage;
	        $objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$errorMessage}";
	        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error($errorMessage, "invalid_request"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
		if(is_null($requestArray['sellerData']['serviceEDO'])) {
			$errorMessage = 'Не заполнено поле `serviceEDO` в sellerData.';
			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$errorMessage}";
			Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

			Context::getCurrent()->getResponse()->setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_request"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
        //endregion

        else {
            Loader::includeModule('iblock');

            $sellerInn = $requestArray['sellerInn'];
	        $objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$sellerInn}";

            $sellerDataArray = $requestArray['sellerData'];
	        $directorDataArray = $requestArray['directorData'];
	        $beneficiarsArray = $requestArray['beneficiars'];

	        //region Обработка sellerData
	        if(isset($sellerDataArray)) {
		        $sellerDataInn = $sellerDataArray['inn'];
		        if(is_null($requestArray['crmId'])) {
			        $sellerCardId = $this->findCard($sellerDataInn); //поиск клиента по sellerInn
		        } else {
			        $crmId = intval($requestArray['crmId']);
			        $sellerCardId = $this->findCard($sellerDataInn, $crmId); //поиск клиента по sellerInn или crmId
		        }
		        //region Обновляем Селлера
		        Logs\File ::AddMessage($sellerCardId, "find sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);
		        $this->createOrUpdateCard($sellerCardId, $sellerDataArray, false, $sellerCardId, "seller");
		        $this->createOrUpdateRQ($sellerCardId, $sellerDataArray);
		        //endregion
	        }
	        //endregion

			//region Обработка directorData
	        if(isset($directorDataArray)) {
		        if(is_null($requestArray['directorData']['serviceEDO'])) {
			        $errorMessage = 'Не заполнено поле `serviceEDO` в directorData.';
			        $jsonRes['success'] = null;
			        $jsonRes['error'][] = $errorMessage;
			        $objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$errorMessage}";
			        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

			        Context::getCurrent()->getResponse()->setStatus(400);
			        $this -> addError(new Error($errorMessage, "invalid_request"));
			        return new EventResult(EventResult::ERROR, null, null, $this);
		        }

		        $directorCardId = self::findCard($directorDataArray['inn'], false); //поиск руководителя по inn
		        Logs\File ::AddMessage($directorCardId, "directorCardId", LOG_API_SYNC_SELLER_CONTROLLER);
				//region Создаем руководителя
		        if(!$directorCardId) {
			        Logs\File ::AddMessage("Создаем карточку и реквизиты руководителя", "create",
				        LOG_API_SYNC_SELLER_CONTROLLER);
		        	$_directorCardId = $this->createOrUpdateCard($sellerCardId, $directorDataArray,true,
				        $directorCardId,"director");
			        $this->createOrUpdateRQ($_directorCardId, $directorDataArray, true);
		        }
		        //endregion
		        //region Обновляем руководителя
		        else {
			        Logs\File ::AddMessage("Обновляем карточку и реквизиты руководителя", "update",
				        LOG_API_SYNC_SELLER_CONTROLLER);
			        $this->createOrUpdateCard($sellerCardId, $directorDataArray, false, $directorCardId, "director");
			        $this->createOrUpdateRQ($directorCardId, $directorDataArray);
		        }
		        //endregion
	        }
			//endregion

	        //region Обработка beneficiars
	        if(isset($beneficiarsArray)) {
		        foreach ($beneficiarsArray as $beneficiarDataArray)
		        {
			        if(is_null($beneficiarDataArray['serviceEDO'])) {
				        $errorMessage = 'Не заполнено поле `serviceEDO` в beneficiars.';
				        $jsonRes['success'] = null;
				        $jsonRes['error'][] = $errorMessage;
				        $objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$errorMessage}";
				        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

				        Context::getCurrent()->getResponse()->setStatus(400);
				        $this -> addError(new Error($errorMessage, "invalid_request"));
				        return new EventResult(EventResult::ERROR, null, null, $this);
			        }
			        $beneficiarCardId = $this->findCard($beneficiarDataArray['inn'], false); //поиск клиента по sellerInn или crmId
			        $beneficiarCardIds[] = $beneficiarCardId;
			        //region Создаем бенефициара
			        if(!$beneficiarCardId) {
				        Logs\File ::AddMessage("Создаем карточку и реквизиты бенефициара", "create",
					        LOG_API_SYNC_SELLER_CONTROLLER);
			        	$_beneficiarCardId = $this->createOrUpdateCard(
			        		$sellerCardId,
					        $beneficiarDataArray,
					        true,
					        $beneficiarCardId,
					        "beneficiar"
				        );
				        $this->createOrUpdateRQ($_beneficiarCardId, $beneficiarDataArray, true);
			        }
			        //endregion
			        //region Обновляем бенефициара
			        else {
				        Logs\File ::AddMessage("Обновляем карточку и реквизиты бенефициара", "update",
					        LOG_API_SYNC_SELLER_CONTROLLER);
				        $this->createOrUpdateCard($sellerCardId, $beneficiarDataArray, false, $beneficiarCardId, "beneficiar");
				        $this->createOrUpdateRQ($beneficiarCardId, $beneficiarDataArray);
			        }
			        //endregion
		        }

		        Logs\File ::AddMessage($beneficiarCardIds, "beneficiarCardIds", LOG_API_SYNC_SELLER_CONTROLLER);
	        }
	        //endregion

	        $jsonRes['success'] = "Изменения приняты";
	        $jsonRes['error'] = "";

	        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

            return "Изменения приняты";
        }
    }

	/**
	 * @throws ArgumentNullException
	 * @throws LoaderException
	 * @throws ArgumentOutOfRangeException
	 */
	public function setGuarantorAction(array $params = []): string|EventResult
	{
		//region Подготовка к обработке запроса
		$timeData = Logs\TimeData::start();

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
		$requestArray = json_decode($request->getInput(),true);
		//endregion

		//region Обработка ошибок
		if($requestArray == NULL) {
			$errorMessage = 'Тело запроса не удалось декодировать как JSON.';
			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";
			Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
			Context::getCurrent()->getResponse()->setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_json"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		if(empty($requestArray['crmId'])) {
			$errorMessage = 'Этот запрос не поддерживается. Пустой `crmId`';
			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";
			Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
			Context::getCurrent()->getResponse()->setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_request"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		if(is_null($requestArray['guarantorData']['serviceEDO'])) {
			$errorMessage = 'Не заполнено поле `serviceEDO` в guarantorData.';
			$jsonRes['success'] = null;
			$jsonRes['error'][] = $errorMessage;
			$objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";
			Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

			Context::getCurrent()->getResponse()->setStatus(400);
			$this -> addError(new Error($errorMessage, "invalid_request"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}
		//endregion
		else
		{
			Loader::includeModule('iblock');

			$sellerInn = $requestArray['sellerInn'];
			$objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК по ИНН Селлера: {$sellerInn}";

			//region Поиск карточки Селлера
			$crmId = intval($requestArray['crmId']);
			$sellerCardId = $this->findCard($sellerInn, $crmId); //поиск клиента по sellerInn или crmId
			//endregion

			$guarantorDataArray = $requestArray['guarantorData'];
			$guarantorCardId = $this -> findCard($guarantorDataArray['inn'], false); //поиск клиента по sellerInn или crmId

			//region Создаем Поручителя
			if (!$guarantorCardId)
			{
				Logs\File ::AddMessage("Создаем карточку и реквизиты Поручителя", "create",
					LOG_API_SYNC_SELLER_CONTROLLER);
				$_guarantorCardId = $this -> createOrUpdateCard(
					$sellerCardId,
					$guarantorDataArray,
					true,
					$guarantorCardId,
					"guarantor",
					$crmId
				);
				$this -> createOrUpdateRQ($_guarantorCardId, $guarantorDataArray, true);
			}
			//endregion

			//region Обновляем Поручителя
			else
			{
				Logs\File ::AddMessage("Обновляем карточку и реквизиты Поручителя", "update",
					LOG_API_SYNC_SELLER_CONTROLLER);
				$this -> createOrUpdateCard($sellerCardId, $guarantorDataArray, false, $guarantorCardId, "guarantor", $crmId);
				$this -> createOrUpdateRQ($guarantorCardId, $guarantorDataArray);
			}
			//endregion
		}

		$jsonRes['success'] = "Изменения приняты";
		$jsonRes['error'] = "";

		Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		return "Изменения приняты";
	}
    public function findCard($dataInn, $crmId = false) {
	    $cardId = false;
	    $entityTypeIdCompany = \CCrmOwnerType::Company;
	    $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
	    if (!$factoryCompany)
	    {
		    Context::getCurrent()->getResponse()->setStatus(500);
		    $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }


	    if(!$crmId) {
		    $params = [
			    'filter' => [
				    'UF_CRM_6433D7C925893' => $dataInn,
			    ],
			    'select' => ['ID']
		    ];
		    $itemsCompany = $factoryCompany -> getItems($params);
		    //Logs\File ::AddMessage($itemsCompany, "itemsCompany", LOG_API_SYNC_SELLER_CONTROLLER);
		    foreach ($itemsCompany as $itemCompany)
		    {
			    $cardId = $itemCompany->getId();
		    }

		    return $cardId;
	    }
	    else {
		    $entityTypeId = 128;
		    $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
		    $itemLK = $factory -> getItem($crmId);
		    if($itemLK) {
		    	$itemLKData = $factory -> getItem($crmId)->getData();
			    $cardId = $itemLKData['COMPANY_ID'];

			    return $cardId;
		    } else {
			    $errorMessage = 'Ошибка `crmId` не известен';

			    Context::getCurrent()->getResponse()->setStatus(404);
			    $this -> addError(new Error($errorMessage, "invalid_request"));
			    return new EventResult(EventResult::ERROR, null, null, $this);
		    }
	    }
    }
    private function createOrUpdateCard(
    	$sellerCardId,
	    $dataArray,
	    $createCard,
	    $currentCardId,
	    $type = "",
        $crmId = false
    )
    {
	    $entityTypeIdCompany = \CCrmOwnerType::Company;
	    $entityTypeIdLK = 128;
	    $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
	    $factoryLK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLK);
	    if (!$factoryCompany)
	    {
		    Context::getCurrent()->getResponse()->setStatus(500);
		    $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }

	    //region Обновление Карточки
	    if(!$createCard) {

		    Logs\File ::AddMessage($currentCardId, "currentCardId", LOG_API_SYNC_SELLER_CONTROLLER);

		    $item = $factoryCompany -> getItem($currentCardId);
		    $itemSeller = $factoryCompany -> getItem($sellerCardId);
		    if($crmId) $itemLK = $factoryLK->getItem($crmId);

		    //region "Тип клиента (Организационно-правовая форма)"
		    $_type = $dataArray['type'];
		    if($_type == "IP") {
			    $rsEnumType = \CUserFieldEnum::GetList(array(), array(
				    "XML_ID" => "IP",
			    ));
		    }
		    elseif($_type == "UL") {
			    $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
				    "XML_ID" => "ORG",
			    ));
		    }
		    elseif($_type == "FL") {
			    $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
				    "XML_ID" => "FL",
			    ));
		    }

		    if ($arEnumType = $rsEnumType -> Fetch())
		    {
			    $TypeId = $arEnumType['ID'];
		    }
		    $item->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
		    //endregion

		    //region "Сервис ЭДО"
		    $serviceEDO = $dataArray['serviceEDO'];
		    $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
			    "XML_ID" => "Edo_".$serviceEDO,
		    ));
		    if ($arEnumEDO = $rsEnumEDO -> Fetch())
		    {
			    $serviceEDOId = $arEnumEDO['ID'];
		    }
		    Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
		    $item->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
		    //endregion

		    //region "Ссылки на маркетплейсы"
		    $marketplaceLinks = $dataArray['marketplaceLinks'];
		    $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
		    //endregion

		    //region "id синхронизации с SE"
		    $syncId = $dataArray['synchId'];
		    $item -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
		    //endregion

		    //region "Изменено ЛК"
		    $item->set("UF_CRM_UPDATE_INFO_LK", true);
		    //endregion

		    //region "Паспорт, СНИЛС заемщика"
		    $dataPassportArray = $dataArray['passport'];
		    if($dataPassportArray !== NULL) {
			    $dataPassportFiles = $dataPassportArray['files'];
			    if(!empty($dataPassportFiles)) {
				    $arFile = array();
				    foreach ($dataPassportFiles as $file) {
					    $fileName = $file["fileName"];
					    $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
					    file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
					    $file = \CFile::MakeFileArray($filePathName);//сформировали массив
					    $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
					    if ($fileId) {
						    $fileArray = \CFile::MakeFileArray($fileId);
						    array_push($arFile, $fileArray);
					    } else {
						    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
					    }
				    }
				    $fields = [
					    'UF_CRM_6433D94467769' => $arFile,
				    ];
				    $item->setFromCompatibleData($fields);
			    }
		    }
		    //endregion


		    //region Обновление Карточки селлера
		    /*if($type == "seller")
		    {

		    }*/
		    //endregion

		    //region Обновление Карточки руководителя
		    if($type == "director") {
				//region "Руководитель (представитель)" в Карточке Селлера
				$itemSeller->set("UF_CRM_1615200179", "CO_".$currentCardId);
				//endregion

				//region "Изменено ЛК" в Карточке Селлера
				$itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
				//endregion
			}
		    //endregion

		    //region Обновление Карточки Поручителя
		    if($type == "guarantor") {
			    //region "Поручитель X" в Карточке ЛК
			    if($crmId)
			    {
				    $_guarantors = [
					    'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
					    'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
					    'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
					    'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
					    'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
				    ];
				    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

				    // Проверка на наличие ID в массиве
				    if (!in_array($currentCardId, $_guarantors))
				    {
					    // ID не найден, ищем первое свободное поле
					    foreach ($_guarantors as $key => $value)
					    {
						    if (empty($value))
						    {
							    // Нашли свободное поле, записываем туда ID
							    $itemLK -> set($key, $currentCardId);
							    break; // Выходим из цикла, так как запись произведена
						    }
					    }
				    }
			    }
		    }
		    //endregion

		    //region Обновление Карточки бенефициаров
		    if($type == "beneficiar")
		    {
			    //region "Бенефициар X" в Карточке Селлера
			    $_beneficiars = [
				    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
				    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
				    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
				    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
				    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
			    ];

			    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

				// Проверка на наличие ID в массиве
			    if (!in_array($currentCardId, $_beneficiars)) {
				    // ID не найден, ищем первое свободное поле
				    foreach ($_beneficiars as $key => $value) {
					    if (empty($value)) {
						    // Нашли свободное поле, записываем туда ID
						    $itemSeller->set($key, $currentCardId);
						    $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
						    break; // Выходим из цикла, так как запись произведена
					    }
				    }
			    }
			    //endregion
		    }
		    //endregion

		    $operation = $factoryCompany->getUpdateOperation($item);
		    $operation->disableCheckAccess()->enableSaveToHistory();
		    $operationResult = $operation->launch();

		    $itemId = $item->getId();

		    $operationOnlySeller = $factoryCompany->getUpdateOperation($itemSeller);
		    $operationOnlySeller->disableCheckAccess()->enableSaveToHistory();
		    $operationOnlySellerResult = $operationOnlySeller->launch();
	    }
	    //endregion
	    //endregion
	    //region Создание Карточки
	    else {
		    $newItem = $factoryCompany->createItem();
		    $itemSeller = $factoryCompany->getItem($sellerCardId);
		    $itemLK = $factoryLK->getItem($crmId);

		    //region "Тип клиента (Организационно-правовая форма)"
		    $_type = $dataArray['type'];
		    if($_type == "IP") {
			    $rsEnumType = \CUserFieldEnum::GetList(array(), array(
				    "XML_ID" => "IP",
			    ));
		    }
		    elseif($_type == "UL") {
			    $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
				    "XML_ID" => "ORG",
			    ));
		    }
		    elseif($_type == "FL") {
			    $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
				    "XML_ID" => "FL",
			    ));
		    }

		    if ($arEnumType = $rsEnumType -> Fetch())
		    {
			    $TypeId = $arEnumType['ID'];
		    }
		    $newItem->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
		    //endregion

		    //region "Сервис ЭДО"
		    $serviceEDO = $dataArray['serviceEDO'];
		    $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
			    "XML_ID" => "Edo_".$serviceEDO,
		    ));
		    if ($arEnumEDO = $rsEnumEDO -> Fetch())
		    {
			    $serviceEDOId = $arEnumEDO['ID'];
		    }
		    Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
		    $newItem->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
		    //endregion

		    //region "Ссылки на маркетплейсы"
		    $marketplaceLinks = $dataArray['marketplaceLinks'];
		    $newItem -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
		    //endregion

		    //region "id синхронизации с SE"
		    $syncId = $dataArray['synchId'];
		    $newItem -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
		    //endregion

		    //region "Изменено ЛК"
		    $newItem->set("UF_CRM_UPDATE_INFO_LK", true);
		    //endregion

		    //region "Паспорт, СНИЛС заемщика"
		    $dataPassportArray = $dataArray['passport'];
		    if($dataPassportArray !== NULL) {
			    $dataPassportFiles = $dataPassportArray['files'];
			    if(!empty($dataPassportFiles)) {
				    $arFile = array();
				    foreach ($dataPassportFiles as $file) {
					    $fileName = $file["fileName"];
					    $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
					    file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
					    $file = \CFile::MakeFileArray($filePathName);//сформировали массив
					    $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
					    if ($fileId) {
						    $fileArray = \CFile::MakeFileArray($fileId);
						    array_push($arFile, $fileArray);
					    } else {
						    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
					    }
				    }
				    $fields = [
					    'UF_CRM_6433D94467769' => $arFile,
				    ];
				    $newItem->setFromCompatibleData($fields);
			    }
		    }
		    //endregion

		    //region "ИНН (SCP)"
		    $newItem->set("UF_CRM_6433D7C925893", $dataArray['inn']);
			//endregion


		    //region Создание Карточки руководителя
		    if ($type == "director") {

			    $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
			    $newItem->setTitle($fullName);

			    //region "Руководитель (представитель)" в Карточке Селлера
			    $itemSeller->set("UF_CRM_1615200179", "CO_".$currentCardId);
			    //endregion

			    //region "Изменено ЛК" в Карточке Селлера
			    $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
			    //endregion
		    }
		    //endregion

		    //region Создание Карточки бенефициаров
		    if ($type == "beneficiar") {

			    $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
			    $newItem->setTitle($fullName);

			    //region "Бенефициар X" в Карточке Селлера
			    $_beneficiars = [
				    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
				    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
				    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
				    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
				    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
			    ];

			    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

			    // Проверка на наличие ID в массиве
			    if (!in_array($currentCardId, $_beneficiars)) {
				    // ID не найден, ищем первое свободное поле
				    foreach ($_beneficiars as $key => $value) {
					    if (empty($value)) {
						    // Нашли свободное поле, записываем туда ID
						    $itemSeller->set($key, $currentCardId);
						    $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
						    break; // Выходим из цикла, так как запись произведена
					    }
				    }
			    }
			    //endregion

			    //region "Изменено ЛК" в Карточке Селлера
			    $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
			    //endregion
		    }
		    //endregion

			//region Создание Карточки Поручителя
		    if($type == "guarantor") {
			    $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
			    $newItem->setTitle($fullName);

			    //region "Поручитель X" в Карточке ЛК
			    $_guarantors = [
				    'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
				    'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
				    'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
				    'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
				    'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
			    ];
			    //endregion
			    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

			    // Проверка на наличие ID в массиве
			    if (!in_array($currentCardId, $_guarantors)) {
				    // ID не найден, ищем первое свободное поле
				    foreach ($_guarantors as $key => $value) {
					    if (empty($value)) {
						    // Нашли свободное поле, записываем туда ID
						    $itemLK->set($key, $currentCardId);
						    break; // Выходим из цикла, так как запись произведена
					    }
				    }
			    }
		    }
		    //endregion

		    $newItem->save();
		    $itemId = $newItem->getId();

		    $operation = $factoryCompany->getAddOperation($newItem);
		    $operation->disableCheckAccess()->enableSaveToHistory();
		    $operationResult = $operation->launch();

		    $itemId = $newItem->getId();

		    $operationOnlySeller = $factoryCompany->getUpdateOperation($itemSeller);
		    $operationOnlySeller->disableCheckAccess()->enableSaveToHistory();
		    $operationOnlySellerResult = $operationOnlySeller->launch();

	    }
	    //endregion


	    Logs\File ::AddMessage($itemId, "Получение ID карточки компании",LOG_API_SYNC_SELLER_CONTROLLER);

	    return $itemId;
    }
	private function findCompanyRQ($cardId, $inn) {
		$requisite = \CRest::call(
			"crm.requisite.list",
			array(
				"filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
				"select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
			)
		)['result'];

		Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

		if(isset($requisite)) {
			return $requisite[0]['ID'];
		} else {
			return null;
		}

	}
	private function createOrUpdateRQ($cardId, $dataArray, $createRQ = false): void
    {
	    $type = $dataArray['type'];
	    $inn = $dataArray['inn'];
	    $kpp = $dataArray['kpp'];
	    $ogrnip = $dataArray['ogrnip'];
	    $ogrn = $dataArray['ogrn'];
	    $okpo = $dataArray['okpo'];
	    $okved = $dataArray['okved'];
	    $companyRegDate = $dataArray['companyRegDate'];
	    $companyName = $dataArray['companyName'];
	    $companyFullName = $dataArray['companyFullName'];
	    $fnsDepartment = $dataArray['fnsDepartment'];
	    $firstName = $dataArray['firstName'];
	    $lastName = $dataArray['lastName'];
	    $secondName = $dataArray['secondName'];
	    $birthday = $dataArray['birthday'];
	    $birthPlace = $dataArray['birthPlace'];
	    $serviceEDO = $dataArray['serviceEDO'];

	    $passportArray = $dataArray['passport'];
	    $passportIssuer = $passportArray['issuer'];
	    $passportNumber = $passportArray['number'];
	    $passportSeries = $passportArray['series'];
	    $passportIssuedAt = $passportArray['issuedAt'];
	    $passportIssuerCode = $passportArray['issuerCode'];

	    $rqId = $this->findCompanyRQ($cardId,$inn);
	    Logs\File ::AddMessage($rqId, "rqId Update", LOG_API_SYNC_SELLER_CONTROLLER);

		if(isset($rqId)) {

			if($type === "IP" || $type === "FL")
			{
				$params = [
					"id" => $rqId,
					"fields" => [
						'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
						'NAME' => $lastName . " " . $firstName . " " . $secondName,
						'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
						'RQ_FIRST_NAME' => $firstName,
						'RQ_LAST_NAME' => $lastName,
						'RQ_SECOND_NAME' => $secondName,
						'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
						'RQ_IDENT_DOC_SER' => $passportSeries,
						'RQ_IDENT_DOC_NUM' => $passportNumber,
						'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
						'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
						'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
						'UF_CRM_1647929611' => $birthPlace,
						'UF_CRM_1684493639' => $birthday,
						'RQ_INN' => $inn,
						'RQ_OGRNIP' => $ogrnip,
						'RQ_OKPO' => $okpo,
						'RQ_OKVED' => $okved,
						'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
						'UF_CRM_1688964741' => $fnsDepartment,
					]
				];
			}
			if($type === "UL")
			{
				$params = [
					"id" => $rqId,
					"fields" => [
						'TITLE' => $companyName,
						'NAME' => $companyName,
						'RQ_INN' => $inn,
						'RQ_KPP' => $kpp,
						'RQ_OGRN' => $ogrn,
						'RQ_OKPO' => $okpo,
						'RQ_OKVED' => $okved,
						'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
						'UF_CRM_1688964741' => $fnsDepartment,
						'RQ_COMPANY_NAME' => $companyName,
						'RQ_COMPANY_FULL_NAME' => $companyFullName,
					]
				];
			}

			\CRest ::call('crm.requisite.update', $params);



			$_requisite = \CRest::call(
				"crm.requisite.get",
				array("id" => $rqId)
			)['result'];

			Logs\File ::AddMessage($_requisite, "requisite Info After Update for {$cardId}",
				LOG_API_SYNC_SELLER_CONTROLLER);

		}
		else {

			if($type === "IP") $PRESET_ID = 2;
			if($type === "FL") $PRESET_ID = 3;
			if($type === "UL") $PRESET_ID = 1;

			if($type === "IP" || $type === "FL")
			{
				$params = [
					"fields" => [
						"ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
						"ENTITY_ID" => $cardId,
						"PRESET_ID" => $PRESET_ID,
						'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
						'NAME' => $lastName . " " . $firstName . " " . $secondName,
						'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
						'RQ_FIRST_NAME' => $firstName,
						'RQ_LAST_NAME' => $lastName,
						'RQ_SECOND_NAME' => $secondName,
						'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
						'RQ_IDENT_DOC_SER' => $passportSeries,
						'RQ_IDENT_DOC_NUM' => $passportNumber,
						'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
						'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
						'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
						'UF_CRM_1647929611' => $birthPlace,
						'UF_CRM_1684493639' => $birthday,
						'RQ_INN' => $inn,
						'RQ_OGRNIP' => $ogrnip,
						'RQ_OKPO' => $okpo,
						'RQ_OKVED' => $okved,
						'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
						'UF_CRM_1688964741' => $fnsDepartment,
					]
				];
				$rqId = \CRest ::call('crm.requisite.add', $params)['data'];
			}
			if($type === "UL")
			{
				$params = [
					"fields" => [
						"ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
						"ENTITY_ID" => $cardId,
						"PRESET_ID" => $PRESET_ID,
						'TITLE' => $companyName,
						'NAME' => $companyName,
						'RQ_INN' => $inn,
						'RQ_KPP' => $kpp,
						'RQ_OGRN' => $ogrn,
						'RQ_OKPO' => $okpo,
						'RQ_OKVED' => $okved,
						'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
						'UF_CRM_1688964741' => $fnsDepartment,
						'RQ_COMPANY_NAME' => $companyName,
						'RQ_COMPANY_FULL_NAME' => $companyFullName,
					]
				];
				$rqId = \CRest ::call('crm.requisite.add', $params)['data'];
			}

			Logs\File ::AddMessage($rqId, "rqId Create", LOG_API_SYNC_SELLER_CONTROLLER);
		}

	    if(isset($rqId))
	    {
		    $addressArray = $dataArray['address'];
		    foreach ($addressArray as $address)
		    {
			    if ($address['type'] == "registration") $addressTypeId = 4;
			    if ($address['type'] == "actual") $addressTypeId = 1;
			    if ($address['type'] == "legal") $addressTypeId = 6;

			    $addressCity = $address['city'];
			    $addressFlat = $address['flat'];
			    $addressHouse = $address['house'];
			    $addressRegion = $address['region'];
			    $addressDistrict = $address['district'];
			    $addressStreet = $address['street'];
			    $addressBuilding = $address['building'];
			    $addressStructure = $address['structure'];
			    $addressCountry = $address['country'];
			    $addressPostalCode = $address['postalCode'];

			    //код добавления данного типа адреса в реквизит карточки клиента
			    $arAddress['ENTITY_ID'] = intval($rqId);//id requisite
			    $arAddress['TYPE_ID'] = $addressTypeId;//Адрес регистрации
			    $arAddress['ENTITY_TYPE_ID'] = \CCrmOwnerType::Requisite;//Реквизит 8
			    $arAddress['ANCHOR_ID'] = $cardId;// ID Компании
			    $arAddress['POSTAL_CODE'] = $addressPostalCode;// Индекс
			    $arAddress['COUNTRY'] = $addressCountry;// Страна
			    $arAddress['PROVINCE'] = $addressRegion;// регион
			    $arAddress['REGION'] = $addressDistrict;// Район
			    $arAddress['CITY'] = $addressCity;// Город
			    $arAddress['ADDRESS_1'] = $addressStreet . ", " . $addressHouse . ", " . $addressStructure . ", " . $addressBuilding;
			    //Улица, дом, корпус, строение.
			    $arAddress['ADDRESS_2'] = $addressFlat;// Квартира / офис.
			    $arAddress['COUNTRY_CODE'] = 643;// Код страны
			    $resultAddress = \CRest ::call('crm.address.add', ['fields' => $arAddress]);
			    self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressStreet, 'STREET', $addressTypeId);
			    self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressHouse, 'BUILDING', $addressTypeId);

			    Logs\File ::AddMessage($arAddress, "arAddress " . $address['type'], LOG_API_SYNC_SELLER_CONTROLLER);
		    }
	    }
    }

	private function addressUpdate($id, $entityTypeId, $dataField, $nameField, $typeId) {
		global $DB;
		$Address = new \Bitrix\Location\Controller\Address;


		$resAddrList = \CRest::call('crm.address.list', array(
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
    public function setBankAccountAction(array $params = [])
    {
	    $timeData = Logs\TimeData::start();
	    $context = Application ::getInstance() -> getContext();
	    $request = $context -> getRequest();
	    $headers = $request->getHeaders()->toArray();
	    $server = $context -> getServer();
	    $url = $server['SCRIPT_URI'];
	    $serverArray = $server->toArray();
	    $serverName = $serverArray['SERVER_NAME'];
	    $objectData['METHOD'] = $server['REQUEST_METHOD'];
	    $point = "SE_BX";
	    foreach ($headers as $key => $header) {
		    $headersValues[$header['name']] = $header['values'][0];
	    }

	    $requestArray = json_decode($request->getInput(),true);

	    \Bitrix\Main\Loader ::IncludeModule('crm');
	    if($requestArray == NULL) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
		    $this->addError(new Error($errorMessage, "invalid_json"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }

	    if(empty($requestArray['bankAccounts'])) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `bankAccounts`";
		    $this->addError(new Error($errorMessage, "invalid_request"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }
	    else
	    {
		    $authorization = $server -> get('REMOTE_USER');
		    $token = str_replace('BitrixAuth ', '', $authorization);

		    Loader ::includeModule('iblock');

		    Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

		    foreach ($requestArray['bankAccounts'] as $bankAccount) {
				$sellerInn = $bankAccount['sellerInn'];
				$crmId = $bankAccount['crmId'];
				$title = $bankAccount['title'];
				$nameBank = $bankAccount['nameBank'];
				$bankIdCode = $bankAccount['bankIdCode'];
				$checkAccount = $bankAccount['checkAccount'];
				$adjAccount = $bankAccount['adjAccount'];

			    $objectData['ITEM_TITLE'] = "Получение `{$title}` по ИНН: {$sellerInn}";

			    if($crmId !== NULL) {
				    $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
			    } else {
				    $sellerCardId = self::findCard($sellerInn);
			    }

			    Logs\File ::AddMessage($sellerCardId, "sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);

			    if($sellerCardId === null || !is_int($sellerCardId)) {
				    Context::getCurrent()->getResponse()->setStatus(404);
				    $this -> addError(new Error('Не существует Селлера с таким ИНН или CRMID', "invalid_json"));
				    return new EventResult(EventResult::ERROR, null, null, $this);
			    }

			    $requisite = \CRest::call(
					"crm.requisite.list",
					array(
						"filter" => ["ENTITY_ID" => $sellerCardId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
						"select" => ["ID","PRESET_ID", "ENTITY_ID", "ENTITY_TYPE_ID"])
				)['result'];

			    Logs\File ::AddMessage($requisite, "requisite", LOG_API_SYNC_SELLER_CONTROLLER);


			    $rqId = $requisite[0]['ID'];

				$parameters = [
					"fields" => [
						"ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
						"ENTITY_ID" => $rqId,
						"NAME" => $title,
						"RQ_BANK_NAME" => $nameBank,
						"RQ_BIK" => $bankIdCode,
						"RQ_BIC" => $bankIdCode,
						"RQ_ACC_NUM" => $checkAccount,
						"RQ_COR_ACC_NUM" => $adjAccount,
						"RQ_ACC_CURRENCY" => "RUB",
						"COMMENTS" => "МКК"
					]
				];
				\CRest ::call('crm.requisite.bankdetail.add', $parameters);
			}

		    $jsonRes['success'] = "{$title} для {$sellerInn} успешно добавлен!";
		    $jsonRes['error'] = "";
		    Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);

		    return $jsonRes;
	    }
    }
    public function getBankAccountAction(array $params = [])
    {
	    $timeData = Logs\TimeData::start();
	    $context = Application ::getInstance() -> getContext();
	    $request = $context -> getRequest();
	    $headers = $request->getHeaders()->toArray();
	    $server = $context -> getServer();
	    $url = $server['SCRIPT_URI'];
	    $serverArray = $server->toArray();
	    $serverName = $serverArray['SERVER_NAME'];
	    $objectData['METHOD'] = $server['REQUEST_METHOD'];
	    $point = "BX_SE";
	    foreach ($headers as $key => $header) {
		    $headersValues[$header['name']] = $header['values'][0];
	    }

	    $requestArray = json_decode($request->getInput(),true);

	    \Bitrix\Main\Loader ::IncludeModule('crm');
	    if($requestArray == NULL) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
		    $this->addError(new Error($errorMessage, "invalid_json"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }

	    if(empty($requestArray['sellerInn'])) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
		    $this->addError(new Error($errorMessage, "invalid_request"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }
        elseif(empty($requestArray['crmId'])) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `crmId`";
            $this->addError(new Error($errorMessage, "invalid_request"));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
	    else
	    {
		    $authorization = $server -> get('REMOTE_USER');
		    $token = str_replace('BitrixAuth ', '', $authorization);

		    Loader ::includeModule('iblock');

		    Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

		    //foreach ($requestArray['bankAccounts'] as $bankAccount) {
				$sellerInn = $requestArray['sellerInn'];
				$crmId = $requestArray['crmId'];
				//$title = $bankAccount['title'];
				//$nameBank = $bankAccount['nameBank'];
				//$bankIdCode = $bankAccount['bankIdCode'];
				//$checkAccount = $bankAccount['checkAccount'];
				//$adjAccount = $bankAccount['adjAccount'];

			    $objectData['ITEM_TITLE'] = "Отправка банковских реквизитов по ИНН: {$sellerInn}";

			    if($crmId !== NULL) {
				    $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
			    } else {
				    $sellerCardId = self::findCard($sellerInn);
			    }

			    Logs\File ::AddMessage($sellerCardId, "sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);

			    if($sellerCardId === null || !is_int($sellerCardId)) {
				    Context::getCurrent()->getResponse()->setStatus(404);
				    $this -> addError(new Error('Не существует Селлера с таким ИНН или CRMID', "invalid_json"));
				    return new EventResult(EventResult::ERROR, null, null, $this);
			    }

			    $requisite = \CRest::call(
					"crm.requisite.list",
					array(
						"filter" => ["ENTITY_ID" => $sellerCardId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
						"select" => ["*","UF_*"]
                    )
				)['result'];

			    Logs\File ::AddMessage($requisite, "requisite", LOG_API_SYNC_SELLER_CONTROLLER);


			    $rqId = $requisite[0]['ID'];

				$parameters = [
					"filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,	"ENTITY_ID" => $rqId]
				];
                Logs\File ::AddMessage($parameters, "parameters for bankdetaillist", LOG_API_SYNC_SELLER_CONTROLLER);
				$arResult = \CRest::call('crm.requisite.bankdetail.list', $parameters)['result'];
                $result = $newResult = [];
                $newResult['crmId'] = $crmId;
                $newResult['sellerInn'] = $sellerInn;
                foreach ($arResult as $bankAccount) {
                    $result['title'] = $bankAccount['NAME'];
                    $result['nameBank'] = $bankAccount['RQ_BANK_NAME'];
                    $result['bankIdCode'] = $bankAccount['RQ_BIK'];
                    $result['checkAccount'] = $bankAccount['RQ_ACC_NUM'];
                    $result['adjAccount'] = $bankAccount['RQ_COR_ACC_NUM'];
                    $result['accCurrency'] = $bankAccount['RQ_ACC_CURRENCY'];
                    $result['comments'] = $bankAccount['COMMENTS'];
                    $bankAccounts[] = $result;
                }
                $newResult['bankAccounts'] = $bankAccounts;
			//}

		    $jsonRes['success'] = $newResult;
		    $jsonRes['error'] = "";
		    Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);

		    return $newResult;
	    }
    }

    public function setLoanAction(array $params = [])
    {
	    $timeData = Logs\TimeData::start();
	    $context = Application ::getInstance() -> getContext();
	    $request = $context -> getRequest();
	    $headers = $request->getHeaders()->toArray();
	    $server = $context -> getServer();
	    $serverArray = $server->toArray();
	    $url = $server['SCRIPT_URI'];
	    $serverName = $serverArray['SERVER_NAME'];

	    $objectData['METHOD'] = $server['REQUEST_METHOD'];
	    $point = "SE_BX";

	    foreach ($headers as $key => $header) {
		    $headersValues[$header['name']] = $header['values'][0];
	    }

	    \Bitrix\Main\Loader ::IncludeModule('crm');

	    $requestArray = json_decode($request->getInput(),true);
	    Logs\File ::AddMessage($requestArray, "requestArray1", LOG_API_SYNC_SELLER_CONTROLLER);

	    if($requestArray == NULL) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
		    $this->addError(new Error($errorMessage, "invalid_json"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }

	    if(empty($requestArray['sellerInn'])) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
		    $this -> addError(new Error($errorMessage, "invalid_request"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }
	    else
	    {
		    $authorization = $server -> get('REMOTE_USER');
		    $token = str_replace('BitrixAuth ', '', $authorization);

		    //Logs\File ::AddMessage($requestArray, "requestArray2", LOG_API_SYNC_SELLER_CONTROLLER);

		    $sellerInn = $requestArray['sellerInn'];
		    $crmId = intval($requestArray['crmId']);
		    $loanData = $requestArray['loanData'];
		    $loanAmount = floatval($loanData['amount']);
		    $loanTerm = intval($loanData['term']);



		    //region $loanTermId
		    global $DB;
		    $rsEnumTerm = \CUserFieldEnum::GetList(array(), array(
			    "XML_ID" => "{$loanTerm}_MONTHS",
		    ));

		    if ($arEnumTerm = $rsEnumTerm -> Fetch())
		    {
			    $loanTermId = (int) $arEnumTerm['ID'];
		    }
		    //Logs\File ::AddMessage($loanTermId, "loanTermId", LOG_API_SYNC_SELLER_CONTROLLER);
		    //endregion $loanTermId

		    $purposeLoan = $loanData['purposeLoan'];

		    $typeContract = (bool) $loanData['typeContract'];
		    $isfirstLoan = (bool) $loanData['isFirstTranche'];

		    if(!is_null($crmId)) {
			    $sellerCardId = self::findCard($sellerInn,$crmId);
		    } else {
			    $sellerCardId = self::findCard($sellerInn);
		    }

		    $entityTypeIdLK = 128;
		    $factoryLK = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeIdLK);

		    if($isfirstLoan)
		    {
			    $parametersLK = [
				    'filter' => [
					    '=COMPANY_ID' => $sellerCardId,
					    'STAGE_ID' => 'DT128_226:UC_6GB0Q7', //Ожидание решения клиента
					    'CATEGORY_ID' => 226
				    ],
				    'select' => ['ID']
			    ];
			    $itemsLK = $factoryLK -> getItems($parametersLK);
			    if($itemsLK) {
				    foreach ($itemsLK as $itemLK) {
					    Logs\File ::AddMessage($itemLK->getId(), "LKgetId", LOG_API_SYNC_SELLER_CONTROLLER);
					    $itemLK->set('UF_CRM_CRMID', $crmId);
					    $itemLK->set('UF_CRM_INN', $sellerInn);
					    $itemLK->set('UF_CRM_LOAN_AMOUNT', $loanAmount);
					    $itemLK->set('UF_CRM_LOAN_TERM', $loanTermId);
					    $itemLK->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purposeLoan);
					    if($typeContract) {
						    $rsEnum = \CUserFieldEnum::GetList(array(), array(
							    "XML_ID" => "WITH_DELAY",
						    ));
						    if ($arEnum = $rsEnum->Fetch()) {
							    $typeContractTrueId = $arEnum['ID'];
						    }
						    $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractTrueId);
					    } else {
						    $rsEnum = \CUserFieldEnum::GetList(array(), array(
							    "XML_ID" => "NO_DELAY",
						    ));
						    if ($arEnum = $rsEnum->Fetch()) {
							    $typeContractFalseId = $arEnum['ID'];
						    }
						    $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractFalseId);
					    }
					    //$itemLK->setStageId('DT128_208:UC_VC1XA7');

				    }
                    $itemLK->save();
                    $itemLK->setStageId('DT128_226:CLIENT');
                    $itemLK->setCategoryId(226);

				    $operationLK = $factoryLK->getUpdateOperation($itemLK);
				    $operationLK->disableAllChecks();
				    $operationLKResult = $operationLK->launch();

				    // Проверка на ошибки
				    if ($operationLKResult->isSuccess()) {
					    $objectData['ITEM_TITLE'] = "Новый транш для ИНН: {$sellerInn}";
					    $jsonRes['success'] = "Новый транш для ИНН: {$sellerInn} создан";
					    $jsonRes['error'] = "";
				    }
				    else {
					    Context::getCurrent()->getResponse()->setStatus(500);
					    $errorMessage = "Создание транша не удалось";
					    $this -> addError(new Error($errorMessage, "invalid_request"));
					    $jsonRes['success'] = null;
					    $jsonRes['error'] = $errorMessage;
					    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
					    return new EventResult(EventResult::ERROR, null, null, $this);
				    }
			    } else {
                    Context::getCurrent()->getResponse()->setStatus(400);
                    $errorMessage = "Создание первого транша невозможно, уже существует!";
                    $this -> addError(new Error($errorMessage, "invalid_request"));
                    $jsonRes['success'] = null;
                    $jsonRes['error'] = $errorMessage;
                    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
                    return new EventResult(EventResult::ERROR, null, null, $this);
                }
		    }
		    else {
			    $itemPrevLK = $factoryLK -> getItem($crmId);
                $titleItemPrevLk = $itemPrevLK->get('TITLE');
                $parentId134ItemPrevLK = $itemPrevLK->get('PARENT_ID_134');
                $createdByItemPrevLK = $itemPrevLK->get('CREATED_BY');

			    $itemLK = $factoryLK -> createItem();
                $itemLK->set('TITLE',"Повторный транш ". $titleItemPrevLk);
			    $itemLK->set('COMPANY_ID',$sellerCardId);
			    $itemLK->set('UF_CRM_CRMID', $crmId);
                $itemLK->set('PARENT_ID_134', $parentId134ItemPrevLK);
			    $itemLK->set('UF_CRM_INN', $sellerInn);
			    $itemLK->set('UF_CRM_LOAN_AMOUNT', $loanAmount);
			    $itemLK->set('UF_CRM_LOAN_TERM', $loanTermId);
			    $itemLK->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purposeLoan);
			    $itemLK->set('UF_CRM_REPEAT_ZAYAVKA', 1);
			    if($typeContract) {
				    $rsEnum = \CUserFieldEnum::GetList(array(), array(
					    "XML_ID" => "WITH_DELAY",
				    ));
				    if ($arEnum = $rsEnum->Fetch()) {
					    $typeContractTrueId = $arEnum['ID'];
				    }
                    Logs\File ::AddMessage($typeContractTrueId, "typeContractTrueId", LOG_API_SYNC_SELLER_CONTROLLER);
				    $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractTrueId);
			    }
			    else {
				    $rsEnum = \CUserFieldEnum::GetList(array(), array(
					    "XML_ID" => "NO_DELAY",
				    ));
				    if ($arEnum = $rsEnum->Fetch()) {
					    $typeContractFalseId = $arEnum['ID'];
				    }
				    $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractFalseId);
			    }

				


			    $context = new \Bitrix\Crm\Service\Context;
			    $context->setUserId($createdByItemPrevLK);

			    $itemLK->save();
                $itemLK->setStageId('DT128_226:NEW');
                $itemLK->setCategoryId(226);

			    $operationLK = $factoryLK->getAddOperation($itemLK, $context);
			    $operationLK->disableAllChecks();
			    $operationLKResult = $operationLK->launch();

			    // Проверка на ошибки
			    if ($operationLKResult->isSuccess()) {
				    $objectData['ITEM_TITLE'] = "Повторный транш для ИНН: {$sellerInn}";
                    $jsonRes['success'] = "Повторный транш для ИНН: {$sellerInn} успешно создан";
                    $jsonRes['error'] = "";
			    }
			    else {
				    Context::getCurrent()->getResponse()->setStatus(500);
				    $errorMessage = "Создание транша не удалось";
				    $this -> addError(new Error($errorMessage, "invalid_request"));
				    $jsonRes['success'] = null;
				    $jsonRes['error'] = $errorMessage;
				    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
				    return new EventResult(EventResult::ERROR, null, null, $this);
			    }
		    }


		    Logs\IBlock::setData($url, $request->getInput(), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return $jsonRes;
	    }
    }

    public function getLimitsAction(array $params = [])
    {
	    $timeData = Logs\TimeData::start();
	    $context = Application ::getInstance() -> getContext();
	    $request = $context -> getRequest();
	    $headers = $request->getHeaders()->toArray();
	    $server = $context -> getServer();
	    $serverArray = $server->toArray();
	    $serverName = $serverArray['SERVER_NAME'];

	    \Bitrix\Main\Loader ::IncludeModule('crm');

	    $point = "SE_BX";
	    $QUERY_STRING = $server['QUERY_STRING'];
	    $url = $server['SCRIPT_URI']."?".$QUERY_STRING;
	    $REQUEST_TIME = $server['REQUEST_TIME'];
	    $QUERY_STRING = $server['QUERY_STRING'];
	    $objectData['METHOD'] = $server['REQUEST_METHOD'];

	    foreach ($headers as $key => $header) {
		    $headersValues[$header['name']] = $header['values'][0];
	    }

	    $requestArray = json_decode($request->getInput(),true);

	    Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

	    if($requestArray == NULL) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
		    $this->addError(new Error($errorMessage, "invalid_json"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }

	    if(empty($requestArray['sellerInn'])) {
		    Context::getCurrent()->getResponse()->setStatus(400);
		    $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
		    $this->addError(new Error($errorMessage, "invalid_request"));
		    $jsonRes['success'] = null;
		    $jsonRes['error'] = $errorMessage;
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }
	    else
	    {
		    $authorization = $server -> get('REMOTE_USER');
		    $token = str_replace('BitrixAuth ', '', $authorization);

		    $sellerInn = $requestArray['sellerInn'];
		    $crmId = (int) $requestArray['crmId'];

		    $objectData['ITEM_TITLE'] = "Получение лимитов по ИНН: {$sellerInn}";

		    Logs\File ::AddMessage($crmId, "crmId", LOG_API_SYNC_SELLER_CONTROLLER);

		    if($crmId !== NULL) {
		    	$sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
		    } else {
			    $sellerCardId = self::findCard($sellerInn);
		    }

		    if($sellerCardId === null || !is_int($sellerCardId)) {
			    Context::getCurrent()->getResponse()->setStatus(404);
			    $this -> addError(new Error('Не существует Селлера с таким ИНН или CRMID', "invalid_json"));
			    return new EventResult(EventResult::ERROR, null, null, $this);
		    }

		    $entityTypeIdOSK = 134;
		    $factoryOSK = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeIdOSK);
		    $parametersOSK = [
				'filter' => [
					'=COMPANY_ID' => $sellerCardId
				]
		    ];
		    $itemsOSK = $factoryOSK -> getItems($parametersOSK);
		    foreach ($itemsOSK as $itemOSK)
		    {
			    $cardOSKData = $itemOSK->getData();
		    }


		    $availableLimit = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744875738']));
		    $allLimit = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744846487']));
		    $possibleLimitIncrease = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684846350']));
		    $dolg = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744827969']));

		    $arLimits['availableLimit'] = $availableLimit;
		    $arLimits['allLimit'] = $allLimit;
		    $arLimits['possibleLimitIncrease'] = $possibleLimitIncrease;
		    $arLimits['dolg'] = $dolg;

		    $jsonRes['success'] = $arLimits;
		    $jsonRes['error'] = "";
		    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

		    return $jsonRes['success'];
	    }
    }
    
    public function getLoansAction(array $params = []) {
	    $timeData = Logs\TimeData::start();
	    $context = Application ::getInstance() -> getContext();
	    $request = $context -> getRequest();
	    $headers = $request->getHeaders()->toArray();
	    $server = $context -> getServer();
	    $serverArray = $server->toArray();
	    $serverName = $serverArray['SERVER_NAME'];

	    \Bitrix\Main\Loader ::IncludeModule('crm');

	    $point = "SE_BX";
	    $QUERY_STRING = $server['QUERY_STRING'];
	    $url = $server['SCRIPT_URI']."?".$QUERY_STRING;
	    $REQUEST_TIME = $server['REQUEST_TIME'];
	    $QUERY_STRING = $server['QUERY_STRING'];
	    $objectData['METHOD'] = $server['REQUEST_METHOD'];

	    foreach ($headers as $key => $header) {
		    $headersValues[$header['name']] = $header['values'][0];
	    }

	    $requestArray = json_decode($request->getInput(),true);

	    //Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

	    $sellerInn = $requestArray['sellerInn'];
	    $crmId = $requestArray['crmId'];

	    $objectData['ITEM_TITLE'] = "Получение списка займов по ИНН и CRMID: {$sellerInn}";

	    //Logs\File ::AddMessage($crmId, "crmId", LOG_API_SYNC_SELLER_CONTROLLER);

	    if(!is_null($crmId)) {
		    $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
	    } else {
		    $sellerCardId = self::findCard($sellerInn);
	    }

	    if($sellerCardId === null || !is_int($sellerCardId)) {
		    Context::getCurrent()->getResponse()->setStatus(404);
		    $this -> addError(new Error('Не существует Селлера с таким ИНН или CRMID', "invalid_json"));
		    return new EventResult(EventResult::ERROR, null, null, $this);
	    }
	    
	    $curM = date('m',$REQUEST_TIME);
	    $curY = date('Y',$REQUEST_TIME);

	    $entityTypeIdDZ = 188;
	    $factoryDZ = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeIdDZ);
	    $dogDateStart = '01-01-1997';
	    $dogDateEnd = '31-' . $curM . '-' . $curY;

	    $parametersDZ = [
		    'filter' => [
			    [
				    "LOGIC" => "OR",
				    ["STAGE_ID" => "DT188_28:NEW"],
				    ["STAGE_ID" => "DT188_28:CLIENT"],
				    ["STAGE_ID" => "DT188_28:UC_EQ8KZU"]
			    ],
			    '=COMPANY_ID' => $sellerCardId,
			    '=UF_CRM_15_SS_FILIAL' => 17611
		    ],
		    'select' => [
		    	'UF_CRM_15_1679907467', 'UF_CRM_15_SS_NOMER',
			    'UF_CRM_15_SS_SUMMADOGOVORA', 'UF_CRM_15_1679925201',
			    'UF_CRM_15_SS_NEXTPAYDAY', 'UF_CRM_15_SS_NEXTPAYSUMMA',
			    'UF_CRM_15_1679907525', 'UF_CRM_15_SS_NOMINAL',
			    'UF_CRM_15_SS_FILIAL'

		    ]
	    ];
	    $res = [];

	    $itemsDZ = $factoryDZ -> getItems($parametersDZ);
	    //Logs\File ::AddMessage($itemsDZ, "itemsDZ", LOG_API_SYNC_SELLER_CONTROLLER);

	    foreach ($itemsDZ as $c => $itemDZ)
	    {
		    $itemDZData = $itemDZ->getData();
		    if(number_format((float) str_replace("|RUB","",$itemDZData['UF_CRM_15_1679907467']), 2,"."," ") == 0.00) {
			    $sumProsrocheno = null;
		    } else {
			    $sumProsrocheno = number_format((float) str_replace("|RUB","",$itemDZData['UF_CRM_15_1679907467']), 2,"."," ");
		    }

		    $res[$c]['numberDog'] = $itemDZData["UF_CRM_15_SS_NOMER"];
		    $res[$c]['sumDog'] = number_format((float) str_replace("|RUB","",$itemDZData["UF_CRM_15_SS_SUMMADOGOVORA"]), 2,"."," ");
		    $res[$c]['dateDog'] = date('Y-m-d\TH:i:s.msp', strtotime($itemDZData["UF_CRM_15_1679925201"]));
		    $res[$c]['nextPayDay'] = date('Y-m-d\TH:i:s.msp', strtotime($itemDZData["UF_CRM_15_SS_NEXTPAYDAY"]));
		    $res[$c]['nextPaySum'] = number_format((float) str_replace("|RUB","", $itemDZData["UF_CRM_15_SS_NEXTPAYSUMMA"]), 2,"."," ");
		    $res[$c]['prosrochenoDays'] = $itemDZData['UF_CRM_15_1679907525'];
		    $res[$c]['sumProsrocheno'] = $sumProsrocheno;
		    $res[$c]['ostatok'] = number_format((float) str_replace("|RUB","",$itemDZData["UF_CRM_15_SS_NOMINAL"]), 2,"."," ");
	    }

	    $jsonRes['success'] = $res;
	    $jsonRes['error'] = "";
	    Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);

	    return $jsonRes['success'];
    }
}
