<?php
define('display_error', 1);
class Bitrix24API {
	private $webhookUrl;
	private $requisites;

	public function __construct() {
		// Укажите URL вебхука для доступа к Bitrix24
		$this->webhookUrl = 'https://crm.seller-capital.ru/rest/1/3lsxt0qfwbns0wve/';
	}

	public function searchDuplicatesByINN($innFilter) {
		// Пример запроса к Bitrix24 API для получения реквизитов
		$url = $this->webhookUrl . 'crm.requisite.list.json';

		$params = [
			'filter' => ['RQ_INN' => $innFilter],
			'select' => ['*']
		];

		$response = $this->sendRequest($url, $params);



		// Отладочные сообщения
		$this->log[0] = "Request URL: " . $url;
		$this->log[1] .= "Request Params: " . json_encode($params);
		$this->log[2] .= "Response: " . $response;


		if (isset($response['error'])) {
			$this->log[3] = "Bitrix24 API Error: " . $response['error_description'];
			return [];
		}
		if (empty($response)) {
			$this->log[3] = "Empty response from Bitrix24 API";
		} else {
			$this->log[3] = "Response received: " . json_encode($response);
		}

		$this->requisites = $response['result'] ?? [];

		// Логика поиска дублей по ИНН
		$duplicates = [];
		$innCounts = [];

		foreach ($this->requisites as $requisite) {
			$inn = $requisite['RQ_INN'];
			$entityId = $requisite['ENTITY_ID'];
			$templateName = $requisite['NAME'];
			$crmLink = $this->getCrmLink($requisite['ENTITY_TYPE_ID'], $entityId);

			if (!isset($innCounts[$inn])) {
				$innCounts[$inn] = [];
			}

			if (!isset($innCounts[$inn][$entityId])) {
				$innCounts[$inn][$entityId] = 0;
			}

			$innCounts[$inn][$entityId]++;

			$duplicates[] = [
				'inn' => $inn,
				'entityName' => 'Entity ' . $entityId,
				'templateName' => $templateName,
				'crmLink' => $crmLink,
				'count' => $innCounts[$inn][$entityId],
				'sameEntity' => count($innCounts[$inn]) === 1
			];
		}

		return $duplicates;
	}

	public function proverkaMethods() {
		$url = $this->webhookUrl.'methods.json';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);

		$httpCode = curl_getinfo($ch , CURLINFO_HTTP_CODE);

		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			error_log("cURL Error: " . curl_error($ch));
		}
		curl_close($ch);

		return "Available Methods: " . $response;
	}

	private function sendRequest($url, $params) {
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => http_build_query($params),
			CURLOPT_HTTPHEADER => array(
				'application/json; charset=utf-8'
			)
		];

		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$httpCode = curl_getinfo($ch , CURLINFO_HTTP_CODE);

		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			error_log("cURL Error: " . curl_error($ch));
		}
		curl_close($ch);

		return json_decode($response, true);
	}

	private function getCrmLink($entityTypeId, $entityId) {
		// Генерация ссылки на CRM
		if ($entityTypeId == 1) {
			return 'https://crm.seller-capital.ru/crm/contact/details/' . $entityId . '/';
		} elseif ($entityTypeId == 2) {
			return 'https://crm.seller-capital.ru/crm/company/details/' . $entityId . '/';
		}

		return '#';
	}
}
