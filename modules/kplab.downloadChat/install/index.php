<?php

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

class kplab_downloadchat extends CModule
{
	const MODULE_ID = 'kplab.downloadChat';
	var $MODULE_ID = 'kplab.downloadChat';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;
	var $strError = '';

	function __construct()
	{
		$this -> MODULE_VERSION = "2.0";
		$this -> MODULE_VERSION_DATE = "2023-12-28 12:00:00";
		$this -> MODULE_NAME = 'История переписки 2.0';
		$this -> MODULE_DESCRIPTION = "Модуль для crm.seller-capital.ru. История переписки";

		$this -> PARTNER_NAME = 'KPLab';
		$this -> PARTNER_URI = 'https://kplab-bitrix.ru/';
	}

	function InstallDB($arParams = [])
	{
		return true;
	}

	function UnInstallDB($arParams = [])
	{
		return true;
	}

	function InstallEvents()
	{
		return true;
	}

	function UnInstallEvents()
	{
		return true;
	}

	function InstallFiles($arParams = [])
	{
		return true;
	}

	function UnInstallFiles()
	{
		return true;
	}

	function DoInstall()
	{
		global $APPLICATION;
		$this -> InstallFiles();
		$this -> InstallDB();
		RegisterModule(self::MODULE_ID);
	}

	function DoUninstall()
	{
		global $APPLICATION;
		UnRegisterModule(self::MODULE_ID);
		$this -> UnInstallDB();
		$this -> UnInstallFiles();
	}
}

