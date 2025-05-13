<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class kplab_api extends CModule
{
    var $MODULE_ID = 'kplab.api';
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

        $this->MODULE_NAME = GetMessage("MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("MODULE_DESC");
        $this->PARTNER_NAME = GetMessage("PARTNER_NAME");
        $this->PARTNER_URI = GetMessage("PARTNER_URI");
    }

    public function installDB()
    {
        RegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, 'CKPLabApi', 'OnBuildGlobalMenu');
        $connection = Application::getConnection();
        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS kplab_api_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255),
                partner_name VARCHAR(255) NOT NULL,
                method_name VARCHAR(255) NOT NULL,
                object_url VARCHAR(255),
                request_url VARCHAR(255) NOT NULL,
                controller_name VARCHAR(100) NOT NULL,
                request_method VARCHAR(10) NOT NULL,
                request_type VARCHAR(50) NOT NULL,
                request_status VARCHAR(50),
                request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                response TEXT,
                execution_time FLOAT,
                request_body LONGTEXT,
                request_headers TEXT,
                task_id VARCHAR(255),
                request_type_id INT
            )
        ");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS kplab_api_request_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_name VARCHAR(255) NOT NULL,
                description TEXT
            )
        ");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS kplab_api_routes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                route_path VARCHAR(255) NOT NULL,
                controller_name VARCHAR(255) NOT NULL,
                method_name VARCHAR(255) NOT NULL,
                http_method VARCHAR(10) NOT NULL,
                active CHAR(1) DEFAULT 'Y'                            
            )
        ");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS kplab_api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key VARCHAR(255) NOT NULL UNIQUE,
                user_id INT DEFAULT 1,
                service_name VARCHAR(255),
                status VARCHAR(20) DEFAULT 'active',
                manual_entry VARCHAR(1) DEFAULT 'N',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL,
                key_location VARCHAR(10) DEFAULT 'header',
                key_param_name VARCHAR(100) NOT NULL DEFAULT 'Authorization'
            )
        ");

        $connection->queryExecute("CREATE TABLE IF NOT EXISTS kplab_api_key_routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_key_id INT NOT NULL,
            route_id INT NOT NULL,
            FOREIGN KEY (api_key_id) REFERENCES kplab_api_keys(id) ON DELETE CASCADE,
            FOREIGN KEY (route_id) REFERENCES kplab_api_routes(id) ON DELETE CASCADE,
            UNIQUE (api_key_id, route_id)
        )");
        return true;
    }

    public function unInstallDB()
    {
        UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, 'CKPLabApi', 'OnBuildGlobalMenu');
        $connection = Application::getConnection();
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_api_logs");
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_api_request_types");
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_api_key_routes");
        $connection->queryExecute("DROP TABLE IF EXISTS kplab_api_keys");
        //$connection->queryExecute("DROP TABLE IF EXISTS kplab_api_routes");
        return true;
    }

    public function installFiles()
    {
        CopyDirFiles(
            dirname(__FILE__).'/admin',
            Application::getDocumentRoot() . '/bitrix/admin',
            true,
            true
        );
    }

    // Метод IsInstalled для совместимости
    /*public function IsInstalled()
    {
        return ModuleManager::isModuleInstalled(self::MODULE_ID);
    }*/
    
    public function unInstallFiles()
    {
        DeleteDirFiles(
            dirname(__FILE__).'/admin',
            Application::getDocumentRoot() . '/bitrix/admin'
        );

    }

    public function DoInstall()
    {
        global $APPLICATION;
        $this->installDB();
        $this->installFiles();
        RegisterModule($this->MODULE_ID);

        // Регистрация обработчика событий
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'onPageStart',
            $this->MODULE_ID,
            '\KPLab\API\V2\EventHandler',
            'disableBitrixAuth'
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        // Если step не равен 2, значит нужно показать форму
        if ($_REQUEST['step'] != 2) {
            // Показ формы в отдельной странице Bitrix
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
                'main',
                'onPageStart',
                $this->MODULE_ID,
                '\KPLab\API\V2\EventHandler',
                'disableBitrixAuth'
            );
            $this->unInstallFiles();
        }
    }
}
