<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;
use \KPLab\Logs;

define("LOG_ORDLAB_CREATIVES", $_SERVER['DOCUMENT_ROOT']."/local/classes/ordlab/ktu.log");
define("ORD_TOKEN_KEY_TEST","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNzc5MzA4NmMtZTBmZi00MzRlLThhODEtMTA4M2MzZDcyMGVjIiwidXNlcklkIjoiODU0ZjUwNDEtODYyNy00NzJhLWI0MTktNTlkNWY5NTA5NTRmIiwiaWF0IjoxNzA3MzIxNzYwLCJqdGkiOiIwZmQzZmU4Yy03ZDU5LTRjMjItODNlOC05OWZmZWRmZGFmZDgifQ.B7Fs9HjOEQUp4QYCFBCWp2E6J6dAJFUXYNeyrEAiUyk");
define("ORD_TOKEN_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiN2Q2MzVhNTYtNzFhNC00NWU1LTk4ZTctYTllYTkxMzVlMWY4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzEzMzI5NjQxLCJqdGkiOiJlN2MyNzMyMy02NWE4LTQzMDgtYTgzNS00N2NlOWVhNjEwMWEifQ.9CqUMKlWQAg1_oGJeSepOKeAkZNxol6cA_v9cV92jWs");
define("ORD_TOKEN_KEY_SANDBOX","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJvd25lcklkIjoiNjNhMmZhMDQtNWJjNS00NGQ4LTliMjktM2Q0NWM1MzAwOTk4IiwidXNlcklkIjoiMjgxZGE3MjgtMWE5ZC00MDVjLWI1ODUtNTM0NGJiOGMyZDE5IiwiaWF0IjoxNzM5MTYzNjI3LCJqdGkiOiJlZmRmODlkYS1iODY5LTQ1MWQtOGFiMS05ZmE5YTNmMmI2ODMifQ.JNw8jsGl3n_sW1Xuozs5qtWirjB4kjodBYxbpd_XbFM");


class DeleteFromORD {
    const point = "BX_ORDLAB";

    //Функция для запуска процесса в Б24
    public static function DeleteFromOrd($actualMethod = null,$cause = null,$id = null) {
        $timeData = Logs\TimeData::start();
        Loader::includeModule('iblock');
        
        $errMsg = null;
        $params['cause'] = $cause;
        $params['id'] = $id;
        $jsonData = json_encode($params, JSON_UNESCAPED_UNICODE);
        $objectData['ITEM_ID'] = null;
        $objectData['ITEM_TYPE_ID'] = null;
        $objectData['ITEM_TITLE'] = "Подача заявки на удаление в ОРД, метод $actualMethod";
        $objectData['METHOD'] = "POST";
        if(defined("ORD_TOKEN_KEY_TEST") && defined("ORD_TOKEN_KEY")) {
            $jsonResponse = \KPLab\Curl::post_ord(ORD_TOKEN_KEY, $actualMethod, $jsonData, $objectData, $timeData, self::point);
            print_r($params);
            print_r($jsonResponse);
            if(!empty($jsonResponse['success'])) {
                return null;
            }
            $textError = json_decode($jsonResponse['error'],true)['detail'];
            strpos($textError,"entity have dependents: ") ? $errMsg = substr($textError, strpos($textError,"entity have dependents: ") + strlen("entity have dependents: ")) : $errMsg = null;;
                        
            if($errMsg!=null) {
                $parts = preg_split('/(\w+:\s)/', $errMsg, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                $data = [];
                for ($i = 0; $i < count($parts); $i += 2) {
                    $key = rtrim(str_replace(':', '', $parts[$i]));
                    $value = $parts[$i + 1];

                    // Split the value by comma
                    $values = explode(',', $value);
                    $trimmedValues = array_map('trim', $values); // Trim whitespace from each value
                    $data[$key] = $trimmedValues;
                }
                print_r($data);
                return $data;
            }
            elseif ($errMsg == null) {
                    return false;
            }
            else {
                return "Ошибка! Кажется к элементу привязаны сущности: ".$errMsg;
            }
        }
        return false;
    }
}
