<? namespace KPLab\OneC;

use Bitrix\Main\Loader;
use \KPLab\Logs;
use KPLab\OrdLab\Entity;

define("LOG_ONEC_SYNC", $_SERVER['DOCUMENT_ROOT']."/local/classes/onec/sync.log");

class ContactPersons {
	public static function setArray($clientId, string $prefix) {
		Loader::includeModule('iblock');
		$IBLOCK_ID = 179;
		$contactPersons = [];
		$arOrder = ['ID' => 'ASC'];
		$arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_1024_VALUE" => $prefix.$clientId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
		$arGroupBy = false;
		$arNavStartParams = [];
		$arSelect = ["*","PROPERTY_*"];
		$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

		while($ob = $res->GetNextElement())
		{
			$arFields = $ob->GetFields();
			$arProps = $ob->GetProperties();
			//Logs\File ::AddMessage($arProps['KLIENT']['VALUE'], "Контакт Битрикс: {$id} -- Контактные лица из 1С", LOG_ONEC_SYNC);
			$contactPersons[] = array(  // собираем массив того, что нам нужно
				'nameText' => $arFields['NAME'],
				'birthDate' => $arProps['DATA_ROZHDENIYA']['VALUE'],
				'phone' => $arProps['TELEFON']['VALUE'],
				'stateId' => $arProps['STATUS']['VALUE_XML_ID'],
				'commentText' => $arProps['KOMMENTARIY']['VALUE'],
			);
		}

		if($prefix == "C_")	Logs\File ::AddMessage($contactPersons, "Контакт Битрикс: {$clientId} -- Контактные лица в 1С", LOG_ONEC_SYNC);
		if($prefix == "CO_") Logs\File ::AddMessage($contactPersons, "Компания Битрикс: {$clientId} -- Контактные лица в 1С", LOG_ONEC_SYNC);

		return $contactPersons;
	}
	public static function getDetails($id, $array, $prefix = "C_") {
		Loader::includeModule('iblock');
		$IBLOCK_ID = 179;

		//if($prefix == "C_")	Logs\File ::AddMessage($array, "Контакт Битрикс: {$id} -- Контактные лица из 1С", LOG_ONEC_SYNC);
		//if($prefix == "CO_") Logs\File ::AddMessage($array, "Компания Битрикс: {$id} -- Контактные лица из 1С", LOG_ONEC_SYNC);

		$contactPersons = [];

		$arOrder = ['ID' => 'ASC'];
		$arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_1024_VALUE" => $prefix.$id, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
		$arGroupBy = false;
		$arNavStartParams = [];
		$arSelect = ["*","PROPERTY_*"];
		$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

		while($ob = $res->GetNextElement())
		{
			$arFields = $ob->GetFields();
			$arProps = $ob->GetProperties();
			//Logs\File ::AddMessage($arProps['KLIENT']['VALUE'], "Контакт Битрикс: {$id} -- Контактные лица из 1С", LOG_ONEC_SYNC);
			$ar_Result[] = array(  // собираем массив того, что нам нужно
				'ID' => $arFields['ID'], // id
			);
		}

		foreach ($ar_Result as $element) {
			//if($prefix == "C_")	Logs\File ::AddMessage($element, "Удаление элемента инфоблока из Битрикс у Контакта: {$id}", LOG_ONEC_SYNC);
			//if($prefix == "CO_") Logs\File ::AddMessage($element, "Удаление элемента инфоблока  из Битрикс у Компании: {$id}", LOG_ONEC_SYNC);
			\CIBlockElement::Delete($element["ID"]);

		}

		foreach ($array as $key => $value) {
			$name = $value['nameText'];
			$birthDate = $value['birthDate']; // 12.06.1984
			$phone = $value['phone']; //9225678329 (без 8 / +7 / 7)
			$stateId = $value['stateId']; // от 1 до 18
			$commentText = $value['commentText'];

			$el = new \CIBlockElement;

			$PROPERTY_VALUES = array();

			$STATUS_PROPERTY_ENUM_ID = Entity::getPropertyEnumIdByArray($stateId, $IBLOCK_ID);

			$PROPERTY_VALUES['STATUS'] = ["VALUE" => $STATUS_PROPERTY_ENUM_ID];
			$PROPERTY_VALUES['DATA_ROZHDENIYA'] = ['VALUE' => $birthDate];
			$PROPERTY_VALUES['TELEFON'] = $phone;
			$PROPERTY_VALUES['KOMMENTARIY'] = $commentText;
			$PROPERTY_VALUES['KLIENT'] = ['VALUE' => $prefix.$id];

			$arContactPersonArray = Array(
				"MODIFIED_BY"    => 1, // элемент изменен текущим пользователем
				"IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
				"IBLOCK_ID"      => $IBLOCK_ID,
				"PROPERTY_VALUES"=> $PROPERTY_VALUES,
				"NAME"           => $name,
				"ACTIVE"         => "Y",            // активен
			);
			if($contactPersonId = $el->Add($arContactPersonArray)) {
				//Logs\File ::AddMessage($arContactPersonArray, "Новый элемент инфоблока {$contactPersonId} -- Контактные лица из 1С", LOG_ONEC_SYNC);
				$contactPersons[] = $contactPersonId;
			}else {
				$status = "Error: ".$el->LAST_ERROR;
			}
		}
		if(!empty($contactPersons)) { return $contactPersons; } else { return $status; }
	}
}