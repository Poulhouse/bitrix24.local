<?php namespace KPLab;

class Nopaper {
	public function __construct()
	{
		$this -> apiUrl = "https://api.seller-capital.ru/";
		$this -> internalUrl = "https://internal.seller-capital.ru/";
		$this -> point = "BX_SE";
		//$this->passportImages = null;
		$this -> errorMessage = null;
	}

	//Создание пакета документов на подпись
	public function createPackageDocument() {

		$user = \CUser ::GetByID(str_replace("user_", "", '{{Ответственный}}')) -> Fetch();
		$arFilter = array("ID" => $user['UF_CRM_CONTACT'], "CHECK_PERMISSIONS" => "N");
		$contact = \CCrmContact ::GetListEx($arSort, $arFilter, false, false, $arSelect, $arOptions) -> Fetch();
		if ($contact['ID'])
		{
			//echo $contact['ID'];
			$entityRequisite = new \Bitrix\Crm\EntityRequisite;
			$requisite = $entityRequisite -> getList([
				"select" => array("RQ_IDENT_DOC_SER", "RQ_IDENT_DOC_NUM", "RQ_IDENT_DOC_ISSUED_BY", "RQ_IDENT_DOC_DEP_CODE", "RQ_IDENT_DOC_DATE"),
				"filter" => array("ENTITY_ID" => $contact['ID'], "ENTITY_TYPE_ID" => \CCrmOwnerType::Contact),
				"order" => array("SORT" => "desc", "ID" => "desc")
			]) -> fetch();
			//echo "<pre>"; var_dump($contact); echo "</pre>";
		}
		$arFilter = [
			'ENTITY_ID' => 'CONTACT',
			'ELEMENT_ID' => $contact['ID'],
			'TYPE_ID' => 'PHONE',
			'VALUE_TYPE' => 'MOBILE',
		];
		$resPhones = \CCrmFieldMulti ::GetListEx([], $arFilter, false, ['nTopCount' => 1], ['VALUE']);
		$arPhone = $resPhones -> fetch();
		//echo "<pre>"; var_dump($arPhone); echo "</pre>";
		//echo $arPhone["VALUE"];


		$files = '{{Документы для подписания}}';//"92834, 92835";
		$filesArray = explode(', ', $files);
		//echo "<pre>"; var_dump($filesArray ); echo "</pre>";
		$post = array();
		$element = array();


		$post["title"] = '{{Название}}';
		$post["clientFlPhoneNumber"] = '7' . substr($arPhone["VALUE"], -10);
		$post["clientUlInn"] = "000";
		$post["files"] = $element;

		foreach ($filesArray as $filesArrayItem)
		{
			$fileInfo = \CFile ::GetFileArray($filesArrayItem);
			$element["fileName"] = $fileInfo['ORIGINAL_NAME'];
			$element["filebase64"] = base64_encode((file_get_contents('https://crm.seller-capital.ru' . $fileInfo['SRC'])));;
			array_push($post["files"], $element);
		}


		$fileJsonName = $_SERVER["DOCUMENT_ROOT"] . "/" . \COption ::GetOptionString("main", "upload_dir") . "/services_sodeistvie/nopaperjson/out_" . '{{ID}}' . ".json";
		file_put_contents($fileJsonName, json_encode($post, JSON_UNESCAPED_UNICODE));
		//file_get_contents($fileJsonName);

		//Заполняем реквизит смарт-элемента
		$entityTypeId = 191;//Идентификатор типа crm сущности
		$itemId = "{{ID}}";//Идентификатор элемента
		$item = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($entityTypeId) -> getItem($itemId);
		$item -> set("UF_CRM_30_SS_POST", "out_" . '{{ID}}' . ".json");
		$result = $item -> save();



		require_once($_SERVER['DOCUMENT_ROOT'] . "/services_sodeistvie/lib/nopaper/api.php");

		//$post = \Bitrix\Main\Web\Json::decode('{{post}}');

		$fileJsonName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/nopaperjson/out_".'{{ID}}'.".json";
		$post = \Bitrix\Main\Web\Json::decode(file_get_contents($fileJsonName));
		$response = send_nopaper('{{Организация}}','/lk-api/external/api/document/create-for-client',$post);


		$fileJsonName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/nopaperjson/in_".'{{ID}}'.".json";
		file_put_contents($fileJsonName, $response);

		//Заполняем реквизит смарт-элемента
		$entityTypeId = 191;//Идентификатор типа crm сущности
		$itemId = "{{ID}}";//Идентификатор элемента
		$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->getItem($itemId);
		$item->set("UF_CRM_30_SS_POST","out_".'{{ID}}'.".json");
		$item->set("UF_CRM_30_SS_RESPONSE","in_".'{{ID}}'.".json");

		$resp = \Bitrix\Main\Web\Json::decode($response);
		if ($resp['documentId']) {$item->set("UF_CRM_30_SS_DOCUMENTID", $resp['documentId']);};

		$result = $item->save();
	}

	//Получение статуса пакета
	public function getStatusPackage() {

	}

	//Подписание серверной подписью
	public function setServerSigned() {

	}
	//Отправка на подписание клиенту
	public function post() {

	}

	//Получение идентификаторов файлов в пакете
	public function getIdsFiles($package) {

	}

	//Получение файлов по идентификаторам
	public function getFiles($ids) {

	}

	//Получение подписей к файлам
	public function getSignedFiles($package) {

	}

	//Изменить статус пакета
}