<?php

class kplab_api extends CModule
{
    var $MODULE_ID = 'kplab.api';
    var $MODULE_NAME = 'API Интеграция с BitrixData';
    var $MODULE_DESCRIPTION = "Модуль для сайта crm.sodeistvie.su. API Интеграция с внешними системами";
    var $MODULE_VERSION = "1.6";
    var $MODULE_VERSION_DATE = "2024-10-06 22:00:00";
    var $PARTNER_NAME = 'KPLab';
    var $PARTNER_URI = 'https://kplab-bitrix.ru/';

    public function DoInstall()
    {
        // Регистрация модуля
        \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

        // Регистрация обработчика событий
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'onPageStart',
            $this->MODULE_ID,
            '\KPLab\API\EventHandler',
            'disableBitrixAuth'
        );

        // Копирование административных файлов в битриксовую директорию
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/kplab.api/admin", // Откуда копируем
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin", // Куда копируем
            true,
            true
        );
    }

    public function DoUninstall()
    {
        // Удаление модуля
        \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

        // Удаление обработчика событий
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'onPageStart',
            $this->MODULE_ID,
            '\KPLab\API\EventHandler',
            'disableBitrixAuth'
        );

        // При удалении модуля удаляем файлы из /bitrix/admin/
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/kplab.api/admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"
        );
    }
}
