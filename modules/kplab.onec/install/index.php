<?php

class kplab_onec extends CModule
{
	var $MODULE_ID = 'kplab.onec';
	var $MODULE_NAME = '1C Интеграция';
	var $MODULE_DESCRIPTION = "Модуль для сайта crm.seller-capital.ru. Интеграция с 1C";
	var $MODULE_VERSION = "1.0";
	var $MODULE_VERSION_DATE = "2024-02-26 13:30:00";
	var $PARTNER_NAME = 'KPLab';
	var $PARTNER_URI = 'https://kplab-bitrix.ru/';

	public function DoInstall()
	{
		\Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

		\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
			'main',
			'onPageStart',
			$this->MODULE_ID,
			'\KPLab\OneC\EventHandler',
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
			'\KPLab\OneC\EventHandler',
			'disableBitrixAuth'
		);
	}
}