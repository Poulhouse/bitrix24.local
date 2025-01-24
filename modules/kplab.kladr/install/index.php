<?php
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

class kplab_kladr extends CModule
{
    const MODULE_ID = 'kplab.kladr';
    public function __construct()
    {
        $arModuleVersion = [];
        include(dirname(__FILE__)."/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_ID = 'kplab.kladr';
        $this->MODULE_NAME = 'Модуль интеграции с ФИАС';
        $this->MODULE_DESCRIPTION = 'Модуль для работы с адресами ФИАС в CRM';
        $this->PARTNER_NAME = 'KPLab';
        $this->PARTNER_URI = 'https://example.com';
    }

    // Установка модуля
    function DoInstall()
    {
        global $APPLICATION;
        // копируем js-файлы, необходимые для работы модуля
        CopyDirFiles(
            __DIR__.'/lib/js',
            Application::getDocumentRoot().'/bitrix/js/'.self::MODULE_ID.'/',
            true,
            true
        );
        // копируем css-файлы, необходимые для работы модуля
        CopyDirFiles(
            __DIR__.'/lib/css',
            Application::getDocumentRoot().'/bitrix/css/'.self::MODULE_ID.'/',
            true,
            true
        );
        // копируем файлы модифицированного модуля location, необходимый для работы нашего модуля
        CopyDirFiles(
            __DIR__.'/lib/modules/location',
            Application::getDocumentRoot().'/local/modules/location/',
            true,
            true
        );
        $this->installEvents();
        RegisterModule(self::MODULE_ID);
    }

    // Удаление модуля
    function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule(self::MODULE_ID);
        $this->UnInstallEvents();
        // удаляем js-файлы
        Directory::deleteDirectory(Application::getDocumentRoot().'/bitrix/js/'.self::MODULE_ID);
        // удаляем css-файлы
        Directory::deleteDirectory(Application::getDocumentRoot().'/bitrix/css/'.self::MODULE_ID);

        Directory::deleteDirectory(Application::getDocumentRoot().'/local/modules/location/');
    }

    // Регистрация событий для добавления вкладки в CRM
    function InstallEvents()
    {
        RegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabKladr', 'appendJavaScriptAndCSS');
        RegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabKladr', 'appendScriptCrmUpdate');
    }

    function UnInstallEvents()
    {
        UnRegisterModuleDependences('main', 'OnBeforeProlog', self::MODULE_ID, 'CKplabKladr', 'appendJavaScriptAndCSS');
        UnRegisterModuleDependences('crm', 'OnBeforeCrmCompanyUpdate', self::MODULE_ID, 'CKplabKladr', 'appendScriptCrmUpdate');
    }
}
