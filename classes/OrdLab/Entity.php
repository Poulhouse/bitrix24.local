<?php namespace KPLab\OrdLab;

use Bitrix\Main\Loader;

class Entity {
	public static function getItem($IBLOCK_ID, int $elementId) {
		Loader::includeModule('iblock');

		$arOrder = ['ID' => 'ASC'];

		if($IBLOCK_ID == 177) //Договор только 1
			$arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_982_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];

		if($IBLOCK_ID == 176) //Организация только 1
			$arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_1014_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
		
		if($IBLOCK_ID == 174) //Организация только 1
			$arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_998_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];


		$arGroupBy = false;
		$arNavStartParams = [];
		$arSelect = ["*","PROPERTY_*"];

		$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

		while($ob = $res->GetNextElement()) {
			$arFields = $ob->GetFields();
			$arProps = $ob->GetProperties();
			if(!empty($arFields) && !empty($arProps))
			{
				$result['FIELDS'] = $arFields;
				$result['PROPERTIES'] = $arProps;
				return $result;
			}
		}

		return null;

	}
	public static function getItembyID($id, $IBLOCK_ID, int $elementId) {
		Loader::includeModule('iblock');

		$arOrder = ['ID' => 'ASC'];

		if($IBLOCK_ID == 177) //Договор
			$arFilter = ["ID" => $id, "IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_982_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];

		if($IBLOCK_ID == 176) //Организация
			$arFilter = ["ID" => $id, "IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_1014_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];

		if($IBLOCK_ID == 174) //Креатив
			$arFilter = ["ID" => $id, "IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_998_VALUE" => $elementId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];


		$arGroupBy = false;
		$arNavStartParams = [];
		$arSelect = ["*","PROPERTY_*"];

		$res = \CIBlockElement::GetList($arOrder,$arFilter,$arGroupBy,$arNavStartParams,$arSelect);

		while($ob = $res->GetNextElement()) {
			$arFields = $ob->GetFields();
			$arProps = $ob->GetProperties();
			if(!empty($arFields) && !empty($arProps))
			{
				$result['FIELDS'] = $arFields;
				$result['PROPERTIES'] = $arProps;
				return $result;
			}
		}

		return null;

	}

	public static function getPropertyEnumIdByArray($RESPONSE_ARRAY, $IBLOCK_ID) {
		//$CODE = key($RESPONSE_ARRAY);
		$arOrder = Array("DEF" => "DESC", "SORT" => "ASC");
		$arFilter = Array("IBLOCK_ID" => $IBLOCK_ID);
		$db_enum_list = \CIBlockPropertyEnum::GetList($arOrder, $arFilter);

		while($ar_enum_list = $db_enum_list->GetNext()) {
			$arrProp[$ar_enum_list['XML_ID']] = $ar_enum_list['ID'];
		}

		return $arrProp[$RESPONSE_ARRAY];
	}

	public static function getPropertyEnumValueByArray($RESPONSE_ARRAY, $IBLOCK_ID) {
		//$CODE = key($RESPONSE_ARRAY);
		$arOrder = Array("DEF" => "DESC", "SORT" => "ASC");
		$arFilter = Array("IBLOCK_ID" => $IBLOCK_ID);
		$db_enum_list = \CIBlockPropertyEnum::GetList($arOrder, $arFilter);

		while($ar_enum_list = $db_enum_list->GetNext()) {
			$arrProp[$ar_enum_list['XML_ID']] = $ar_enum_list['VALUE'];
		}

		return $arrProp[$RESPONSE_ARRAY];
	}


	public static function setToList($itemId, $IBLOCK_ID, $PROPERTY_VALUE, $PROPERTY_CODE) {
		Loader::includeModule('iblock');
		echo PHP_EOL."------".PHP_EOL;
		print_r($itemId);
		echo PHP_EOL."------".PHP_EOL;
		if($itemId) {
			\CIBlockElement::SetPropertyValues($itemId, $IBLOCK_ID, $PROPERTY_VALUE, $PROPERTY_CODE);
			return true;
		}
		return false;
	}
}
