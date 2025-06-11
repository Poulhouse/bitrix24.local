<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\API\V2\LogsAction;
use KPLab\API\V2\Model\ORM\RoutesTable;
use KPLab\API\V2\Model\Service\DadataService;
use KPLab\API\V2\Model\Service\LegalService;
use KPLab\API\V2\Model\Service\PersonService;
use KPLab\API\V2\Model\DTO\PersonDTO;
use KPLab\API\V2\Model\Service\SellerService;
use KPLab\Logs;
use KPLab\API\V2\Helpers\Locker;

define("LOG_API_SYNC_PERSON_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/PersonController.log");
define("LOG_API_SYNC_LEGAL_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/LegalController.log");

class PersonLegalController extends \Bitrix\Main\Engine\Controller
{
    private const MODULE_ID = "kplab.api";
    public string $serverName;
    public array $queryParamsArray;
    public mixed $objectData;
    public mixed $requestData;
    public string $partnerName;
    public SellerService $sellerService;
    public HandlerResponse $handlerResponse;

    public function __construct() {
        parent::__construct();
        \Bitrix\Main\Loader::includeModule('crm');
        $this->sellerService = new SellerService();
        $this->handlerResponse = new HandlerResponse();
    }
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
        ];
    }
    public function setPersonAction(): \Bitrix\Main\EventResult | \Bitrix\Main\Engine\Response\Json
    {
        // 1. Читаем тело запроса
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);
        $this->objectData['ITEM_TITLE'] = "Добавление персоны из 1С: ";

        try {
            // Валидация и преобразование входных данных
            $requestData = $this->validateRequest($body);

            // Создание DTO
            $personDTO = PersonDTO::createFromArray($requestData);
            $personArray = $personDTO->toArray();

            // Обработка через сервисный слой
            $personService = new PersonService($personDTO->inn);
            $personService->find();
            $personService->setPartnerName($this->partnerName);

            if ($personService->personId == 0) {
                $personService->add($personDTO);
                $status = 'created';
                $this->objectData['ITEM_TITLE'] = "Добавление персоны из 1С: {$personDTO->inn}";
            } else {
                $personService->update($personService->personId, $personDTO);
                $status = 'updated';
                $this->objectData['ITEM_TITLE'] = "Обновление персоны из 1С: {$personDTO->inn}";
            }
            $this->objectData['INIT_OBJECT_URL'] = "https://{$this->serverName}/crm/type/4/details/{$personService->personId}/";

            $responseData = [
                'crmId' => $personService->personId,
                'status' => $status
            ];
            Logs\File::AddMessage($responseData, "responseData", LOG_API_SYNC_PERSON_CONTROLLER);
            // Формирование успешного ответа
            return $handler->handleSuccess($responseData, $this->objectData);

        } catch (\InvalidArgumentException $e) {
            // Ошибки валидации
            return $handler->handleError(400, $e->getMessage(), "validation_error", $this->objectData);

        } catch (\Exception $e) {
            // Системные ошибки
            return $handler->handleError(500, 'Internal server error', "server_error", $this->objectData);
        }
        //endregion

    }
    public function setLegalAction(): \Bitrix\Main\EventResult | \Bitrix\Main\Engine\Response\Json
    {
        // 1. Читаем тело запроса
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);
        $this->objectData['ITEM_TITLE'] = "Добавление юр.лица из 1С: ";

        // Валидация и преобразование входных данных
        $requestData = $this->validateRequest($body);

        // Создание DTO
        $legalDTO = LegalDTO::init($requestData);
        $legalArray = $legalDTO->toArray();

        // Логирование полученных данных
        Logs\File::AddMessage($legalArray, "legalRequest", LOG_API_SYNC_LEGAL_CONTROLLER);

        try {

            // Обработка через сервисный слой
            $legalService = new LegalService($legalDTO->inn);
            $legalService->find();
            Logs\File::AddMessage($legalService->legalId, "legalId", LOG_API_SYNC_LEGAL_CONTROLLER);

            $legalService->setPartnerName($this->partnerName);
            Logs\File::AddMessage($legalService->partnerName, "partnerName", LOG_API_SYNC_LEGAL_CONTROLLER);
            if (!$legalService->legalId) {
                $legalService->add($legalDTO);
                $status = 'created';
                $this->objectData['ITEM_TITLE'] = "Добавление юр.лица из 1С: {$legalDTO->inn}";
            } else {
                $legalService->update($legalService->legalId, $legalDTO);
                $status = 'updated';
                $this->objectData['ITEM_TITLE'] = "Обновление юр.лица из 1С: {$legalDTO->inn}";
            }
            $this->objectData['INIT_OBJECT_URL'] = "https://{$this->serverName}/crm/type/4/details/{$legalService->legalId}/";

            $responseData = [
                'crmId' => $legalService->legalId,
                'status' => $status
            ];
            Logs\File::AddMessage($responseData, "responseData", LOG_API_SYNC_LEGAL_CONTROLLER);
            // Формирование успешного ответа
            return $handler->handleSuccess($responseData, $this->objectData);

        }
        catch (\InvalidArgumentException $e) {
            // Ошибки валидации
            $errorCustomData = [
                'exceptionMessage' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            return $handler->handleError(400, "Invalid Argument", "validation_error", $this->objectData, $errorCustomData);

        }
        catch (\Exception|\Throwable $e) {
            // Системные ошибки
            $errorCustomData = [
                'exceptionMessage' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            return $handler->handleError(500, "Internal server error", "server_error", $this->objectData, $errorCustomData);
        }
        //endregion

    }

    public function testAction(): \Bitrix\Main\Engine\Response\Json
    {
        \Bitrix\Main\Application::getInstance()->addBackgroundJob(
            function() {
                file_put_contents(
                    $_SERVER['DOCUMENT_ROOT'].'/background_test.log',
                    date('Y-m-d H:i:s')." Task executed\n",
                    FILE_APPEND
                );
            },
            [],
            \Bitrix\Main\Application::JOB_PRIORITY_LOW
        );

        return new \Bitrix\Main\Engine\Response\Json([
            'status' => 'queued'
        ]);
    }

    /**
     * Валидация тела запроса
     */
    private function validateRequest(string $requestBody): array
    {
        if (empty($requestBody)) {
            throw new \InvalidArgumentException('Request body is empty');
        }

        $data = json_decode($requestBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON format: '.json_last_error_msg());
        }

        if (!isset($data['inn'])) {
            throw new \InvalidArgumentException('INN is required');
        }

        return $data;
    }

}