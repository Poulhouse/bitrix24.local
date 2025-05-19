<?php namespace KPLab\CRM;


class DeleteFromB24{
	public $entityTypeId;
	public function __construct($case, $id = null)
	{
		\Bitrix\Main\Loader::includeModule('crm');	
		\Bitrix\Main\Loader::includeModule('bizproc');
		switch ($case){
			case "LEAD" : $this -> entityTypeId = \CCrmOwnerType::Lead;
			break;
			case "DEAL" : $this -> entityTypeId = \CCrmOwnerType::Deal;
			break;
			case "DYNAMIC" :;
			break;
			case "FORM" : $this -> entityTypeId = \CCrmOwnerType::Webform;
			break;
			case "IBLOCK" :;
			break;
		}
	}

	// Передовать ID стадии
	public function ByStatus($statusId){
		// Фильтр стадии
		$filter = array(
			'STATUS_ID' => $statusId, // ID стадии
		);
		return self::GoDeleteCrm($filter);
	}

	// Передавать ID воронки
	public function ByCategory($categotyId){
		$filter = array(
			'CATEGORY_ID' => $categotyId, // ID воронки
		);
		return self::GoDeleteCrm($filter);
	}

	// Передавать ID Смарт-процесса
	public function Bizproc($bizprocId) {
		$this -> entityTypeId = $bizprocId;
		$filter = array(
			// 'entityTypeId' => $bizprocId, // ID смарт процесса
		);
		return self::GoDeleteCrm($filter);
	}

	// Передавать ID Формы
	public function Form($formId) {
		$filter = array(
			'ID' => $formId, // ID Формы
		);
		return self::GoDeleteCrm($filter);
	}

	// Конечная функция, где уже
	public function GoDeleteCrm($filter){
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($this -> entityTypeId);
		$items = $factory -> getItems(['select' => ['ID'],'filter'=> $filter]);

		foreach($items as $item){
			$operation = $factory -> getDeleteOperation($item);
			$operation -> disableAllChecks();
			$saveResult = $operation->launch();

			if (!$saveResult->isSuccess()){
				print_r("Элемент ".$item->getId()." не был удалён");
				return false;
			} 
		}
		return true;
	}

	//Удаление всех элементов
	public static function DeleteAllElements($IBLOCK_ID) {
        //Удаление элементов
        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ["IBLOCK_ID" => $IBLOCK_ID],
            false,
            false,
            ['ID']
        );
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            \CIBlockElement::Delete($arFields['ID']);
		}
    }

	//Удаление всех разделов
	public static function DeleteAllSections($IBLOCK_ID) {
        //Удаление разделов
		$res = \CIBlockSection::GetList(
			['ID' => 'ASC'],
			["IBLOCK_ID" => $IBLOCK_ID],
			false,
			['ID'],
			false
		);
		$i = 0;
		while ($arSection = $res->Fetch()) {
			if($i>=200){return false;}else $i++;
			\CIBlockSection::Delete($arSection['ID']);
		}
    }
}


//$del = new KPLab\CRM\DeleteFromB24("LEAD");
//$del -> ByStatus("NEW");
//$del -> ByStatus("JUNK");
//$del -> ByStatus("3");
//$del = new KPLab\CRM\DeleteFromB24("DEAL");
//$del -> ByCategory(0);
//$del -> ByCategory(18);
//$del -> ByCategory(14);
//$del -> ByCategory(15);
//$del -> ByCategory(21);
//$del -> ByCategory(20);
//$del -> ByCategory(30);
//$del -> ByCategory(24);
//$del -> ByCategory(38);
//$del -> ByCategory(32);
//$del -> ByCategory(39);
//$del -> ByCategory(44);
//$del -> ByCategory(45);
//$del -> ByCategory(46);
//$del -> ByCategory(53);
//$del = new KPLab\CRM\DeleteFromB24("DYNAMIC");
//$del -> Bizproc(1076);
//$del -> Bizproc(1072);
//$del -> Bizproc(1064);
//$del -> Bizproc(1038);
//$del -> Bizproc(159);
//$del -> Bizproc(139);
//$del -> Bizproc(161);
//$del -> Bizproc(137);
//$del -> Bizproc(190);
//$del -> Bizproc(143);
//$del -> Bizproc(172);
//$del -> Bizproc(171);
//$del -> Bizproc(150);
//$del -> Bizproc(169);