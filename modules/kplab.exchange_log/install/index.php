<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class kplab_exchange_log extends CModule
{
    var $MODULE_ID = 'kplab.exchange_log';
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

        $this->MODULE_NAME = "KPLab: Exchange Log";
        $this->MODULE_DESCRIPTION = "Управления отслеживанием изменений";
        $this->PARTNER_NAME = GetMessage("PARTNER_NAME");
        $this->PARTNER_URI = GetMessage("PARTNER_URI");
    }

    public function installDB()
    {
        // Регистрируем обработчик глобального меню
        RegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, 'CKPLabExchangeLog', 'OnBuildGlobalMenu');

        $connection = Application::getInstance()->getConnection();

        // Таблица для логирования изменений
        $sqlLog = "CREATE TABLE IF NOT EXISTS `kplab_exchange_log` (
            `ID` INT NOT NULL AUTO_INCREMENT,
            `ENTITY_TYPE_ID` INT NOT NULL,
            `ENTITY_ID` INT NOT NULL,
            `FIELD_NAME` VARCHAR(255) NOT NULL,
            `OLD_VALUE` TEXT,
            `NEW_VALUE` TEXT,
            `USER_ID` INT NOT NULL,
            `SERVICE_UPDATE_NAME` VARCHAR(255) NOT NULL,
            `CHANGE_DATE` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        // Таблица для хранения настроек модуля (опционально)
        $sqlConfig = "CREATE TABLE IF NOT EXISTS `kplab_exchange_config` (
            `ID` INT NOT NULL AUTO_INCREMENT,
            `SETTING_KEY` VARCHAR(255) NOT NULL,
            `SETTING_VALUE` TEXT NOT NULL,
            PRIMARY KEY (`ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        try {
            $connection->queryExecute($sqlLog);
            $connection->queryExecute($sqlConfig);
        } catch (SqlQueryException $e) {
            throw new Exception("Ошибка создания таблиц: " . $e->getMessage());
        }
        return true;
    }

    public function unInstallDB()
    {
        UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, 'CKPLabExchangeLog', 'OnBuildGlobalMenu');
        $connection = Application::getInstance()->getConnection();
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_exchange_log");
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_exchange_config");
        return true;
    }

    public function installFiles()
    {
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
        return true;
    }

    public function uninstallFiles()
    {
        $adminDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        if ($handle = opendir(__DIR__ . '/admin')) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..')
                    continue;
                $target = $adminDir . '/' . $file;
                if (file_exists($target)) {
                    unlink($target);
                }
            }
            closedir($handle);
        }
        return true;
    }

    public function DoInstall()
    {
        global $APPLICATION;
        $this->installDB();
        $this->installFiles();
        RegisterModule($this->MODULE_ID);
        // Можно здесь добавить дополнительные действия установки
        // Регистрация обработчика событий
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmLeadAdd',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Lead',
            'OnAfterCrmLeadAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmLeadUpdate',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Lead',
            'OnAfterCrmLeadUpdate'
        );

        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmDealAdd',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Deal',
            'OnAfterCrmDealAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmDealUpdate',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Deal',
            'OnAfterCrmDealUpdate'
        );

        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmCompanyAdd',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Company',
            'OnAfterCrmCompanyAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmCompanyUpdate',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Company',
            'OnAfterCrmCompanyUpdate'
        );

        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmDynamicItemAdd',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Item',
            'OnCrmDynamicItemAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'crm',
            'OnAfterCrmDynamicItemUpdate',
            $this->MODULE_ID,
            '\Kplab\Exchange_log\Handlers\Item',
            'OnCrmDynamicItemUpdate'
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        // Если step не равен 2, значит нужно показать форму подтверждения
        if ($_REQUEST['step'] != 2) {
            $APPLICATION->IncludeAdminFile(
                "Удаление модуля: " . $this->MODULE_NAME,
                dirname(__FILE__).'/unstep.php'
            );
        } else {
            if (isset($_REQUEST['save_data'])) {
                $save_data = $_REQUEST['save_data'];
                foreach($save_data as $save) {
                    if($save != 'Y') {
                        $this->unInstallDB();
                    }
                }
            }
            UnRegisterModule($this->MODULE_ID);

            // Удаление обработчика событий
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmLeadAdd',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Lead',
                'OnAfterCrmLeadAdd'
            );
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmLeadUpdate',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Lead',
                'OnAfterCrmLeadUpdate'
            );

            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmDealAdd',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Deal',
                'OnAfterCrmDealAdd'
            );
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmDealUpdate',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Deal',
                'OnAfterCrmDealUpdate'
            );

            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmCompanyAdd',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Company',
                'OnAfterCrmCompanyAdd'
            );
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmCompanyUpdate',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Company',
                'OnAfterCrmCompanyUpdate'
            );

            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmDynamicItemAdd',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Item',
                'OnCrmDynamicItemAdd'
            );
            \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
                'crm',
                'OnAfterCrmDynamicItemUpdate',
                $this->MODULE_ID,
                '\Kplab\Exchange_log\Handlers\Item',
                'OnCrmDynamicItemUpdate'
            );
            $this->unInstallFiles();
        }
    }
}
