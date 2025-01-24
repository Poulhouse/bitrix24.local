<?php namespace KPLab;

use \KPLab\Logs;

class Pep {
	public static function getFilesArrayByItem($entityTypeId, $itemId, $ufNameFrom, $ufNameTo) {
		$item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->getItem($itemId);
		$arFile = array();
		foreach ($item->getData()["{$ufNameFrom}"] as $fileUrl) {
			// Парсинг URL для получения компонентов
			$parsedUrl = parse_url($fileUrl);

			// Получение абсолютного пути
			$absolutePath = $parsedUrl['path'];
			$fileArray = \CFile::MakeFileArray($absolutePath);

			array_push($arFile, $fileArray);
		}


		echo '<pre>';
		print_r($arFile);
		echo '</pre>' .PHP_EOL;

		$item->set($ufNameTo, $arFile);
		$result = $item->save();
		if (count($result->getErrorMessages())>0) {
			echo '<pre>'; print_r($result->getErrorMessages()); echo '</pre>';
		}
	}

}