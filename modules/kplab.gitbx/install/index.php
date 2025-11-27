<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class kplab_gitbx extends CModule
{
    public function __construct()
    {
        $this->MODULE_ID = 'kplab.gitbx';
        $this->MODULE_NAME = "GitBx DevOps (B24)";
        $this->MODULE_DESCRIPTION = "Агент CI/CD и миграций для коробочного Битрикс24";
        $this->PARTNER_NAME = "KPLab";
        $this->PARTNER_URI = "https://kplab-bitrix.ru";

        $version = [];

        include __DIR__ . "/version.php";

        $this->MODULE_VERSION = $version["VERSION"];
        $this->MODULE_VERSION_DATE = $version["VERSION_DATE"];
    }

    // Установка модуля
    public function DoInstall()
    {
        global $APPLICATION;

        if (!IsModuleInstalled($this->MODULE_ID)) {
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
            ModuleManager::registerModule($this->MODULE_ID);
        }

        $APPLICATION->IncludeAdminFile(
            "Установка модуля GitBx",
            __DIR__ . "/step.php"
        );
    }

    // Удаление модуля
    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            "Удаление модуля GitBx",
            __DIR__ . "/uninstall.php"
        );
    }

    /* =======================
       БАЗА ДАННЫХ
       ======================= */

    public function InstallDB()
    {
        // Пока БД не используем — оставляем заглушку.
        return true;
    }

    public function UnInstallDB()
    {
        // На MVP БД не трогаем.
        return true;
    }

    /* =======================
       РЕГИСТРАЦИЯ СОБЫТИЙ (меню)
       ======================= */

    public function InstallEvents()
    {
        RegisterModuleDependences(
            "main",
            "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "KPLab\\GitBx\\AdminMenu",
            "buildMenu"
        );

        return true;
    }

    public function UnInstallEvents()
    {
        UnRegisterModuleDependences(
            "main",
            "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "KPLab\\GitBx\\AdminMenu",
            "buildMenu"
        );

        return true;
    }

    /* =======================
       ФАЙЛЫ
       ======================= */

    public function InstallFiles()
    {
        $path = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin";

        if (is_dir(__DIR__ . "/admin")) {
            CopyDirFiles(
                __DIR__ . "/admin",
                $path,
                true,
                true
            );
        }

        // CSS / JS / images
        CopyDirFiles(__DIR__ . "/../assets", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/kplab.gitbx", true, true);

        return true;
    }

    public function UnInstallFiles()
    {
        // Удаляем admin-файлы
        $adminPath = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/";

        $files = [
            "kplab.gitbx_snapshots.php",
            "kplab.gitbx_diff.php",
            "kplab.gitbx_migrations.php",
            "kplab.gitbx_actualize_test.php",
        ];

        foreach ($files as $file) {
            $full = $adminPath . $file;
            if (file_exists($full)) {
                unlink($full);
            }
        }

        // Удаляем assets
        $assetsDir = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/kplab.gitbx";
        if (is_dir($assetsDir)) {
            DeleteDirFilesEx("/bitrix/kplab.gitbx");
        }

        return true;
    }
}
