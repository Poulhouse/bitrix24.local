<?php

class kplab_jwt extends CModule
{
	var $MODULE_ID = 'kplab.jwt';
	var $MODULE_NAME = 'JWT Authentication';
	var $MODULE_DESCRIPTION = "Модуль для сайта sodeistvie.su. Своя авторизация";
	var $MODULE_VERSION = "1.0";
	var $MODULE_VERSION_DATE = "2023-10-21 00:30:00";
	var $PARTNER_NAME = 'KPLab';
	var $PARTNER_URI = 'https://kplab-bitrix.ru/';

	public function DoInstall()
	{
		\Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

		\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
			'main',
			'onPageStart',
			$this->MODULE_ID,
			'\KPLab\JWT\EventHandler',
			'disableBitrixAuth'
		);
	}

	public function DoUninstall()
	{
		\Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

		\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
			'main',
			'onPageStart',
			$this->MODULE_ID,
			'\KPLab\JWT\EventHandler',
			'disableBitrixAuth'
		);
	}
}