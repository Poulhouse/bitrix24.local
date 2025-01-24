<?php
/*
 * Файл local/modules/kplab.fias/include.php
 */

use \Bitrix\Main,
	\Bitrix\Main\Loader,
	\Bitrix\Crm\Service,
	\Bitrix\Crm\Timeline\Entity\TimelineTable,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Main\Application,
	\Bitrix\Main\Entity,
	\Bitrix\Crm\Timeline\TimelineType;

Class CKplabFias
{

	static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
	{
		if($GLOBALS['APPLICATION']->GetGroupRight("main") < "R")
			return;

		$MODULE_ID = basename(dirname(__FILE__));
		$aMenu = array(
			//"parent_menu" => "global_menu_services",
			"parent_menu" => "global_menu_settings",
			"section" => $MODULE_ID,
			"sort" => 50,
			"text" => $MODULE_ID,
			"title" => '',
//			"url" => "partner_modules.php?module=".$MODULE_ID,
			"icon" => "",
			"page_icon" => "",
			"items_id" => $MODULE_ID."_items",
			"more_url" => array(),
			"items" => array()
		);

		if (file_exists($path = dirname(__FILE__).'/admin'))
		{
			if ($dir = opendir($path))
			{
				$arFiles = array();

				while(false !== $item = readdir($dir))
				{
					if (in_array($item,array('.','..','menu.php')))
						continue;

					if (!file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$MODULE_ID.'_'.$item))
						file_put_contents($file,'<'.'? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/'.$MODULE_ID.'/admin/'.$item.'");?'.'>');

					$arFiles[] = $item;
				}

				sort($arFiles);

				foreach($arFiles as $item)
					$aMenu['items'][] = array(
						'text' => $item,
						'url' => $MODULE_ID.'_'.$item,
						'module_id' => $MODULE_ID,
						"title" => "",
					);
			}
		}
		$aModuleMenu[] = $aMenu;
	}

	public static function writeToLog($data, $title = '') {
		$log = "\n------------------------\n";
		$log .= date("Y.m.d G:i:s") . "\n";
		$log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
		$log .= print_r($data, 1);
		$log .= "\n------------------------\n";
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/hook.log', $log, FILE_APPEND);
		return true;
	}

	public static function appendJavaScriptAndCSS()
	{
		$module_id = 'kplab.fias';
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/jquery-1.11.1.min.js');
		Asset::getInstance()->addCss('/bitrix/css/' . $module_id . '/jquery.fias.min.css');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/core.js');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/fias.js');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/fias_zip.js');
		Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/onChangeScript.js', true);
		Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/script.js', true);
		//CJSCore::Init(array("fiasModule"));
		return true;
	}

	public static function OnAfterCrm_UpdateHandler($fields) {
		//self::writeToLog($fields, '$fields from include 111');
		Application::getConnection();
		return true;
	}
	
	public static function appendScriptCrmUpdate($fields) {
		$module_id = 'kplab.fias';
		Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/onChangeScript.js', true);
		return true;
	}

}
?>
