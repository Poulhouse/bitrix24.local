<?php namespace KPLab;

use \Bitrix\Main\Web\HttpClient;
use \Bitrix\Main\Web\Uri;
use \Bitrix\Main\Web\Http\Request;
use \Bitrix\Main\Web\Http\Method;
use \Bitrix\Main\Web\Http\ClientException;
use \Bitrix\Main\Web\Http\MultipartStream;
use \KPLab\Logs;
use \KPLab\API\V2\LogsAction;

define("LOG_CURL", $_SERVER['DOCUMENT_ROOT']."/local/classes/curl.log");
\Bitrix\Main\Loader::includeModule('kplab.api.v2');

class Curl {
	public static function post_v2($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation)
	{

        \Bitrix\Main\Loader::includeModule('kplab.api.v2');

        $http = new HttpClient(['version' => HttpClient::HTTP_1_1]);

        $headersRequest = array(
            "key" => "{$tokenKey}",
            "Content-Type" => "application/json"
        );
        $http->setHeaders($headersRequest);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

        if (!$emulation) {
            $response = $http->post($url, $jsonData);

            $result = $http->getResult();
            Logs\File::AddMessage($result,"result",LOG_CURL);
            $status = $http->getStatus();
            $headers = $http->getHeaders();
            if ($response !== false) {
                if ($status == 200) {
                    $jsonRes['success'] = $result;
                    $jsonRes['error'] = "";
                } else {
                    $jsonRes['error'] = $status . " -- " . $response;
                    $jsonRes['success'] = null;
                }
            }
            else {
				$jsonRes['error'] = 'Нет соединения';
				$jsonRes['success'] = "";
                $statusRequest = 'Failed';
			}


            // Логируем информацию
            LogsAction::Request(
                $objectData,
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                'POST',       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                $jsonRes,                      // Ответ на запрос
                $timeData,                          // Время
                $jsonData,               // Тело запроса
                json_encode($headersRequest),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                true                              // Тип запроса (если есть)
            );

			Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $headersRequest);
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			//Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);

            // Логируем информацию
            LogsAction::Request(
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                'POST',       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                $emulationRes,                      // Ответ на запрос
                $timeData,                          // Время
                $jsonData,               // Тело запроса
                json_encode($headersRequest),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                true                              // Тип запроса (если есть)
            );
			Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $headersRequest);

