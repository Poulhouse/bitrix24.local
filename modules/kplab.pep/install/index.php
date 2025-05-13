<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

class kplab_pep extends CModule
{
    var $MODULE_ID = 'kplab.pep';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;

    function __construct()
    {
        $arModuleVersion = [];

        include(dirname(__FILE__)."/version.php");

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = GetMessage("MODULE_PEP_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("MODULE_PEP_DESC");
        $this->PARTNER_NAME = GetMessage("PARTNER_PEP_NAME");
        $this->PARTNER_URI = GetMessage("PARTNER_PEP_URI");
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installFiles();
        $this->installDB();
        EventManager::getInstance()->registerEventHandler(
            "main",
            "OnPageStart",
            $this->MODULE_ID,
            "\\Kplab\\Pep\\EventHandlers",
            "onPageStartHandler"
        );
        EventManager::getInstance()->registerEventHandler(
            "main",
            "OnBuildGlobalMenu",
            $this->MODULE_ID,
            "\\Kplab\\Pep\\Admin\\Menu",
            "OnBuildGlobalMenu"
        );

        global $APPLICATION;
        $APPLICATION->IncludeAdminFile(
            "Установка модуля ПЭП",
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/kplab.pep/install/step.php"
        );

    }
    public function DoUninstall(): void
    {
        global $APPLICATION;

        if ($_REQUEST["step"] < 2) {
            $APPLICATION->IncludeAdminFile(
                "Удаление модуля ПЭП",
                __DIR__ . "/unstep1.php"
            );
        } else {
            $this->uninstallFiles();
            if ($_REQUEST["delete_db"] === "Y") {
                $this->uninstallDB();
            }

            if ($_REQUEST["delete_options"] === "Y") {
                \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
            }

            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                "main",
                "OnPageStart",
                $this->MODULE_ID,
                "\\Kplab\\Pep\\EventHandlers",
                "onPageStartHandler"
            );
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                "main",
                "OnBuildGlobalMenu",
                $this->MODULE_ID,
                "\\Kplab\\Pep\\Admin\\Menu",
                "OnBuildGlobalMenu"
            );

            \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(
                "Модуль ПЭП удалён",
                __DIR__ . "/unstep2.php"
            );
        }
    }

    public function installDB(): bool
    {
        $connection = Application::getConnection();
        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS kplab_pep_sgn_log (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                COMPANY_ID INT NOT NULL,
                DOCUMENT_ID INT NOT NULL,
                DOCUMENT_HASH CHAR(64) NOT NULL,
                FULL_NAME VARCHAR(255),
                PHONE VARCHAR(20),
                EMAIL VARCHAR(255),
                SIGNED_AT DATETIME,
                SIGNATURE CHAR(64),
                IP_ADDRESS VARCHAR(45),
                CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS kplab_pep_sms_codes (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                PHONE VARCHAR(20) NOT NULL,
                DOCUMENT_HASH CHAR(64) NOT NULL,
                SMS_CODE VARCHAR(6) NOT NULL,
                IS_USED TINYINT(1) DEFAULT 0,
                EXPIRES_AT DATETIME NOT NULL,
                CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS kplab_pep_log (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                DOCUMENT_HASH CHAR(64) NOT NULL,
                EVENT_TYPE VARCHAR(50) NOT NULL,
                PHONE VARCHAR(20),
                IP_ADDRESS VARCHAR(45),
                MESSAGE TEXT,
                CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        return true;
    }
    public function uninstallDB(): bool
    {
        $conn = Application::getConnection();
        $conn->queryExecute("DROP TABLE IF EXISTS kplab_pep_stats");
        $conn->queryExecute("DROP TABLE IF EXISTS kplab_pep_sgn_log");
        $conn->queryExecute("DROP TABLE IF EXISTS kplab_pep_sms_codes");
        $conn->queryExecute("DROP TABLE IF EXISTS kplab_pep_log");
        return true;
    }
    public function installFiles()
    {
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true, // rewrite
            true  // recursive
        );
    }

    public function uninstallFiles()
    {
        $adminDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        $handle = opendir(__DIR__ . '/admin');
        while (false !== ($file = readdir($handle))) {
            if ($file === '.' || $file === '..') continue;
            $target = $adminDir . '/' . $file;
            if (file_exists($target)) {
                unlink($target);
            }
        }
        closedir($handle);
    }

}
