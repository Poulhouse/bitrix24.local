<?php

use Bitrix\Main\EventManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class kplab_jira extends CModule
{
    public function __construct()
    {
        $this->MODULE_ID = 'kplab.jira';
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2025-02-17';
        $this->MODULE_NAME = 'Jira Sync';
        $this->MODULE_DESCRIPTION = 'Модуль для регистрации событий в Битрикс24';
        $this->PARTNER_NAME = 'KPLab';
        $this->PARTNER_URI = 'https://kplab-bitrix.ru/';
    }

    public function DoInstall()
    {
        $this->RegisterEvents();
        \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        $this->UnRegisterEvents();
        \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    private function RegisterEvents()
    {
        $eventManager = EventManager::getInstance();
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskCommentAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskCommentUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'tasks',
            'OnTaskCommentDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentDelete'
        );
    }

    private function UnRegisterEvents()
    {
        $eventManager = EventManager::getInstance();
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'socialnetwork',
            'onSonetGroupSubjectDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'onSonetGroupSubjectDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskDelete'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskCommentAdd',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentAdd'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskCommentUpdate',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentUpdate'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'tasks',
            'OnTaskCommentDelete',
            $this->MODULE_ID,
            '\Kplab\Jira\EventHandlers',
            'OnTaskCommentDelete'
        );
    }
}