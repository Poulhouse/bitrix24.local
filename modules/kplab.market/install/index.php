<?php
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;

class kplab_market extends CModule
{
    public $MODULE_ID = 'kplab.market';
    public $MODULE_NAME = 'KPLab: Marketplace Core';
    public $MODULE_DESCRIPTION = 'Управление приложениями, установками и токенами';
    public $PARTNER_NAME = 'KPLab';
    public $PARTNER_URI  = 'https://kplab-bitrix.ru';

    public function __construct()
    {
        include __DIR__.'/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        \Bitrix\Main\Loader::includeModule('highloadblock');
        \Bitrix\Main\Loader::includeModule($this->MODULE_ID);

        $this->InstallFiles();

        RegisterModuleDependences(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'CKPLabMarket',
            'OnBuildGlobalMenu'
        );

        // --- создаём оба HL-блока ---
        $appsId = $this->installHLApplications();
        $installsId = $this->installHLInstallations();

        Option::set($this->MODULE_ID, 'HL_APPS_ID', $appsId);
        Option::set($this->MODULE_ID, 'HL_INSTALLS_ID', $installsId);

        // Агент на обновление токенов
        CAgent::AddAgent(
            '\KPLab\Market\Agent\RefreshTokens::run();',
            $this->MODULE_ID,
            'N',
            3600,   // раз в час
            '',     // дата начала (пусто — сразу)
            'Y'     // активен
        );

        $logDir = Application::getDocumentRoot().'/upload/kplab_market/logs/';
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0775, true);
        }
    }

    public function DoUninstall()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);

        UnRegisterModuleDependences(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'CKPLabMarket',
            'OnBuildGlobalMenu'
        );
        ModuleManager::unRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
    }

    // --- HL-блок для приложений ---
    private function installHLApplications(): int
    {
        $exists = HighloadBlockTable::getList(['filter' => ['=NAME' => 'KPLabApplications']])->fetch();
        if ($exists) return (int)$exists['ID'];

        $res = HighloadBlockTable::add(['NAME' => 'KPLabApplications', 'TABLE_NAME' => 'kplab_applications']);
        if (!$res->isSuccess()) {
            throw new \RuntimeException(implode('; ', $res->getErrorMessages()));
        }

        $hlId = (int)$res->getId();
        $this->addUf($hlId, 'UF_CODE', 'Код приложения', 'string');
        $this->addUf($hlId, 'UF_NAME', 'Название', 'string');
        $this->addUf($hlId, 'UF_CLIENT_ID', 'Client ID', 'string');
        $this->addUf($hlId, 'UF_CLIENT_SECRET', 'Client Secret', 'string');
        $this->addUf($hlId, 'UF_SCOPE', 'Scope', 'string');
        $this->addUf($hlId, 'UF_DESCRIPTION', 'Описание', 'string');
        $this->addUf($hlId, 'UF_STATUS', 'Статус', 'string');
        return $hlId;
    }

    // --- HL-блок для установок ---
    private function installHLInstallations(): int
    {
        $exists = HighloadBlockTable::getList(['filter' => ['=NAME' => 'KPLabAppInstallations']])->fetch();
        if ($exists) return (int)$exists['ID'];

        $res = HighloadBlockTable::add(['NAME' => 'KPLabAppInstallations', 'TABLE_NAME' => 'kplab_app_installations']);
        if (!$res->isSuccess()) {
            throw new \RuntimeException(implode('; ', $res->getErrorMessages()));
        }

        $hlId = (int)$res->getId();
        $this->addUf($hlId, 'UF_MEMBER_ID', 'Member ID', 'string');
        $this->addUf($hlId, 'UF_DOMAIN', 'Домен', 'string');
        $this->addUf($hlId, 'UF_APP_CODE', 'Код приложения', 'string');
        $this->addUf($hlId, 'UF_ACCESS_TOKEN', 'Access Token', 'string');
        $this->addUf($hlId, 'UF_REFRESH_TOKEN', 'Refresh Token', 'string');
        $this->addUf($hlId, 'UF_EXPIRES_AT', 'Истекает', 'datetime');
        $this->addUf($hlId, 'UF_STATUS', 'Статус', 'string');
        $this->addUf($hlId, 'UF_INSTALLED_AT', 'Дата установки', 'datetime');
        $this->addUf($hlId, 'UF_UNINSTALLED_AT', 'Дата удаления', 'datetime');
        $this->addUf($hlId, 'UF_LOG', 'Лог', 'string');
        return $hlId;
    }

    private function addUf(int $hlId, string $fieldName, string $label, string $type = 'string'): void
    {
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlId);
        $uf = new \CUserTypeEntity();
        $uf->Add([
            'ENTITY_ID' => 'HLBLOCK_'.$hlId,
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => $type,
            'EDIT_FORM_LABEL' => ['ru' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label],
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
        ]);
    }

    public function InstallFiles()
    {
        CopyDirFiles(__DIR__.'/admin', Application::getDocumentRoot().'/bitrix/admin', true, true);
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(__DIR__.'/admin', Application::getDocumentRoot().'/bitrix/admin');
    }
}
