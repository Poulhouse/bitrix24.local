<?php

class kplab_customagents extends CModule
{
    var $MODULE_ID = 'kplab.customagents';
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

        $this->MODULE_NAME = "KPLab: Custom Agents";
        $this->MODULE_DESCRIPTION = "Свои агенты";
        $this->PARTNER_NAME = "KPLab";
        $this->PARTNER_URI = "https://kplab-bitrix.ru";
    }

    public function installDB()
    {
        return true;
    }

    public function unInstallDB()
    {
        return true;
    }

    public function installFiles()
    {
        return true;
    }

    public function uninstallFiles()
    {
        return true;
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        UnRegisterModule($this->MODULE_ID);
    }
}
