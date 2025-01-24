<?php

// ID Инфоблока категорий товаров
const CATEGORIES_IBLOCK_ID = 36;
// ID Инфоблока списка очередей
const QUEUE_LIST_IBLOCK_ID = 37;
// ID Инфоблока списка городов
const CITIES_IBLOCK_ID = 42;
// ID свойства - дневной тип очереди
const QUEUE_TYPE_DAY_PROP_ID = 743;
const QUEUE_TYPE_NIGHT_PROP_ID = 744;
// Кол-во минут, на которое сделка блокируется для обработки текущем пользователем
const QUEUE_DEAL_LOCKED_MINUTES = 2;
const QUEUE_DEAL_SUBMIT_INTERVAL = 40; // in seconds
const QUEUE_DEAL_SUBMIT_INTERVAL_INCOMING = 0; // in seconds
const QUEUE_DEAL_SUBMIT_INTERVAL_TELEMARKETING = 0; // in seconds
//const QUEUE_DEAL_SUBMIT_INTERVAL = 0; // in seconds
const QUEUE_DEAL_STAGE_NEW = 9;
const QUEUE_DEAL_STAGE_DEFAULT = 11;
const QUEUE_DEAL_STAGE_APPOINTMENT = 8;
const QUEUE_DEAL_STAGE_DECLINE = 'LOSE';

const QUEUE_DEAL_STAGE_NEW_NAME = 'Получен лид';
const QUEUE_DEAL_STAGE_APPOINTMENT_NAME = 'Передано в офис';
const QUEUE_DEAL_STAGE_DECLINE_NAME = 'Отказ клиента';

const IBLOCK_QUEUE_ID = 37;
const IBLOCK_QUEUE_CONFIG_ID = 47;
const IBLOCK_SCRIPT_ID = 43;
const IBLOCK_SCRIPT_ITEM_ID = 44;
const IBLOCK_SCRIPT_PHRASE_ID = 45;
const IBLOCK_SCRIPT_OBJECTION_ID = 46;
const IBLOCK_CAMPAIGN_ID = 53;
const IBLOCK_ASTERISK_ID = 55;
const IBLOCK_INCOMING_ASTERISK_QUEUE_ID = 67;

const HLBLOCK_CAMPAIGIN_QUEUE_ID = 6;

const GROUP_HEAD_SPECIALIST_ID = 17;
const GROUP_TECH_SPECIALIST_ID = 18;
const GROUP_CALL_SPECIALIST_ID = 19;

const ASTERISK_HANGUP_URL = 'http://192.168.52.2:8077/CallMeEnd.php';
const ASTERISK_API_URL = 'https://192.168.52.2/element/';
const ASTERISK_TRANSFER_API_URL = 'http://192.168.52.2:8077/';

const BEELINE_SMS_ACTIVE = true;
const BEELINE_LOGIN = '1662711.2';
const BEELINE_PASSWORD = '!Q2w3e4r';
const BEELINE_HOSTNAME = 'beeline.amega-inform.ru';
const BEELINE_SENDER_NAME = 'SODEISTVIE';

// Код свойства сделки - Связанные категории товаров
const PROP_DEAL_CATEGORIES = 'UF_CRM_1573736333';
// Дефолтное время в секундах, после которого время планируемого звонка считается просроченным
const DEFAULT_PLANNED_CALL_EXPIRED_TIME = 60 * 4;

const PROP_QUEUE_TYPE_ID = 'NAPRAVLENIE_SOZDAVAEMYKH_SDELOK';
//соответствие межу ID значения свойства справочника направлений очереди и направлением сделки
const NAPRAVLENIE_SOZDAVAEMYKH_SDELOK_TO_TYPE_ID = 
[
	'352' => 1,//займы
	'353' => 2,//сбережения
	'354' => 3,//партнер
	'355' => 4//партнер_получен лид

];