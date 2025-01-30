<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/LeadsController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");


class Leads extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\V2\Controller\ActionFilterBots\Authentication(),
		];
	}
	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		return parent::prepareParams();
	}

	public function setLeadsBotAction(array $params = []) {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$server = $context -> getServer();
		$serverArray = $server->toArray();
		$serverName = $serverArray['SERVER_NAME'];

		\Bitrix\Main\Loader ::IncludeModule('crm');

		$requestArray = json_decode($request->getInput(),true);

		Logs\File::AddMessage($requestArray,"requestArray",LOG_API_SYNC_CONTROLLER);

		if($requestArray == NULL) {
			Context::getCurrent()->getResponse()->setStatus(400);
			$this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
			return new EventResult(EventResult::ERROR, null, null, $this);
		}

		return "success";
	}

	public static function addLeadToStage($controller, $stageId, $source, $requestArray) {
		$entityTypeId = \CCrmOwnerType::Lead;
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		if (!$factory)
		{
			Context::getCurrent()->getResponse()->setStatus(500);
			$controller -> addError(new Error('Ошибка на сервере', "invalid_server"));
			return new EventResult(EventResult::ERROR, null, null, $controller);
		}
		foreach ($requestArray['leads'] as $lead) {
			// пустой элемент, у которого заполнены значения полей по умолчанию. В том числе направление, стадия, кем создан и т.д.
			$newItem = $factory->createItem();
			// метод вернет данные об элементе в виде массива, идентичного по структуре "старому" API
			// $item->getCompatibleData();
			// можно данные записать массивом, аналогичным по структуре "старому" API

			if ($lead['status'] == false){ //не целевой
				$status = 9248;
			}
			else if ($lead['status'] == true){
				$status = 9247;
			}
			else if ($lead['status'] == null){//нет данных
				$status = 11024;
			}

			$fields = [
				'TITLE' => $lead['title'], //Название Лида
				'UF_CRM_1675341358002' => $lead['inn'], //ИНН
				'NAME' => $lead['name'], //Имя Лида
				'LAST_NAME' => $lead['lastName'], //Фамилия Лида
				'SECOND_NAME' => $lead['secondName'], //Отчество Лида (если есть)
				'UF_CRM_1595501723401' => $lead['requestedAmount'], //Запрашиваемая сумма
				'UF_CRM_1681799695541' => $lead['moneyTurnoverM'], //Ежемесячный оборот
				"UF_CRM_1682140471" => $lead['availableLimit'], //Лимит по прескорингу
				"SOURCE_ID" => $source['VALUE_XML_ID'],
				"UF_CRM_1681241444" => $status,
				'UTM_SOURCE' => 'seller',
				'UTM_MEDIUM' => 'referral',
				'UTM_CAMPAIGN' => $source['VALUE'],
				'UTM_CONTENT' => $requestArray['partnerInn']
			];
			$newItem->setFromCompatibleData($fields);
			$newItem->setStageId($stageId);
			// данные нигде не сохранены, они только хранятся внутри $item
			// чтобы сохранить изменения в БД без выполнения всех связанных действий, достаточно вызвать
			// $item->save();
			// НО ДЕЛАТЬ ТАК НЕ РЕКОМЕНДУЕТСЯ.
			// Все связанные действия, часто обеспечивающие работоспособность функционала crm будут пропущены (обновления счетчиков,
			// прав доступа, поисковых индексов, т.д.)
			// если необходимо переопределить контекст выполнения, это надо сделать в явном виде
			$context = new \Bitrix\Crm\Service\Context();
			$context->setUserId(1);

			// операция производится от пользователя $userId с выполнением всех проверок
			$operation = $factory->getAddOperation($newItem, $context);
			$result = $operation->launch();

			if($result->isSuccess()){
				$newId = $newItem->getId();
				if($newId){
					$MF = new \CCrmFieldMulti;
					$MF->Add([
						'ENTITY_ID' => 'LEAD',
						'ELEMENT_ID' => $newId,
						'VALUE' => $lead['email'],
						'TYPE_ID' => 'EMAIL', //PHONE - телефон
						'VALUE_TYPE' => 'WORK',
					]);
					$MF->Add([
						'ENTITY_ID' => 'LEAD',
						'ELEMENT_ID' => $newId,
						'VALUE' => $lead['phone'],
						'TYPE_ID' => 'PHONE', //EMAIL - телефон
						'VALUE_TYPE' => 'WORK',
					]);
				}
			}
		}
		return null;
	}
}