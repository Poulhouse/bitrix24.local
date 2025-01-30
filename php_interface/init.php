<?php
use \Bitrix\Main\Application;
use   \Bitrix\Main\DB\Connection;
use   \Bitrix\Main\Loader;
use Bitrix\Main\EventManager;
use Bitrix\Main\Mail\Event;
use KPLab\Logs;
define("LANGUAGE_ID", "ru");
define("LOG_INIT", $_SERVER['DOCUMENT_ROOT']."/local/init.log");
\Bitrix\Main\Loader::includeModule("crm");
\Bitrix\Main\Loader::includeModule("mail");
\Bitrix\Main\Loader::includeModule("kplab.fias");
\Bitrix\Main\Loader::includeModule("location");
\Bitrix\Main\Loader::includeModule('iblock');

require_once ($_SERVER['DOCUMENT_ROOT'].'/local/crest/crest.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/autoload.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/services_sodeistvie/lib/functions.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/services_sodeistvie/lib/ss_sync.php');
require_once ($_SERVER['DOCUMENT_ROOT'].'/services_sodeistvie/lib/nopaper/api.php');

AddEventHandler("crm", "OnAfterCrmCompanyUpdate", Array("MyClass", "OnAfterCrm_UpdateHandler"));
AddEventHandler("crm", "OnAfterCrmLeadUpdate", Array("MyClass", "OnAfterCrmLeadUpdateHandler"));
AddEventHandler("main", "OnProlog", Array("MyClass", "MyOnPrologHandler"), 50);
AddEventHandler('rest', 'OnRestServiceBuildDescription', array('RestTest', 'OnRestServiceBuildDescription'));
AddEventHandler("im", "OnBeforeChatMessageAdd", Array("MyClass", "OnBeforeChatMessageAddHandler"));
//AddEventHandler("main", 'OnBeforeMailSend', array("MyClass", "OnBeforeMailSend"));
/*
EventManager::getInstance()->addEventHandler('main', 'OnBeforeMailSend', function(&$event) {
    $arParams = $event->getParameter(0);
    $subject = $arParams['SUBJECT'];
    Logs\File::AddMessage($arParams, "arParams", LOG_INIT);
    Logs\File::AddMessage($subject, "subject", LOG_INIT);

    if(str_contains($subject, "deal_id") === false) {
        $MessageId = $arParams['HEADER']['Message-Id'];
        Logs\File::AddMessage($MessageId, "MessageId", LOG_INIT);

        preg_match('/crm\.activity\.(\d+)-/', $MessageId, $matches);

        Logs\File::AddMessage($matches[1], "matches_1", LOG_INIT);

        if (isset($matches[1])) {
            Logs\File::AddMessage($matches[1], "ID дела", LOG_INIT);
            $listActivity = CCrmActivity::GetList([],['ID' => $matches[1]],false,false,[],[]);
            while ($activity = $listActivity->Fetch()) {
                if($activity['OWNER_TYPE_ID'] == 2) {
                    $dealId = $activity['OWNER_ID'];

                    $arParams['SUBJECT'] = $subject . " deal_id ".$dealId;
                    \CCrmActivity::Update($matches[1], array('SUBJECT'=>$arParams['SUBJECT']), false, false);
                    Logs\File::AddMessage($arParams, "arParams new", LOG_INIT);
                }

                Logs\File::AddMessage($activity, "дело", LOG_INIT);
            }

            $result = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, $arParams);

            return $result;
        }
    }
});

*/
//AddEventHandler("mail", 'onMailMessageModified', array("MyClass", "my_onMailMessageModified"));

function testAgent()
{
    Logs\File::AddMessage([],"Агент Включился",LOG_INIT);
    Logs\File::AddMessage([],"Агент Выключился",LOG_INIT);
    return "testAgent();";
}
function deleteItemsAgent($id) {
	$entityTypeId = $id;
	//$counters = 2;
	//AddMessage2Log("Агент Включился");

	$params = [
		'entityTypeId' => $entityTypeId,
		'select' => ['ID'],
		'order' => ['ID'=>'ASC']
	];

	$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

	$res = \CRest::call('crm.item.list', $params)['result']['items'];

	foreach($res as $el) {
		//echo "Элемент ".$el['id']."\n";

		$item = $factory->getItem($el['id']);

		if (isset($item))
		{
			// Step 1: get opetation
			$operation = $factory->getDeleteOperation($item);

			// Step 2: config operation (optional)
			$operation
				->disableCheckWorkflows()
				->disableCheckRequiredUserFields()
				->disableBizProc()
				->disableAutomation()
			;


			// Step 3: launch operation
			$operationResult = $operation->launch();

			if ( $operationResult->isSuccess() )
			{
				/**
				 * Operation success
				 */
				AddMessage2Log("Агент удалил {$el['id']}");
			}
			else
			{
				/**
				 * Operation failed with error
				 *
				 * @operationResult->getErrors();
				 * @operationResult->getErrorMessages();
				 */
				AddMessage2Log($operationResult->getErrorMessages());
			}

			//AddMessage2Log($result,"Результат удаления элемента: {$el['id']}");
			//
			//echo "Элемент ".$el['id']." найден и удален \n\n";
		}
	}
	//AddMessage2Log("Агент Выключился");
	return "deleteItemsAgent({$id});";
}

//title: Поиск и замена подстроки в файлах
function replaceInFiles($dir, $search, $replace, $formatFile) {
    // Открываем директорию
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    // Проходим по всем файлам в директории
    foreach ($files as $name => $file) {
        // Проверяем, что файл является .js
        if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === $formatFile) {
            // Читаем содержимое файла
            $content = file_get_contents($file->getRealPath());

            // Если строка найдена, заменяем её
            if (strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);

                // Перезаписываем файл с измененным содержимым
                file_put_contents($file->getRealPath(), $content);
                echo "Обновлен файл: " . $file->getRealPath() . "\n";
            }
        }
    }
}

//title: Поиск и замена подстроки в БД
function replaceTextInBitrixDB($search, $replace, $limit = 10, $offset = 0)
{
    global $DB;

    // Получаем список таблиц с лимитом и оффсетом
    $tableQuery = "
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME
        LIMIT $offset, $limit
    ";

    $res = $DB->Query($tableQuery);
    $tables = [];
    while ($row = $res->Fetch()) {
        $tables[] = $row['TABLE_NAME'];
    }

    if (!$tables) {
        echo "Нет таблиц для обработки в указанном диапазоне.\n";
        return;
    }

    // Получаем список колонок, содержащих текстовые данные
    $columnsQuery = "
        SELECT TABLE_NAME, COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'longtext')
        AND TABLE_NAME IN ('" . implode("','", $tables) . "')
    ";

    $res = $DB->Query($columnsQuery);
    $columns = [];
    while ($row = $res->Fetch()) {
        $columns[] = $row;
    }

    if (!$columns) {
        echo "Нет подходящих колонок в указанных таблицах.\n";
        return;
    }

    // Выполняем замену в колонках
    foreach ($columns as $column) {
        $updateQuery = "
            UPDATE {$column['TABLE_NAME']} 
            SET {$column['COLUMN_NAME']} = REPLACE({$column['COLUMN_NAME']}, '$search', '$replace')
            WHERE {$column['COLUMN_NAME']} LIKE '%$search%'
        ";

        $DB->Query($updateQuery, true);
        echo "Обновлено в {$column['TABLE_NAME']}.{$column['COLUMN_NAME']}\n";
    }

    echo "Готово! Обработано $limit таблиц, начиная с $offset.\n";
}