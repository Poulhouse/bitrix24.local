<?
if(!(defined("CHK_EVENT") && CHK_EVENT===true)) define("BX_CRONTAB_SUPPORT", true);
//define("BX_CRONTAB_SUPPORT", true);
//define("BX_CRONTAB", true);
//define("SHORT_INSTALL_CHECK", true);
/* Ansible managed */
define("DBPersistent", false);
$DBType = "mysql";
$DBHost = "localhost";
$DBLogin = "bitrix0";
$DBPassword = "CSIb7eIq5ohJu&oSM+DC";
$DBName = "sitemanager";
$DBDebug = false;
$DBDebugToFile = false;

define("DELAY_DB_CONNECT", true);
define("CACHED_b_file", 3600);
define("CACHED_b_file_bucket_size", 10);
define("CACHED_b_lang", 3600);
define("CACHED_b_option", 3600);
define("CACHED_b_lang_domain", 3600);
define("CACHED_b_site_template", 3600);
define("CACHED_b_event", true);
define("CACHED_b_agent", 3660);
define("CACHED_menu", 3600);

define("BX_FILE_PERMISSIONS", 0664);
define("BX_DIR_PERMISSIONS", 0775);
@umask(~BX_DIR_PERMISSIONS);

define("MYSQL_TABLE_TYPE", "INNODB");
define("SHORT_INSTALL", true);
define("VM_INSTALL", true);

define("BX_UTF", true);

define("BX_DISABLE_INDEX_PAGE", true);
define("BX_COMPRESSION_DISABLED", true);
define("BX_USE_MYSQLI", true);

/*if($_REQUEST['COMMAND'] === 'startCallViaRest' || $_SERVER['REQUEST_URI'] === '/rest/voximplant.call.startViaRest.json') {
	define('BITRIXREST_URL', '192.168.52.2:8077/CallMeOut.php');
}*/

define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/local/default_log.txt");
define("BX_TEMPORARY_FILES_DIRECTORY", "/home/bitrix/.bx_temp/sitemanager/");
///https://dev.1c-bitrix.ru/api_d7/bitrix/landing/local.php
define("LANDING_DISABLE_CLOUD", true);
///
?>