			return $emulationRes;
		}

	}
	public static function post_MYFi($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation)
	{
        \Bitrix\Main\Loader::includeModule('kplab.api.v2');

        $http = new HttpClient(['version' => HttpClient::HTTP_1_1]);

        $headersRequest = array(
            "Token" => "{$tokenKey}",
            "Content-Type" => "application/json"
        );
        $http->setHeaders($headersRequest);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

        if (!$emulation) {
            $response = $http->post($url, $jsonData);

            $result = $http->getResult();
            Logs\File::AddMessage($result,"result",LOG_CURL);
            $status = $http->getStatus();
            $headers = $http->getHeaders();
            if ($response !== false) {
                if ($status == 200) {
                    $jsonRes['success'] = $result;
                    $jsonRes['error'] = "";
                } else {
                    $jsonRes['error'] = $status . " -- " . $response;
                    $jsonRes['success'] = null;
                    $statusRequest = 'Failed';
                }
            }
            else {
                $jsonRes['error'] = 'Нет соединения';
                $jsonRes['success'] = "";
                $statusRequest = 'Failed';
            }


            // Логируем информацию
            LogsAction::Request(
                $objectData,
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                'POST',       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                $jsonRes,                      // Ответ на запрос
                $timeData,                          // Время
                $jsonData,               // Тело запроса
                json_encode($headersRequest),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                true                              // Тип запроса (если есть)
            );

            Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $headersRequest);
            return $jsonRes;
        }
        else {
            $emulationRes['success'] = "Эмуляция запроса успешна произведена!";
            $emulationRes['error'] = "";

            //Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);

            // Логируем информацию
            LogsAction::Request(
                $methodName,                        // Метод запроса (имя метода)
                $url,                               // URL запроса
                $controllerName,                    // имя текущего контроллера
                'POST',       // Метод запроса (POST или GET)
                $statusRequest,                     // Статус запроса
                $emulationRes,                      // Ответ на запрос
                $timeData,                          // Время
                $jsonData,               // Тело запроса
                json_encode($headersRequest),// Заголовки запроса
                $taskId,                            // Task ID (если есть)
                0,                              // ID Типа запроса (если есть)
                true                              // Тип запроса (если есть)
            );
            Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $headersRequest);

            return $emulationRes;
        }

	}

	public static function postApiSE_LK_Bearer($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation, $ping, $headerResponse)
	{
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json-patch+json"
		);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

		if (!$emulation) {
			$curlOptions = [
				CURLOPT_URL => $url,
				CURLOPT_HEADER => $headerResponse,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $jsonData,
				CURLOPT_HTTPHEADER => $curlHeaders,
			];
			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);
			$response = curl_exec($ch);

			if ($response !== false)
			{
				$ch_info = curl_getinfo($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$header = substr($response, 0, $ch_info['header_size']);
				$html = substr($response, $ch_info['header_size']);

				$newHtml = substr($html, 0, -1);
				$html = substr($newHtml, 1);

				$jsonRes['success'] = $response;
				$jsonRes['http_code'] = $http_code;
				$jsonRes['error'] = "";

				if ($http_code !== 200)
				{
					$jsonRes['error'] = $http_code . " -- " . $header . " -- " . $html;
					$jsonRes['http_code'] = $http_code;
					$jsonRes['success'] = "";
                    $statusRequest = 'Failed';
				}
			} else
			{
				$jsonRes['error'] = 'Нет соединения';
				$jsonRes['http_code'] = "";
				$jsonRes['success'] = "";
                $statusRequest = 'Failed';
			}
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $jsonRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
            }
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $emulationRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $curlHeaders);
            }

			return $emulationRes;
		}

	}
	public static function postApiSE_LK($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation, $ping, $headerResponse)
	{

		//Logs\File::AddMessage($jsonData,"jsonData",LOG_CURL);

		$curlHeaders = array(
			"key: {$tokenKey}",
			"Content-Type: application/json-patch+json"
		);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

		if (!$emulation) {
			$curlOptions = [
				CURLOPT_URL => $url,
				CURLOPT_HEADER => $headerResponse,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $jsonData,
				CURLOPT_HTTPHEADER => $curlHeaders,
			];
			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);
			$response = curl_exec($ch);

			if ($response !== false)
			{
				$ch_info = curl_getinfo($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$header = substr($response, 0, $ch_info['header_size']);
				$html = substr($response, $ch_info['header_size']);

				$newHtml = substr($html, 0, -1);
				$html = substr($newHtml, 1);

				$jsonRes['success'] = $response;
				$jsonRes['http_code'] = $http_code;
				$jsonRes['error'] = "";

				if ($http_code !== 200)
				{
					$jsonRes['error'] = $http_code . " -- " . $header . " -- " . $html;
					$jsonRes['http_code'] = $http_code;
					$jsonRes['success'] = "";
                    $statusRequest = 'Failed';
				}
			} else
			{
				$jsonRes['error'] = 'Нет соединения';
				$jsonRes['http_code'] = "";
				$jsonRes['success'] = "";
                $statusRequest = 'Failed';
			}
			Logs\File::AddMessage($jsonRes,"jsonRes",LOG_CURL);
			if(!$ping) {

                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $jsonRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                          // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
            }
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $emulationRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $curlHeaders);
            }

			return $emulationRes;
		}

	}
	public static function postInternalSE_LK_Bearer($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation, $ping, $headerResponse)
	{
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json-patch+json"
		);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

		if (!$emulation) {
			$curlOptions = [
				CURLOPT_URL => $url,
				CURLOPT_HEADER => $headerResponse,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $jsonData,
				CURLOPT_HTTPHEADER => $curlHeaders,
			];
			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);
			$response = curl_exec($ch);

			if ($response !== false)
			{
				$ch_info = curl_getinfo($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$header = substr($response, 0, $ch_info['header_size']);
				$html = substr($response, $ch_info['header_size']);

				$newHtml = substr($html, 0, -1);
				$html = substr($newHtml, 1);

				$jsonRes['success'] = $response;
				$jsonRes['http_code'] = $http_code;
				$jsonRes['error'] = "";

				if ($http_code !== 200)
				{
					$jsonRes['error'] = $http_code . " -- " . $header . " -- " . $html;
					$jsonRes['http_code'] = $http_code;
					$jsonRes['success'] = "";
                    $statusRequest = 'Failed';
				}
			} else
			{
				$jsonRes['error'] = 'Нет соединения';
				$jsonRes['http_code'] = "";
				$jsonRes['success'] = "";
                $statusRequest = 'Failed';
			}
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $jsonRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
            }
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $emulationRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $curlHeaders);
            }

			return $emulationRes;
		}

	}
	public static function postInternalSE_LK($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation, $ping, $headerResponse)
	{
		//Logs\File::AddMessage($jsonData,"jsonData",LOG_CURL);

		$curlHeaders = array(
			"key: {$tokenKey}",
			"Content-Type: application/json-patch+json"
		);

        $statusRequest = 'Success';
        $controllerName = "";
        $methodName = __FUNCTION__;
        $taskId = null;

		if (!$emulation) {
			$curlOptions = [
				CURLOPT_URL => $url,
				CURLOPT_HEADER => $headerResponse,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $jsonData,
				CURLOPT_HTTPHEADER => $curlHeaders,
			];
			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);
			$response = curl_exec($ch);

			if ($response !== false)
			{
				$ch_info = curl_getinfo($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$header = substr($response, 0, $ch_info['header_size']);
				$html = substr($response, $ch_info['header_size']);

				$newHtml = substr($html, 0, -1);
				$html = substr($newHtml, 1);

				$jsonRes['success'] = $response;
				$jsonRes['http_code'] = $http_code;
				$jsonRes['error'] = "";

				if ($http_code !== 200)
				{
					$jsonRes['error'] = $http_code . " -- " . $header . " -- " . $html;
					$jsonRes['http_code'] = $http_code;
					$jsonRes['success'] = "";
                    $statusRequest = 'Failed';
				}
			} else
			{
				$jsonRes['error'] = 'Нет соединения';
				$jsonRes['http_code'] = "";
				$jsonRes['success'] = "";
                $statusRequest = 'Failed';
			}
			Logs\File::AddMessage($jsonRes,"jsonRes",LOG_CURL);

			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $jsonRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
            }
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);
			if(!$ping) {
                // Логируем информацию
                LogsAction::Request(
                    $objectData,
                    $methodName,                        // Метод запроса (имя метода)
                    $url,                               // URL запроса
                    $controllerName,                    // имя текущего контроллера
                    'POST',       // Метод запроса (POST или GET)
                    $statusRequest,                     // Статус запроса
                    $emulationRes,                      // Ответ на запрос
                    $timeData,                          // Время
                    $jsonData,               // Тело запроса
                    json_encode($curlHeaders),// Заголовки запроса
                    $taskId,                            // Task ID (если есть)
                    0,                              // ID Типа запроса (если есть)
                    true                              // Тип запроса (если есть)
                );
                Logs\IBlock::setData($url, $jsonData, $emulationRes, $objectData, $timeData, $point, $curlHeaders);
            }

			return $emulationRes;
		}

	}

	public static function get_LK($tokenKey, $url, $jsonData, $objectData, $timeData, $point) {
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json"
		);
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $curlHeaders,
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			//$str = 'с.т. "АСТРА"';
			//$str = str_replace('"', '', $str);
			$html = substr($response, $ch_info['header_size']);

			$newHtml = substr($html,0,-1);
			$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['http_code'] = $http_code;
			$jsonRes['error'] = "";

			if($http_code !== 200) {
				$jsonRes['error'] = $http_code." -- ".$header." -- ".$html;
				$jsonRes['http_code'] = $http_code;
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['http_code'] = "";
			$jsonRes['success'] = "";
		}
		Logs\File::AddMessage($jsonRes,"jsonRes",LOG_CURL);

		//AddMessage2Log($jsonRes,"jsonRes setData");
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}

	public static function post_with_files($url, $params) {
		if($url != '')
		{
			$multipartData = $params['multipart_data'];
			$fileSize = $params['files_size'];
			$tokenKey = $params['token_key'];
			$emulation = $params['emulation'];
			$objectData = $params['object_data'];
			$timeData = $params['time_data'];
			$point = $params['point'];
			$delimiter = $params['delimiter'];
			$curlHeaders = array("Content-Type:multipart/form-data", "key:{$tokenKey}");// cURL headers for file uploading

			if (!$emulation)
			{
				$uri = new Uri($url);
				$body = new MultipartStream($multipartData);
				$boundary = $body->getBoundary();
				$BUF_LEN = 524288;

				$i=0;
				foreach ($multipartData as $v)
				{
					$k = "files";
					$body->write('--' . $boundary . "\r\n");
					if ((is_resource($v) && get_resource_type($v) === 'stream') || is_array($v))
					{
						$filename = $v['filename'] ?? $k;
						$contentType = $v['contentType'] ?? 'application/octet-stream';
						$body->write('Content-Disposition: form-data; name="' . $k . '"; filename="' . $filename . '"' . "\r\n");
						$body->write('Content-Type: ' . $contentType . "\r\n\r\n");

						if (is_array($v))
						{
							if (isset($v['resource']) && is_resource($v['resource']) && get_resource_type($v['resource']) === 'stream')
							{
								fseek($v['resource'], 0);
								while (!feof($v['resource']))
								{
									$body->write(stream_get_contents($v['resource'], $BUF_LEN));
								}
							}
							else
							{
								if (isset($v['content']))
								{
									$body->write($v['content']);
								}
								else
								{
									throw new \Bitrix\Main\ArgumentException("File `{$k}` not found for multipart upload.", 'data');
								}
							}
						}
						else
						{
							fseek($v, 0);
							while (!feof($v))
							{
								$body->write(stream_get_contents($v, static::BUF_LEN));
							}
						}
					}
					else
					{
						$body->write('Content-Disposition: form-data; name="' . $k . '"' . "\r\n\r\n");
						$body->write($v);
					}

					$body->write("\r\n");
					$i++;
				}

				$body->write('--' . $boundary . "--\r\n");

				$headers = [
					'User-Agent' => 'bitrix',
					'key' => $tokenKey,
					'Content-type' => 'multipart/form-data; boundary=' . $body->getBoundary(),
				];

				$http = new HttpClient([
					'compress' => true,
				]);

				$request = new Request(Method::POST, $uri, $headers, $body);

				foreach ($multipartData as $resource => $v)
				{
					fclose($multipartData[$resource]['resource']);
				}
				try
				{
					$response = $http->sendRequest($request);

					$jsonRes['success'] = (string)$response->getBody();
					$jsonRes['error'] = "";
					$http_code = $response->getStatusCode();
					$header = $response->getHeaders();
					$html = (string)$response->getBody();

					if ($http_code !== 200)
					{
						$jsonRes['error'] = $http_code . " -- " . (string)$header . " -- " . $html;
						$jsonRes['success'] = "";
					}

					$dataJsonFiles = json_encode($multipartData);
					Logs\IBlock ::setData($url, $dataJsonFiles, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
					return $jsonRes;
				}
				catch (ClientException $e)
				{
					$jsonRes['error'] = $e->getMessage();
					$jsonRes['success'] = "";
				}
			}
			else {
				$emulationRes['success'] = "Эмуляция";
				$emulationRes['error'] = "";
				$dataJsonFiles = json_encode($multipartData);
				Logs\File ::AddMessage($emulationRes, "Эмуляция Ответ", LOG_CURL);
				Logs\IBlock ::setData($url, $dataJsonFiles, $emulationRes, $objectData, $timeData, $point, $curlHeaders);

				return $emulationRes;
			}
		}
		else {
			$jsonRes['error'] = 'Нет соединения - ';
			$jsonRes['success'] = "";
		}
		//AddMessage2Log($jsonRes,"jsonRes setData");
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}

	public static function post_multiform_data($tokenKey, $url, $jsonData, $objectData, $timeData, $point, $emulation)
	{
		if($url != '')
		{
			$boundary = uniqid();
			$delimiter = '-------------' . $boundary;

			$curlHeaders = array(
				"key: {$tokenKey}",
				"Content-Type: multipart/form-data; boundary=" . $delimiter,
				"Content-Length: " . strlen($jsonData)
			);

			if (!$emulation)
			{
				$curlOptions = [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $jsonData,
					CURLOPT_HTTPHEADER => $curlHeaders,
				];
				$ch = curl_init();
				curl_setopt_array($ch, $curlOptions);
				$response = curl_exec($ch);

				if ($response !== false)
				{
					$ch_info = curl_getinfo($ch);
					$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$header = substr($response, 0, $ch_info['header_size']);
					$html = substr($response, $ch_info['header_size']);

					$newHtml = substr($html, 0, -1);
					$html = substr($newHtml, 1);

					$jsonRes['success'] = $response;
					$jsonRes['error'] = "";

					if ($http_code !== 200)
					{
						$jsonRes['error'] = $http_code . " -- " . $header . " -- " . $html;
						$jsonRes['success'] = "";
					}
				} else
				{
					$jsonRes['error'] = 'Нет соединения';
					$jsonRes['success'] = "";
				}
				$dataJsonFiles = json_encode($jsonData);
				Logs\IBlock ::setData($url, $dataJsonFiles, $jsonRes, $objectData, $timeData, $point, $curlHeaders);
				return $jsonRes;
			} else
			{
				$emulationRes['success'] = "Эмуляция";
				$emulationRes['error'] = "";
				$dataJsonFiles = json_encode($jsonData);
				Logs\File ::AddMessage($emulationRes, "Эмуляция Ответ", LOG_CURL);
				Logs\IBlock ::setData($url, $dataJsonFiles, $emulationRes, $objectData, $timeData, $point, $curlHeaders);

				return $emulationRes;
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['success'] = "";
			return $jsonRes;
		}

	}

	public static function post_ord($tokenKey, $url, $jsonData, $objectData, $timeData, $point) {
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json"
		);
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => $curlHeaders,
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			//$str = 'с.т. "АСТРА"';
			//$str = str_replace('"', '', $str);
			$html = substr($response, $ch_info['header_size']);

			Logs\File::AddMessage($html,"Ответ",LOG_CURL);

			//$newHtml = substr($html,0,-1);
			//$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['header'] = $header;
			$jsonRes['error'] = "";
			$successCodes = [200,201,202];
			if(!in_array($http_code, $successCodes)) {
				$jsonRes['error'] = $html;
				$jsonRes['errorArray'] = json_decode($html, true);
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['header'] = "";
			$jsonRes['success'] = "";
		}
		//Logs\File::AddMessage($html,"Ответ",LOG_CURL);
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}
	public static function post_OneC($username,$password, $url, $jsonData, $objectData, $timeData, $point) {

		Logs\File::AddMessage($jsonData,"jsonData",LOG_CURL);

		$curlHeaders = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Accept-Charset: UTF-8'
		);

		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => $username.':'.$password,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_HTTPHEADER => $curlHeaders
		];

		$ch = curl_init();

		curl_setopt_array($ch, $curlOptions);

		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			Logs\File::AddMessage($http_code,"http_code",LOG_CURL);

			$jsonRes['success'] = $response;
			$jsonRes['header'] = "";
			$jsonRes['error'] = "";
			$successCodes = [200,201,202];

			if(!in_array($http_code, $successCodes)) {
				$jsonRes['error'] = $response;
				$jsonRes['errorArray'] = json_decode($response, true);
				$jsonRes['success'] = "";
			}

		} else {
			$jsonRes['error'] = "Битрикс: Ошибка! Получен пустой ответ 1С. Возможна ошибка расшифровки JSON в 1С.";
			$jsonRes['header'] = "";
			$jsonRes['success'] = "";
		}

		Logs\File::AddMessage($jsonRes,"jsonRes1",LOG_CURL);
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}
	public static function put_ord($tokenKey, $url, $jsonData, $objectData, $timeData, $point) {
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json"
		);
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => $curlHeaders,
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			//$str = 'с.т. "АСТРА"';
			//$str = str_replace('"', '', $str);
			$html = substr($response, $ch_info['header_size']);

			$newHtml = substr($html,0,-1);
			$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['header'] = $header;
			$jsonRes['error'] = "";
			$successCodes = [200,201,202];
			if(!in_array($http_code, $successCodes)) {
				$jsonRes['error'] = "{".$html."}";
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['header'] = "";
			$jsonRes['success'] = "";
		}
		//AddMessage2Log($jsonRes,"jsonRes setData");
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}
	public static function get_ord($tokenKey, $url, $jsonData, $objectData, $timeData, $point) {
		$curlHeaders = array(
			"Authorization: Bearer {$tokenKey}",
			"Content-Type: application/json"
		);
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $curlHeaders,
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			//$str = 'с.т. "АСТРА"';
			//$str = str_replace('"', '', $str);
			$html = substr($response, $ch_info['header_size']);

			$newHtml = substr($html,0,-1);
			$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['error'] = "";

			if($http_code !== 200) {
				$jsonRes['error'] = $http_code." -- ".$header." -- ".$html;
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['success'] = "";
		}
		//AddMessage2Log($jsonRes,"jsonRes setData");
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}
	public static function post($tokenKey, $url, $jsonData, $objectData, $timeData, $logFilePath) {
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => array(
				"key: {$tokenKey}",
				"Content-Type: application/json"
			),
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			//$str = 'с.т. "АСТРА"';
			//$str = str_replace('"', '', $str);
			$html = substr($response, $ch_info['header_size']);

			$newHtml = substr($html,0,-1);
			$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['error'] = "";

			if($http_code !== 200) {
				$jsonRes['error'] = $http_code." -- ".$header." -- ".$html;
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['success'] = "";
		}

		Logs\File::set($url,$jsonData,$jsonRes,$objectData,$timeData,$logFilePath);

		return $jsonRes;
	}
	public static function postWithoutAuth($url, $jsonData, $objectData, $timeData, $point) {
		$curlHeaders = array(
			"Content-Type: application/json"
		);
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => $curlHeaders,
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			$html = substr($response, $ch_info['header_size']);
			Logs\File::AddMessage($html,"Ответ",LOG_CURL);
			$jsonRes['success'] = $html;
			$jsonRes['header'] = $header;
			$jsonRes['error'] = "";
			$successCodes = [200,201,202];
			if(!in_array($http_code, $successCodes)) {
				$jsonRes['error'] = $html;
				$jsonRes['errorArray'] = json_decode($html, true);
				$jsonRes['success'] = "";
			}
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['header'] = "";
			$jsonRes['success'] = "";
		}
		$res = Logs\IBlock::setData($url, $jsonData, $jsonRes, $objectData, $timeData, $point, $curlHeaders);

		return $jsonRes;
	}

	public static function postWithQueryUrl($tokenKey, $queryUrl, $objectData, $timeData, $point, $emulation,
	                                        $httpHeaders, $data = null, $multipartData = false) {
		$jsonData = json_encode($data,JSON_UNESCAPED_UNICODE);


		if (!$emulation)
		{
			$http = new HttpClient(['version' => HttpClient::HTTP_1_1]);

			$http->setHeaders($httpHeaders);

			if(!is_null($data)) {
				$response = $http->post($queryUrl, $jsonData, $multipartData);
				Logs\File::AddMessage($response,"response1",LOG_CURL);
			} else {
				$response = $http->post($queryUrl, $data, $multipartData);
				Logs\File::AddMessage($response,"response2",LOG_CURL);
			}

			$result = $http->getResult();

			$status = $http->getStatus();
			$headers = $http->getHeaders();

			//Logs\File::AddMessage($status,"status",LOG_CURL);
			//Logs\File::AddMessage($headers,"headers",LOG_CURL);

			if ($status == 200)
			{
				$jsonRes['success'] = $status . " | " .$response;
				$jsonRes['response'] = $response;
				$jsonRes['error'] = "";
			} else
			{
				$jsonRes['error'] = $status . " -- " . $response;
				$jsonRes['response'] = null;
				$jsonRes['success'] = null;
			}
			Logs\IBlock::setData($queryUrl, $jsonData, $jsonRes, $objectData, $timeData, $point, $httpHeaders);
			return $jsonRes;
		}
		else {
			$emulationRes['success'] = "Эмуляция запроса успешна произведена!";
			$emulationRes['error'] = "";

			Logs\File::AddMessage($emulationRes,"Эмуляция Ответ",LOG_CURL);
			Logs\IBlock::setData($queryUrl, $jsonData, $emulationRes, $objectData, $timeData, $point, $httpHeaders);

			return $emulationRes;
		}
	}
}