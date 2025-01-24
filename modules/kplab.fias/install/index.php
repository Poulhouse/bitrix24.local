<?php
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\EventManager;
use Bitrix\Main\Config\Option;

IncludeModuleLangFile(__FILE__);

class kplab_fias extends CModule
{
	const MODULE_ID = 'kplab.fias';
	var $MODULE_ID = 'kplab.fias';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;
	var $strError = '';

	function __construct()
	{
		$arModuleVersion = [];
		include(dirname(__FILE__)."/version.php");
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = GetMessage("kplab.fias_MODULE_NAME");
		$this->MODULE_DESCRIPTION = GetMessage("kplab.fias_MODULE_DESC");

		$this->PARTNER_NAME = GetMessage("kplab.fias_PARTNER_NAME");
		$this->PARTNER_URI = GetMessage("kplab.fias_PARTNER_URI");
	}

	function InstallDB($arParams = [])
	{
		RegisterModuleDependences('main', 'OnBuildGlobalMenu', self::MODULE_ID, 'CKplabFias', 'OnBuildGlobalMenu');
		RegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabFias', 'appendJavaScriptAndCSS');
		RegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnBeforeCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		RegisterModuleDependences('crm', 'OnAfterCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		RegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');

		return true;
	}

	function UnInstallDB($arParams = [])
	{
		UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', self::MODULE_ID, 'CKplabFias', 'OnBuildGlobalMenu');
		UnRegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabFias', 'appendJavaScriptAndCSS');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		UnRegisterModuleDependences('crm', 'OnAfterCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		UnRegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');

		return true;
	}

	function InstallEvents()
	{
		//EventManager::getInstance()->registerEventHandlerCompatible('main','OnBeforeProlog',self::MODULE_ID,
		//'CKplabFias','appendJavaScriptAndCSS');
		RegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabFias', 'appendJavaScriptAndCSS');
		RegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnBeforeCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		RegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		RegisterModuleDependences('crm', 'OnAfterCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		RegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		return true;
	}

	function UnInstallEvents()
	{
		// удаляем наш обработчик события
		//EventManager::getInstance()->registerEventHandlerCompatible('main','OnBeforeProlog',self::MODULE_ID,
		//'CKplabFias','appendJavaScriptAndCSS');
		UnRegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabFias', 'appendJavaScriptAndCSS');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnBeforeCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'appendScriptCrmUpdate');
		UnRegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		UnRegisterModuleDependences('crm', 'OnAfterCrmCompanyUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		UnRegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', self::MODULE_ID, 'CKplabFias', 'OnAfterCrm_UpdateHandler');
		return true;
	}

	function InstallFiles($arParams = [])
	{
		if (is_dir($p = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/admin'))
		{
			if ($dir = opendir($p))
			{
				while (false !== $item = readdir($dir))
				{
					if ($item == '..' || $item == '.' || $item == 'menu.php')
						continue;
					file_put_contents($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.self::MODULE_ID.'_'.$item,
					'<'.'? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/'.self::MODULE_ID.'/admin/'.$item.'");?'.'>');
				}
				closedir($dir);
			}
		}
		if (is_dir($p = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/install/components'))
		{
			if ($dir = opendir($p))
			{
				while (false !== $item = readdir($dir))
				{
					if ($item == '..' || $item == '.')
						continue;
					CopyDirFiles($p.'/'.$item, $_SERVER['DOCUMENT_ROOT'].'/bitrix/components/'.$item, $ReWrite = True, $Recursive = True);
				}
				closedir($dir);
			}
		}

		// копируем js-файлы, необходимые для работы модуля
		CopyDirFiles(
			__DIR__.'/assets/js',
			Application::getDocumentRoot().'/bitrix/js/'.self::MODULE_ID.'/',
			true,
			true
		);
		// копируем css-файлы, необходимые для работы модуля
		CopyDirFiles(
			__DIR__.'/assets/css',
			Application::getDocumentRoot().'/bitrix/css/'.self::MODULE_ID.'/',
			true,
			true
		);
		CopyDirFiles(
			__DIR__.'/modules/location',
			Application::getDocumentRoot().'/local/modules/location/',
			true,
			true
		);

		return true;
	}

	function UnInstallFiles()
	{
		if (is_dir($p = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/admin'))
		{
			if ($dir = opendir($p))
			{
				while (false !== $item = readdir($dir))
				{
					if ($item == '..' || $item == '.')
						continue;
					unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.self::MODULE_ID.'_'.$item);
				}
				closedir($dir);
			}
		}
		if (is_dir($p = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/install/components'))
		{
			if ($dir = opendir($p))
			{
				while (false !== $item = readdir($dir))
				{
					if ($item == '..' || $item == '.' || !is_dir($p0 = $p.'/'.$item))
						continue;

					$dir0 = opendir($p0);
					while (false !== $item0 = readdir($dir0))
					{
						if ($item0 == '..' || $item0 == '.')
							continue;
						DeleteDirFilesEx('/bitrix/components/'.$item.'/'.$item0);
					}
					closedir($dir0);
				}
				closedir($dir);
			}
		}

		// удаляем js-файлы
		Directory::deleteDirectory(Application::getDocumentRoot().'/bitrix/js/'.self::MODULE_ID);
		// удаляем css-файлы
		Directory::deleteDirectory(Application::getDocumentRoot().'/bitrix/css/'.self::MODULE_ID);

		Directory::deleteDirectory(Application::getDocumentRoot().'/local/modules/location/');


		return true;
	}

	function DoInstall()
	{
		global $APPLICATION;
		$this->InstallFiles();
		$this->InstallDB();
		RegisterModule(self::MODULE_ID);
	}

	function DoUninstall()
	{
		global $APPLICATION;
		UnRegisterModule(self::MODULE_ID);
		$this->UnInstallDB();
		$this->UnInstallFiles();
	}
}
?>
