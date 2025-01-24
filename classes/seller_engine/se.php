<?php namespace KPLab;

define("LOG_SE", $_SERVER['DOCUMENT_ROOT']."/local/classes/seller_engine/se.log");
define("TOKEN_SE", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU");
use \KPLab\Logs;

$module_folder = \Bitrix\Main\Application::getDocumentRoot() . '/local/classes/seller_engine';

\Bitrix\Main\Loader::registerNamespace('KPLab\SellerEngine\Controller', $module_folder . '/controller');

class SellerEngine {
	public $apiUrl;
	public $testApiUrl;
	public $itemTitle;
	public $passportImages;
	public $itemPassportFiles;
	public $errorMessage;
	public $itemId;
	public $entityTypeId;
	public $CURLObjectData;
	public $multipartFormData;
	public $post_data;
	public $postParams;
	public $postResponse;
    public $domain;

	public function __construct()
	{
        // Определяем текущий домен
        $this->domain = $_SERVER['HTTP_HOST'];

        if (strpos($this->domain, 'test') !== false) {
            $this -> apiUrl = "https://api.dev.seller-capital.ru/";
            $this -> internalUrl = "https://internal.dev.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.dev.seller-capital.ru/lk/user/Authenticate";
        } else {
            $this -> apiUrl = "https://api.seller-capital.ru/";
            $this -> internalUrl = "https://internal.seller-capital.ru/";
            $this -> internalUserUrl = "https://internal.seller-capital.ru/lk/user/Authenticate";
        }

		$this->postParams['point'] = "BX_SE";
		$this->errorMessage = null;
	}
	public function getCompany($elementId) {

		$this->itemId = $elementId;
		$this->entityTypeId = \CCrmOwnerType::Company;

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
		$item = $factory -> getItem($this->itemId);

		if($item) {
			//$this->itemData = $item->getData();
			$this->itemTitle = $item->getData()['TITLE'];
			$this->itemPassportFiles = $item->getData()['UF_CRM_6433D94467769'];
		}
		else {
			$this->errorMessage = "Не найдена Компания с ID:{$this->itemId}";
		}


		Logs\File::AddMessage($this,"this Company",LOG_SE);

		return $this;
	}
	public function getContact($elementId) {

		$this->itemId = $elementId;
		$this->entityTypeId = \CCrmOwnerType::Contact;

		$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
		$item = $factory -> getItem($this->itemId);

		if($item) {
			//$this->itemData = $item->getData();
			$this->itemTitle = $item->getData()['TITLE'];
			$this->itemPassportFiles = $item->getData()['UF_CRM_6433D94467769'];
		}
		else {
			$this->errorMessage = "Не найден Контакт с ID:{$this->itemId}";
		}

		return $this;
	}
	public function getPassportImagesPath() {
		if($this->errorMessage)
			return $this->errorMessage;

		//$passportFiles = $this->itemPassportFiles;

		foreach ($this->itemPassportFiles as $k => $passportFile) {
			//$arPassportImages[$k]['files'] = \CFile::GetPath($passportFile);
			$arPassportImages[] = \CFile::GetFileArray($passportFile);
		}
		$this->passportImages = $arPassportImages;

		Logs\File::AddMessage($arPassportImages,"passportImages",LOG_SE);

		return $this->passportImages;
	}
	public function setPassportPOSTUrl() {
		$resRQItem = \CRest::call("crm.requisite.list",[
			"order"=>["DATE_CREATE" => "ASC"],
			"filter"=>["ENTITY_ID"=>$this->itemId],
			"select"=>[ "*" ]
		])['result'];
		if(!empty($resRQItem))
		{
			$sellerInn = $resRQItem[0]['RQ_INN'];
		}
		//$sellerInn = $this->itemData['UF_CRM_63DC8E7C79C19'];

		$this->apiUrl = $this->apiUrl . "Document/RecognizeDocuments?sellerInn={$sellerInn}&type=Passport";

		Logs\File::AddMessage($this->apiUrl,"apiUrl",LOG_SE);
		return $this->apiUrl;
	}
	public function setTestUploadFilesUrl() {
		$resRQItem = \CRest::call("crm.requisite.list",[
			"order"=>["DATE_CREATE" => "ASC"],
			"filter"=>["ENTITY_ID"=>$this->itemId],
			"select"=>[ "*" ]
		])['result'];
		if(!empty($resRQItem))
		{
			$sellerInn = $resRQItem[0]['RQ_INN'];
		}
		//$sellerInn = $this->itemData['UF_CRM_63DC8E7C79C19'];

		$this->testApiUrl = $this->apiUrl . "Document/TestUploadFiles?sellerInn={$sellerInn}&type=Passport";
		return $this->testApiUrl;
	}
	public function getTestUploadFilesUrl() {
		return $this->testApiUrl;
	}
    public function setCURLObjectData() {
        $result['ITEM_ID'] = $this->itemId;
        $result['ITEM_TYPE_ID'] = $this->entityTypeId;
        $result['ITEM_TITLE'] = "Проверка паспорта: ". $this->itemTitle;
        $result['METHOD'] = "POST";
        $result['INIT_OBJECT_URL'] = "https://{$this->domain}/crm/type/{$this->entityTypeId}/details/{$this->itemId}/";
        $this->postParams['object_data'] = $result;

        Logs\File::AddMessage($this->postParams['object_data'],"object_data",LOG_SE);

        return $result;
    }

	public function build_data_files($delimiter, $fields, $files){
		$data = '';
		$eol = "\r\n";
		foreach ($fields as $name => $content) {
			$data .= "--" . $delimiter . $eol
				. 'Content-Disposition: form-data; name="' . $name . "\"".$eol.$eol
				. $content . $eol;
		}
		foreach ($files as $_key => $_val) {
			$data = "--" . $delimiter . $eol
				. 'Content-Disposition: multipart/form-data; name="files"; filename="' . $_val['file_name'] . '"' . $eol
				. 'Content-Type: ' . $_val['file_type'] . $eol
				. 'Content-Transfer-Encoding: binary'.$eol;
			$data .= $eol .
				$data .= $_val['packed_data'] . $eol .
					"--" . $delimiter . "--".$eol;
		}
		return $data;
	}

	public function setCURLFILEData($files) {
		if($this->errorMessage)
			return $this->errorMessage;

		$boundary = uniqid();
		$this->postParams['delimiter'] = '-------------' . $boundary;

		$fileData = array();
		$files_size = 0;
        Logs\File::AddMessage($files, "files", LOG_SE);
		foreach ($files as $file) {
			//$file_path = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/".$file['SUBDIR']."/".$file['FILE_NAME'];
			$file_path = "https://crm.seller-capital.ru/".\COption::GetOptionString("main", "upload_dir")."/".$file['SUBDIR']."/".$file['FILE_NAME'];
			$file_name = $file['FILE_NAME'];
			$file_type = $file['CONTENT_TYPE'];

			$file_size = $file['FILE_SIZE'];
            Logs\File::AddMessage($file_path,"file_path",LOG_SE);
            $res = fopen($file_path, 'r');
            if ($res === false) {
                $error = error_get_last();
                Logs\File::AddMessage('fopen error: ' . $error['message'], "file_path", LOG_SE);
            } else {
                $fileData[] = array('resource' => $res,'filename' => $file_name);
                $files_size += $file_size;
            }
            //$res = file_get_contents($file_path);
            if ($res === false) Logs\File::AddMessage('Failed to open file with file_get_contents', "file_path", LOG_SE);

		}
		$this->postParams['multipart_data'] = $fileData;
		$this->postParams['files_size'] = $files_size;

		Logs\File::AddMessage($this->postParams['multipart_data'],"multipart_data",LOG_SE);

		return $this->postParams['multipart_data'];
	}

	public function get_multipart_data() {
		return $this->postParams['multipart_data'];
	}
	public function getCURLFILEData() {
		return $this->postParams['multipart_data'];
	}
	public function postPassportData($emulation = false)
	{
		if($this->errorMessage)
			return $this->errorMessage;

		$this->postParams['time_data'] = Logs\TimeData::start();
		$this->postParams['emulation'] = $emulation;
		$this->postParams['token_key'] = TOKEN_SE;

		if (defined("TOKEN_SE")) {
			$jsonResponse = \KPLab\Curl::post_with_files($this->apiUrl, $this->postParams);

			if($jsonResponse["success"] !== "") $jsonResponseSuccess = json_decode($jsonResponse["success"], true);

			$factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
			$item = $factory -> getItem($this->itemId);

			$item->set("UF_DOCUMENT_PACKAGE_ID",$jsonResponseSuccess["documentPackageId"]);

			$operation = $factory->getUpdateOperation($item);

			$operationResult = $operation->launch();

			$resRQItem = \CRest::call("crm.requisite.list",[
				"order"=>["DATE_CREATE" => "ASC"],
				"filter"=>["ENTITY_ID"=>$this->itemId],
				"select"=>[ "*" ]
			])['result'];

			if(!empty($resRQItem))
			{
				$resultRequisite = \CRest ::call(
					'crm.requisite.update',
					[
						"id" => $resRQItem[0]['ID'],
						"fields" => [
							'XML_ID' => $jsonResponseSuccess["documentPackageId"],
						]
					]
				);
			}

			$this->postResponse = $jsonResponse;

			return $jsonResponse;
		}
		return false;
	}

	public function postTestUploadFiles($emulation = false)
	{
		$this->postParams['time_data'] = Logs\TimeData::start();
		$this->postParams['emulation'] = $emulation;
		$this->postParams['token_key'] = TOKEN_SE;

		if (defined("TOKEN_SE")) {
			$jsonResponse = \KPLab\Curl::post_with_files($this->testApiUrl, $this->postParams);
			$this->postResponse = $jsonResponse;

			return $jsonResponse;
		}
		return false;
	}

	public function getPostResponse(){
		return $this->postResponse;
	}
	//Logs\File::AddMessage($fields,"fields",LOG_SE);
}