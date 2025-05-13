<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use KPLab\API\V2\DTO\PersonDTO;
use KPLab\API\V2\DTO\LegalDTO;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\API\V2\Service\PersonService;
use KPLab\API\V2\Service\LegalService;
use KPLab\Logs;

define("LOG_API_SYNC_LEGAL_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/LegalController.log");

class Legal extends \Bitrix\Main\Engine\Controller
{
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new ActionFilter\Authentication(),
        ];
    }
    public function setAction()
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
            array_column($headers, 'values.0', 'name'),
            $requestBody,
            $context
        );

        try {
            // Валидация и преобразование входных данных
            $requestData = $this->validateRequest($requestBody);

            // Создание DTO
            $legalDTO = LegalDTO::init($requestData);

            $legalArray = $legalDTO->toArray();

            // Логирование полученных данных
            Logs\File::AddMessage($legalArray, "legalRequest", LOG_API_SYNC_LEGAL_CONTROLLER);

            // Обработка через сервисный слой
            $legalService = new LegalService($legalDTO->inn);
            $legalId = $legalService->find();
            Logs\File::AddMessage($legalId, "legalId", LOG_API_SYNC_PERSON_CONTROLLER);

            if (!$legalId) {
                $result = $legalService->add($legalDTO);
            } else {
                $result = $legalService->update($legalId, $legalDTO);
            }

            $responseData = [
                'crmId' => $result->getId(),
                'status' => $legalId ? 'updated' : 'created'
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