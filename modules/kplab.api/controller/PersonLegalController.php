<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\API\V2\LogsAction;
use KPLab\API\V2\Model\Service\LegalService;
use KPLab\API\V2\Model\Service\PersonService;
use KPLab\API\V2\Model\DTO\PersonDTO;
use KPLab\Logs;
use KPLab\API\V2\Helpers\Locker;

define("LOG_API_SYNC_PERSON_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/PersonController.log");
define("LOG_API_SYNC_LEGAL_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/LegalController.log");

class PersonLegalController extends \Bitrix\Main\Engine\Controller
{
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
        ];
    }
    public function setPersonAction(): \Bitrix\Main\EventResult | \Bitrix\Main\Engine\Response\Json
    {
        // Инициализация контекста
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        //region Подготовка к обработке запроса
        $timeData = Logs\TimeData::start();
        $headers = $request->getHeaders()->toArray();
        $requestBody = $request->getInput();
        $requestMethod = $request->getRequestMethod();
        $url = $request->getRequestUri();
        $authorization = $context->getServer()->get('REMOTE_USER');
        $partnerName = LogsAction::getPartnerName($authorization);

        $headersValues = [];
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $queryParamsArray = $request->toArray();

        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "Добавление персоны: ";
        $objectData = $this->CURLObjectData;

        // Инициализация обработчика ответов
        $handler = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            $partnerName, // partner name
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestBody,
            $context
        );

        try {
            // Валидация и преобразование входных данных
            $requestData = $this->validateRequest($requestBody);

            // Создание DTO
            $personDTO = PersonDTO::createFromArray($requestData);
            $personArray = $personDTO->toArray();

            // Логирование полученных данных
            Logs\File::AddMessage($personArray, "personRequest", LOG_API_SYNC_PERSON_CONTROLLER);

            // Обработка через сервисный слой
            $personService = new PersonService($personDTO->inn);
            $personService->find();
            Logs\File::AddMessage($personService->personId, "personId", LOG_API_SYNC_PERSON_CONTROLLER);

            $personService->setPartnerName($partnerName);
            Logs\File::AddMessage($personService->partnerName, "partnerName", LOG_API_SYNC_PERSON_CONTROLLER);

            if (!$personService->personId) {
                $personService->add($personDTO);
            } else {
                $personService->update($personService->personId, $personDTO);
            }

            $responseData = [
                'crmId' => $personService->personId,
                'status' => $personService->personId ? 'updated' : 'created'
            ];
            Logs\File::AddMessage($responseData, "responseData", LOG_API_SYNC_PERSON_CONTROLLER);
            // Формирование успешного ответа
            return $handler->handleSuccess($responseData, $this->CURLObjectData);

        } catch (\InvalidArgumentException $e) {
            // Ошибки валидации
            return $handler->handleError(400, $e->getMessage(), "validation_error", $this->CURLObjectData);

        } catch (\Exception $e) {
            // Системные ошибки
            return $handler->handleError(500, 'Internal server error', "server_error", $this->CURLObjectData);
        }
        //endregion

    }
    public function setLegalAction(): \Bitrix\Main\EventResult | \Bitrix\Main\Engine\Response\Json
    {
        // Инициализация контекста
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        //region Подготовка к обработке запроса
        $timeData = Logs\TimeData::start();
        $headers = $request->getHeaders()->toArray();
        $requestBody = $request->getInput();
        $requestMethod = $request->getRequestMethod();
        $url = $request->getRequestUri();
        $authorization = $context->getServer()->get('REMOTE_USER');
        $partnerName = LogsAction::getPartnerName($authorization);

        $headersValues = [];
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $queryParamsArray = $request->toArray();

        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "Добавление юр.лица: ";
        $objectData = $this->CURLObjectData;

        // Инициализация обработчика ответов
        $handler = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "", // partner name
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestBody,
            $context
        );
        // Валидация и преобразование входных данных
        $requestData = $this->validateRequest($requestBody);

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

            $legalService->setPartnerName($partnerName);
            Logs\File::AddMessage($legalService->partnerName, "partnerName", LOG_API_SYNC_LEGAL_CONTROLLER);
            if (!$legalService->legalId) {
                $legalService->add($legalDTO);
            } else {
                $legalService->update($legalService->legalId, $legalDTO);
            }

            $responseData = [
                'crmId' => $legalService->legalId,
                'status' => $legalService->legalId ? 'updated' : 'created'
            ];
            Logs\File::AddMessage($responseData, "responseData", LOG_API_SYNC_LEGAL_CONTROLLER);
            // Формирование успешного ответа
            return $handler->handleSuccess($responseData, $this->CURLObjectData);

        }
        catch (\InvalidArgumentException $e) {
            // Ошибки валидации
            return $handler->handleError(400, $e->getMessage(), "validation_error", $this->CURLObjectData);

        }
        catch (\Exception $e) {
            // Системные ошибки
            return $handler->handleError(500, 'Internal server error', "server_error", $this->CURLObjectData);
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