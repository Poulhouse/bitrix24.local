<?php
namespace KPLab\Integration;

use KPLab\Logs;

class OneC
{
	//получаем массив ID элементов списка Бенефициаров, где есть привязка к ID Компании
	public static function get_benificiarByIdUl($id)
	{
		$paramsForGetQuery = [
			'IBLOCK_TYPE_ID' => 'lists',
			'IBLOCK_ID' => 170,
			'FILTER' => [
				'PROPERTY_943' => $id
			]
		];


		$result = \CRest ::call('lists.element.get', $paramsForGetQuery);

		if ($result['total'])
		{
			// Уже существует!
			return $result['result'];
		}

		return [];
	}
	public static function update_benificiar($id,$params = []){

		$contactId_benificiar = ss_sync_get_contact_id($params['ФизическоеЛицо']);
		$priznakOrder_benificiar = $params['ПризнакПорядок'];
		$dolya_benificiar = $params['ДоляКапитал'];

		if ($priznakOrder_benificiar == '0')
		{
			$priznakValue_benificiar = 569; // Признак
		} elseif ($priznakOrder_benificiar == '1')
		{
			$priznakValue_benificiar = 570; // Признак
		} elseif ($priznakOrder_benificiar == '2')
		{
			$priznakValue_benificiar = 571; // Признак
		} elseif ($priznakOrder_benificiar == '3')
		{
			$priznakValue_benificiar = 2; // Признак
		} elseif ($priznakOrder_benificiar == '4')
		{
			$priznakValue_benificiar = 572; // Признак
		} elseif ($priznakOrder_benificiar == '5')
		{
			$priznakValue_benificiar = 573; // Признак
		}

		$arrBenificiar = [
			'NAME' => "ФИО бенефициара",
			'PROPERTY_939' => $priznakValue_benificiar,
			'PROPERTY_941' => $dolya_benificiar,
			'PROPERTY_942' => $contactId_benificiar,
			'PROPERTY_943' => $id
		];

		$__arrBenificiar = self::get_benificiarByIdUl($id);

		//foreach ()

		$paramsForPostQuery = array(
			'IBLOCK_TYPE_ID' => 'lists',
			'IBLOCK_ID' => 167,
			'ELEMENT_CODE' => 'benificiar_' . $contactId_benificiar, //символьный код элемента
			'FIELDS' => $arrBenificiar
		);

		//$params[]
		//$priznak,$dolya,$contactId)
		return true;
	}
}