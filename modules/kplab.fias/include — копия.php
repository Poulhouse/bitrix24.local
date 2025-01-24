<?php
/*
 * Файл local/modules/kplab.fias/include.php
 */

//\Bitrix\Main\Loader::registerAutoloadClasses(
//	'kplab.fias',
//	array(
//		'KPLab\\Fias' => 'lib/Fias.php',
//	)
//);

use \Bitrix\Main,
	\Bitrix\Main\Loader,
	\Bitrix\Crm\Service,
	\Bitrix\Crm\Timeline\Entity\TimelineTable,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Crm\Timeline\TimelineType;


//var $module_id = 'kplab.fias';

//CJSCore ::RegisterExt('jquery_fias_min_full',
//	array(
//		'js' => '/bitrix/js/' . $module_id . '/jquery.fias.min.js',
//		'css' => '/bitrix/css/' . $module_id . '/jquery.fias.min.css'
//	)
//);
//CJSCore ::RegisterExt('jquery_1.11.1',
//	array(
//		'js' => '/bitrix/js/' . $module_id . '/jquery-1.11.1.min.js'
//	)
//);
//CJSCore ::RegisterExt('jquery_fias_min_css',
//	array(
//		'css' => '/bitrix/css/' . $module_id . '/jquery.fias.min.css'
//	)
//);
//CJSCore ::RegisterExt('fias_core',
//	array(
//		'js' => '/bitrix/js/' . $module_id . '/core.js',
//	)
//);
//CJSCore ::RegisterExt('fias',
//	array(
//		'js' => '/bitrix/js/' . $module_id . '/fias.js',
//	)
//);
//CJSCore ::RegisterExt('fiasZip',
//	array(
//		'js' => '/bitrix/js/' . $module_id . '/fias_zip.js',
//	)
//);
//CJSCore ::RegisterExt('fiasScript',
//	array(
//		'js' => '/local/modules/' . $module_id . '/scripts/script.js'
//	)
//);
//
CJSCore ::RegisterExt('onChangeScript',
	array(
		'js' => '/local/modules/' . $module_id . '/scripts/onChangeScript.js'
	)
);
// = Main\EventManager::getInstance();

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

	public static function appendJavaScriptAndCSS()
	{
		$module_id = 'kplab.fias';
		//CJSCore ::Init('jquery_1.11.1');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/jquery-1.11.1.min.js');
		Asset::getInstance()->addCss('/bitrix/css/' . $module_id . '/jquery.fias.min.css');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/core.js');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/fias.js');
		Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/fias_zip.js');
		//Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/onChangeScript.js', true);
		Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/script.js', true);

//		CJSCore ::Init('jquery_fias_min_css');
//		CJSCore ::Init('fias_core');
//		CJSCore ::Init('fias');
//		CJSCore ::Init('fiasZip');
//		CJSCore ::Init('fiasScript');
		//CJSCore ::Init('onChangeScript');
		return true;
	}
	public static function appendScriptCrmCompanyUpdate() {
//		var $module_id = 'kplab.fias';
		CJSCore ::Init('onChangeScript');
		return true;
	}
public static function appendScriptCrmUpdate() {
		var $module_id = 'kplab.fias';
		Asset::getInstance()->addJs('/local/modules/' . $module_id . '/scripts/onChangeScript.js', true);
		return true;
	}

}
?>
