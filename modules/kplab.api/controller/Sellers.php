<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Application;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\API\V2\Auth\ActionFilter\Authentication;
use KPLab\Logs;
use Bitrix\Main\Context;
use \KPLab\API\V2\Helpers\HandlerResponse;
use Bitrix\Main\Engine\Response\Json;
use KPLab\API\V2\Model\DTO\Sellers\SellerLegalEntityDTO;

use KPLab\API\V2\Factory\SellerDtoFactory;
use KPLab\API\V2\Model\DTO\Validator;
use KPLab\API\V2\Model\Service\SellerService;
use KPLab\API\V2\Model\Service\Sellers\SellerDirectorService;
use KPLab\API\V2\Model\Service\Sellers\SellerBeneficiarOwnersService;
use KPLab\API\V2\Model\ORM\RoutesTable;
use Bitrix\Main\Config\Option;
use KPLab\API\V2\LogsAction;

define("LOG_API_SYNC_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/SellersController.log");
define("LOG_API_SYNC_SELLER_ERRORS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/ErrorsSellersController.log");
define("LOG_API_SYNC_SET_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/SetSellersController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");

/**
 *  @OA\Tag(
 *       name="Sellers",
 *       description="API методы над селлерами"
 *   )
 */
class Sellers extends \Bitrix\Main\Engine\Controller
{

    /**
     *      @OA\Schema(schema="ErrorItem", type="object",
     *          @OA\Property(property="message", type="string", example="Ошибка `crmId` не известен", description="Текстовое описание ошибки"),
     *          @OA\Property(property="code", type="string", example="invalid_request", description="Код ошибки (строка или число)"),
     *          @OA\Property(property="customData", type="object", nullable=true, example=null, description="Дополнительные данные об ошибке (может быть null)")
     *      )
     */
    /**
     * @OA\Parameter(parameter="crmId",
     *     name="crmId",
     *     description="Внутренний идентификатор карточки ЛК в Битрикс24",
     *     @OA\Schema(
     *       type="string",
     *       example="1234"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */
    /**
     * @OA\Parameter(parameter="sellerInn",
     *     name="sellerInn",
     *     description="ИНН Селлера",
     *     @OA\Schema(
     *       type="string",
     *        example="159168"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */

    private const MODULE_ID = 'kplab.api';
    public string $rqId;
    public string $itemDatatitle;
    public string $partnerName;
    public int $entityTypeId;
    public int $entityId;
    public int $companyId;
    public int $contactId;
    public array $CURLObjectData;
    public string $serverName;
    public array $queryParamsArray;
    public SellerService $sellerService;
    public mixed $objectData;
    public mixed $requestData;
    public HandlerResponse $handlerResponse;

    public function __construct() {
        parent::__construct();
        \Bitrix\Main\Loader::includeModule('crm');
        $this->sellerService = new SellerService();
        $this->handlerResponse = new HandlerResponse();
    }

    /**
     * @return Authentication[]
     */
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new Authentication(),
        ];
    }

    //region POST

    public function setAction(array $params = []): EventResult|Json
    {

        // 1. Читаем тело запроса
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);

        $this->objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: ";

        // 3. Парсим тело
        $data = $this->requestData;
        Logs\File::AddMessage($data, 'Тело запроса', LOG_API_SYNC_SELLER_CONTROLLER);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        try {
            $crmId = $data['crmId'] ?? null;

            // region Построение DTO
            $sellerDto = SellerDtoFactory::create($data);
            $errors = Validator::collectErrors($sellerDto);

            if ($sellerDto instanceof SellerLegalEntityDTO) {
                $directorErrors = Validator::collectErrors($sellerDto->directorData);
                if (!empty($directorErrors)) {
                    $errors['directorData'] = $directorErrors;
                }

                $beneficiarsErrors = array_filter(
                    array_map(fn($b) => Validator::collectErrors($b), $sellerDto->beneficiars),
                    fn($e) => !empty($e)
                );
                if (!empty($beneficiarsErrors)) {
                    $errors['beneficiars'] = array_values($beneficiarsErrors);
                }
            }

            if (!empty(array_filter($errors))) {
                return $handler->handleError(422, json_encode($errors, JSON_UNESCAPED_UNICODE), 'validation_error', $this->objectData);
            }
            // endregion

            // region Синхронизация
            $this->objectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$data['sellerInn']}";
            $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
            $this->objectData['INIT_OBJECT_URL'] = "https://{$serverName}/crm/type/128/details/{$crmId}/";

            $this->sellerService->sync($sellerDto, $crmId);

            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID' => $crmId,
                    'ENTITY_TYPE' => 'DYNAMIC_128',
                    'COMMENT' => '[b]Данные успешно синхронизированы через Seller-Engine[/b]'
                ]
            ]);
            //endregion

            return $handler->handleSuccess(['message' => 'OK'], $this->objectData);

        } catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }

    /**
     * Добавление Селлера из Анонимной формы
     */
    /**
     * @OA\Parameter(
     *     parameter="guid",
     *     name="guid",
     *     description="GUID",
     *     @OA\Schema(
     *       type="string"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */
    /**
     * @OA\Post(
     *        path="/sellers/anonym/",
     *        tags={"Sellers"},
     *        summary="Добавление Селлера из Анонимной формы",
     *        operationId="setFromAnonymFormAction",
     *        @OA\Parameter(ref="#/components/parameters/guid"),
     *        @OA\Response(
     *            response=200,
     *            description="Успешный ответ"
     *        ),
     *        @OA\RequestBody(ref="#/components/requestBodies/setSellerAnonym")
     *  )
     * @param array $params
     * @return array|EventResult|mixed
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    /**
     *  @OA\RequestBody(request="setSellerAnonym",
     *     description="Новый селлер из Анонимной формы",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="type", type="string", enum={"IP"}, example="IP"),
     *           @OA\Property(property="inn", type="string", example="667100354160"),
     *           @OA\Property(property="ogrnip", type="string", example="323665800170896"),
     *           @OA\Property(property="okpo", type="string", example="2025313195"),
     *           @OA\Property(property="okved", type="string", example="62.01"),
     *           @OA\Property(property="companyRegDate", type="string", format="date-time", example="2023-08-22T00:00:00Z"),
     *           @OA\Property(property="fnsDepartment", type="string", nullable=true),
     *           @OA\Property(property="firstName", type="string", example="Андрей"),
     *           @OA\Property(property="lastName", type="string", example="Татарченков"),
     *           @OA\Property(property="secondName", type="string", example="Павлович"),
     *           @OA\Property(property="birthday", type="string", format="date-time", example="1989-03-25T00:00:00Z"),
     *           @OA\Property(property="birthPlace", type="string", example="ГОР.ВЛАДИВОСТОК ПРИМОРСКОГО КРАЯ"),
     *           @OA\Property(property="marketplaceLinks", type="array",
     *              @OA\Items(type="string", example="https://www.wildberries.ru/seller/https://www.wildberries.ru/brands/lizun-toys")
     *           ),
     *           @OA\Property(property="passport", type="object", ref="#/components/schemas/Passport"),
     *           @OA\Property(property="address", type="array",
     *               @OA\Items(ref="#/components/schemas/Address")
     *           ),
     *           @OA\Property(property="isAcceptPersonalInfo", type="boolean", example=true),
     *           @OA\Property(property="isAcceptPEPInfo", type="boolean", example=false)
     *        )
     *     )
     *  )
     */
    public function setFromAnonymFormAction(array $params = []): mixed
    {
        // 1) Получаем тело JSON и сразу инициализируем handler
        $body    = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);

        $this->objectData['ITEM_TITLE'] = "Результат добавления Селлера из Анонимной формы: ";

        // 2. Декодируем JSON
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        // 2) Проверяем наличие query-параметра 'guid'
        if (empty($this->queryParamsArray['guid'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `guid`.';
            return $handler->handleError(400, $errorMessage, 'invalid_request', $this->objectData);
        }

        if (empty($data['inn'])) {
            return $handler->handleError(400, 'Пустой inn', 'invalid_request', $this->objectData);
        }

        $guid = $this->queryParamsArray['guid'];
        $inn  = $data['inn'];
        $this->objectData['ITEM_TITLE'] = "Результат добавления Селлера из Анонимной формы: {$inn}";

        try {

            // 3. Поиск карточки
            if ($guid === '') {
                $sellerCardId = $this->sellerService->findCard($inn);
            } else {
                $card = $this->sellerService->findCardByDealGUID($inn, $guid);
                if (!is_array($card)) {
                    return $handler->handleError(404, 'Карточка клиента не найдена', 'not_found', $this->objectData);
                }
                $sellerCardId = $card['COMPANY_ID'];
                $dealCardId   = $card['ID'];
                $this->sellerService->changeStageDealCard($dealCardId,'C23:UC_K2J0ML');
            }

            // 4. Обновляем компанию и реквизиты
            $this->sellerService->updateCompanyCard($sellerCardId, $data);

            $rqId = $this->sellerService->findCompanyRQ($sellerCardId, $inn)
                ?? $this->sellerService->createRequisite($sellerCardId, $data);

            $this->sellerService->updateRequisite($rqId, $data, $sellerCardId);

            // 5. Обновление адреса
            \KPLab\Helpers\Address::processAddressRequisites($rqId, \CCrmOwnerType::Company, $sellerCardId, $data);

            // 6. Добавляем комментарий
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID'    => $sellerCardId,
                    'ENTITY_TYPE'  => 'COMPANY',
                    'COMMENT'      => '[b]Данные успешно синхронизированы через анонимную форму[/b]'
                ]
            ]);

            $this->objectData['INIT_OBJECT_URL'] = "https://{$this->serverName}/crm/company/details/{$sellerCardId}/";
            return $handler->handleSuccess("Данные успешно сохранились", $this->objectData);
        }
        catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }

    /**
     * Результат добавления Поручителя из ЛК
     */
    /**
     * @OA\Post(path="/sellers/setGuarantor/",
     *       tags={"Sellers"},
     *       summary="Добавление Поручителя",
     *       operationId="setGuarantor",
     *       @OA\Response(
     *           response=200,
     *           description="Успешный ответ"
     *       ),
     *       @OA\RequestBody(ref="#/components/requestBodies/setGuarantor")
     * )
     *
     */
    /**
     *  @OA\RequestBody(request="setGuarantor",
     *     description="Новый поручитель",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="sellerInn", type="string", example="667100354160"),
     *           @OA\Property(property="crmId", type="string", example="300"),
     *           @OA\Property(property="guarantorData", ref="#/components/schemas/PersonData")
     *        )
     *     )
     *  )
     */
    /**
     * @param array $params
     * @return EventResult| Json
     */
    public function setGuarantorAction(array $params = []): mixed
    {
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);
        $this->objectData['ITEM_TITLE'] = "SE: Результат добавления Поручителя из ЛК: ";

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        $crmId = $data['crmId'] ?? null;
        if (!$crmId) {
            return $handler->handleError(400, 'Пустой crmId', 'invalid_request', $this->objectData);
        }

        if (empty($data['guarantorData']['serviceEDO'])) {
            return $handler->handleError(400, 'Не заполнено поле serviceEDO в guarantorData', 'invalid_request', $this->objectData);
        }


        try {
            $guarantorDto = SellerDtoFactory::createPerson($data['guarantorData']);
            $errors = Validator::collectErrors($guarantorDto);

            if (!empty(array_filter($errors))) {
                return $handler->handleError(422, json_encode($errors, JSON_UNESCAPED_UNICODE), 'validation_error', $this->objectData);
            }

            $sellerInn = $data['sellerInn'] ?? '';
            $this->objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК по ИНН Селлера: {$sellerInn}";


            $sellerCardId = $this->sellerService->findCard($sellerInn, $crmId);
            $guarantorCardId = $this->sellerService->findCard($guarantorDto->inn);

            if (!$guarantorCardId) {
                $guarantorCardId = $this->sellerService->createCard($sellerCardId, (array)$guarantorDto, 'guarantor', $crmId);
            }

            $this->sellerService->updateCard($sellerCardId, (array)$guarantorDto, $guarantorCardId, 'guarantor', $crmId);

            $rqId = $this->sellerService->findCompanyRQ($guarantorCardId, $guarantorDto->inn)
                ?? $this->sellerService->createRequisite($guarantorCardId, (array)$guarantorDto);
            $this->sellerService->updateRequisite($rqId, (array)$guarantorDto, $guarantorCardId);


            \KPLab\Helpers\Address::processAddressRequisites($rqId, \CCrmOwnerType::Company, $guarantorCardId, (array)$guarantorDto);

            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID' => $crmId,
                    'ENTITY_TYPE' => 'DYNAMIC_128',
                    'COMMENT' => '[b]Поручитель успешно синхронизирован через Seller-Engine[/b]'
                ]
            ]);

            return $handler->handleSuccess(['message' => 'Изменения приняты'], $this->objectData);
        }
        catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }

    /**
     * Добавление счетов Селлера по ИНН
     *
     * @OA\Post(
     *         path="/sellers/setBankAccount/",
     *         tags={"Sellers"},
     *         summary="Добавление счетов Селлера по ИНН",
     *         operationId="setBankAccountAction",
     *         @OA\Response(
     *             response=200,
     *             description="Успешный ответ"
     *         ),
     *         @OA\RequestBody(ref="#/components/requestBodies/setBankAccount")
     *   )
     * @param array $params
     * @return EventResult|Json
     * @throws LoaderException
     */
    /**
     *  @OA\RequestBody(request="setBankAccount",
     *     description="Новый счет Селлера",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="bankAccounts", type="array", @OA\Items(ref="#/components/schemas/BankAccount"))
     *        )
     *     )
     *  )
     */
    public function setBankAccountAction(array $params = []): mixed
    {
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        if (empty($data['bankAccounts']) || !is_array($data['bankAccounts'])) {
            return $handler->handleError(400, 'Поле bankAccounts пустое или не является массивом', 'invalid_request', $this->objectData);
        }
        try
        {
            $bankAccounts = \KPLab\API\V2\Factory\BankAccountFactory::many($data['bankAccounts']);
            $errors = [];
            foreach ($bankAccounts as $index => $dto) {
                $dtoErrors = Validator::collectErrors($dto);
                if (!empty($dtoErrors)) {
                    $errors[$index] = $dtoErrors;
                }
            }

            if (!empty($errors)) {
                return $handler->handleError(422, json_encode($errors, JSON_UNESCAPED_UNICODE), 'validation_error', $this->objectData);
            }

            foreach ($bankAccounts as $dto) {
                $this->objectData['ITEM_TITLE'] = "Добавление счёта {$dto->title} по ИНН: {$dto->sellerInn}";

                $sellerCardId = $dto->crmId
                    ? $this->sellerService->findCard($dto->sellerInn, $dto->crmId)
                    : $this->sellerService->findCard($dto->sellerInn);

                if (!$sellerCardId || !is_int($sellerCardId)) {
                    return $handler->handleError(400, "Селлер с ИНН {$dto->sellerInn} не найден", 'not_found', $this->objectData);
                }

                $rqId = $this->sellerService->getFirstRequisiteId($sellerCardId);
                if (!$rqId) {
                    return $handler->handleError(400, "Не найден реквизит для компании с ID {$sellerCardId}", 'no_requisite', $this->objectData);
                }

                $this->sellerService->addBankAccount($rqId, $dto);
            }

            return $handler->handleSuccess(['message' => "Счета успешно добавлены"], $this->objectData);

        }
        catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }

    /**
     * Новый / Повторный транш для ИНН
     */
    /**
     * @OA\Post(
     *     path="/sellers/setLoan/",
     *     tags={"Sellers"},
     *     summary="Добавление займа Селлера",
     *     operationId="setLoanAction",
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 oneOf={
     *                     @OA\Schema(
     *                         type="object",
     *                         @OA\Property(property="status", type="string", example="success"),
     *                         @OA\Property(property="data", type="string", example="Новый транш успешно создан"),
     *                         @OA\Property(
     *                             property="errors",
     *                             type="array",
     *                             example="[]",
     *                             @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                             description="Список ошибок (может быть пустым)"
     *                         )
     *                     ),
     *                     @OA\Schema(
     *                         type="object",
     *                         @OA\Property(property="status", type="string", example="success"),
     *                         @OA\Property(property="data", type="string", example="Повторный транш успешно создан"),
     *                         @OA\Property(
     *                             property="errors",
     *                             type="array",
     *                             example="[]",
     *                             @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                             description="Список ошибок (может быть пустым)"
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\RequestBody(ref="#/components/requestBodies/setLoan")
     * )
     */
    /**
     *  @OA\RequestBody(request="setLoan",
     *     description="Новый транш Селлера",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="sellerInn", type="string"),
     *           @OA\Property(property="crmId", type="string"),
     *           @OA\Property(
     *              property="loanData",
     *              type="object",
     *              @OA\Property(property="amount", type="number", format="float", example=1000.5),
     *              @OA\Property(property="term", type="integer", enum={3,6,9,12}, example=6, description="3/6/9/12 месяцев"),
     *              @OA\Property(property="purposeLoan", type="string"),
     *              @OA\Property(property="typeContract", type="boolean", example=true, description="Нужна ли отсрочка"),
     *              @OA\Property(property="isfirsttranche", type="boolean", example=true, description="Это первый транш")
     *          )
     *        )
     *     )
     *  )
     */
    /**
     * @param array $params
     * @return EventResult|Json
     */
    public function setLoanAction(array $params = []): mixed
    {

        $body    = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        if (empty($data['sellerInn'])) {
            return $handler->handleError(400, 'Пустой sellerInn', 'invalid_request', $this->objectData);
        }

        try {

            $sellerInn = $data['sellerInn'];
            $crmId     = (int)($data['crmId'] ?? 0);
            $loanData  = $data['loanData'] ?? [];

            $this->objectData['ITEM_TITLE'] = "Новый/повторный транш для ИНН: {$sellerInn}";
            $result = $this->sellerService->processLoan($sellerInn, $crmId, $loanData);

            if ($result['status'] === 'first_created') {
                return $handler->handleSuccess("Новый транш для ИНН: {$sellerInn} успешно создан", $this->objectData);
            }

            if ($result['status'] === 'repeat_created') {
                return $handler->handleSuccess("Повторный транш для ИНН: {$sellerInn} успешно создан", $this->objectData);
            }
            return $handler->handleError(400, $result['message'] ?? 'Ошибка обработки транша', 'invalid_request', $this->objectData);
        }
        catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }

    /**
     * Получение данных от СМЭВ
     */
    /**
     * @OA\Parameter(
     *     parameter="crmEntityId",
     *     name="crmEntityId",
     *     description="ID компании/контакта",
     *     @OA\Schema(
     *       type="string",
     *       example="company_56711"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */
    /**
     * @OA\Parameter(
     *     parameter="rqId",
     *     name="rqId",
     *     description="ID реквизита",
     *     @OA\Schema(
     *       type="string",
     *        example="159168"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */
    /**
     * @OA\Post(
     *     path="/sellers/smavInfo/",
     *     tags={"Sellers"},
     *     summary="Отправка данных от СМЭВ",
     *     operationId="setSmavInfoAction",
     *     security={{"QueryKey": {}}},
     *     @OA\Parameter(ref="#/components/parameters/crmEntityId"),
     *     @OA\Parameter(ref="#/components/parameters/rqId"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(property="data", type="string", example="Данные успешно сохранились"),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\RequestBody(ref="#/components/requestBodies/setSmavInfo")
     * )
     */
    /**
     * @OA\RequestBody(request="setSmavInfo",
     *      description="Получение данных от СМЭВ",
     *      required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *          type="object",
     *          @OA\Property(property="Id", type="string", example="c9848790-f027-4ac7-8490-0f777de143aa"),
     *          @OA\Property(
     *              property="Response",
     *              type="object",
     *                      @OA\Property(property="fl", type="boolean", example=false),
     *                      @OA\Property(property="code", type="string", example="VALID"),
     *                      @OA\Property(
     *                          property="services",
     *                          type="array",
     *                          @OA\Items(
     *                                  type="object",
     *                                  @OA\Property(property="id", type="string", example="2e792dcc-1f1f-4431-be40-c5c7e8c5233b"),
     *                                  @OA\Property(property="status", type="string", example="success"),
     *                                  @OA\Property(property="service", type="string", example="mvd"),
     *                                  @OA\Property(
     *                                      property="result",
     *                                      type="object",
     *                                              @OA\Property(property="valid", type="boolean", example=true),
     *                                              @OA\Property(property="description", type="string", example="Данные корректны")
     *
     *                                  ),
     *                                  @OA\Property(property="message", type="string", example=null, nullable=true)
     *
     *                          )
     *                      ),
     *                      @OA\Property(
     *                          property="attributes",
     *                          type="object",
     *                                  @OA\Property(property="inn", type="string", example=null, nullable=true),
     *                                  @OA\Property(
     *                                      property="name",
     *                                      type="object",
     *                                              @OA\Property(property="fns", type="boolean", example=null, nullable=true),
     *                                              @OA\Property(property="mvd", type="boolean", example=true),
     *                                              @OA\Property(property="pfr", type="boolean", example=null, nullable=true)
     *
     *                                  ),
     *                                  @OA\Property(property="snils", type="string", example=null, nullable=true),
     *                                  @OA\Property(
     *                                      property="surname",
     *                                      type="object",
     *                                              @OA\Property(property="fns", type="boolean", example=null, nullable=true),
     *                                              @OA\Property(property="mvd", type="boolean", example=true),
     *                                              @OA\Property(property="pfr", type="boolean", example=null, nullable=true)
     *
     *                                  ),
     *                                  @OA\Property(
     *                                      property="patronymic",
     *                                      type="object",
     *                                              @OA\Property(property="fns", type="boolean", example=null, nullable=true),
     *                                              @OA\Property(property="mvd", type="boolean", example=true),
     *                                              @OA\Property(property="pfr", type="boolean", example=null, nullable=true)
     *
     *                                  ),
     *                                  @OA\Property(
     *                                      property="pass_number",
     *                                      type="object",
     *                                              @OA\Property(property="fns", type="boolean", example=null, nullable=true),
     *                                              @OA\Property(property="mvd", type="boolean", example=true),
     *                                              @OA\Property(property="pfr", type="boolean", example=null, nullable=true)
     *
     *                                  ),
     *                                  @OA\Property(
     *                                      property="pass_series",
     *                                      type="object",
     *                                              @OA\Property(property="fns", type="boolean", example=null, nullable=true),
     *                                              @OA\Property(property="mvd", type="boolean", example=true),
     *                                              @OA\Property(property="pfr", type="boolean", example=null, nullable=true)
     *
     *                                  )
     *
     *                      )
     *
     *          )
     *
     *         )
     *     )
     * )
     */
    /**
     * @param array $params
     * @return EventResult|Json
     * @throws LoaderException
     */
    public function setSmavInfoAction(array $params = []): mixed
    {

        \Bitrix\Main\Loader::IncludeModule('crm');

        // 1. Читаем тело запроса
        $body = Application::getInstance()->getContext()->getRequest()->getInput();
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, $body, __FUNCTION__);

        $this->objectData['ITEM_TITLE'] = "Получение данных от СМЭВ для задачи:";

        // 3. Декодируем JSON
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $handler->handleError(400, 'Некорректный JSON', 'invalid_json', $this->objectData);
        }

        // 4. Валидация query-параметров
        $query = $this->queryParamsArray;
        if (empty($query['crmEntityId']) || empty($query['rqId'])) {
            return $handler->handleError(400, "Отсутствует crmEntityId или rqId", "missing_params", $this->objectData);
        }

        // 5. Валидация структуры services
        if (!isset($data['Response']['services']) || !is_array($data['Response']['services'])) {
            return $handler->handleError(400, "Отсутствует массив 'services'", "invalid_structure", $this->objectData);
        }

        try {
            $crmEntityId = $query['crmEntityId'];
            $rqId = (int)$query['rqId'];
            $taskId = $data['Id'] ?? null;
            $services = $data['Response']['services'];

            $this->objectData['ITEM_TITLE'] = "Получение данных от СМЭВ для задачи: $taskId";
            $this->objectData['CRM_ENTITY_ID'] = $crmEntityId;

            // 6. Получаем фабрику и элемент
            if (str_starts_with($crmEntityId, 'company_')) {
                $entityTypeId = \CCrmOwnerType::Company;
                $entityId = (int)str_replace('company_', '', $crmEntityId);
            } elseif (str_starts_with($crmEntityId, 'contact_')) {
                $entityTypeId = \CCrmOwnerType::Contact;
                $entityId = (int)str_replace('contact_', '', $crmEntityId);
            } else {
                return $handler->handleError(400, "Некорректный формат crmEntityId", "invalid_crm_entity", $this->objectData);
            }

            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
            $item = $factory->getItem($entityId);

            if (!$item) {
                return $handler->handleError(404, "Сущность с ID $entityId не найдена", "entity_not_found", $this->objectData);
            }

            // 7. Логирование ID
            Logs\File::AddMessage($item->getId(), "->getId()", LOG_API_SYNC_SELLER_CONTROLLER);

            // 8. Сохраняем данные от СМЭВ
            $result = $this->sellerService->saveAllData($factory, $item, $services);

            // 9. Обновляем логирование
            $this->objectData['ITEM_TITLE'] = "Получение данных от СМЭВ: {$item->getId()}";

            if ($result['status'] === 'error') {
                $errorMessage = "Ошибка при сохранении: " . implode(', ', $result['messages']);
                \CRest::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $item->getId(),
                        "ENTITY_TYPE" => $entityTypeId === \CCrmOwnerType::Company ? "COMPANY" : "CONTACT",
                        "COMMENT" => "[b]{$errorMessage}[/b]"
                    ]
                ]);
                return $handler->handleError(400, $errorMessage, "save_error", $this->objectData);
            }

            // 10. Успешный ответ
            return $handler->handleSuccess("Данные успешно сохранены", $this->objectData);

        } catch (\Throwable $e) {
            return $handler->handleError(500, $e->getMessage(), 'exception', $this->objectData);
        }
    }
    //endregion POST

    //region GET

    /**
     * Получение даты окончания согласия по ИНН
     * @param array $params
     * @return array|EventResult|mixed
     * @throws LoaderException
     */
    /**
     * @OA\Get(
     *     path="/sellers/getCloseDateConsent/",
     *     tags={"Sellers"},
     *     summary="Получение даты окончания согласия селлера",
     *     operationId="getCloseDateConsentAction",
     *     @OA\Parameter(ref="#/components/parameters/crmId"),
     *     @OA\Parameter(ref="#/components/parameters/sellerInn"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="object",
     *                      @OA\Property(property="closeDateConsent", type="string", example="2025-07-13T00:00:00.0700+05:00")
     *                  ),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     )
     * )
     * @throws ArgumentException
     * @throws LoaderException
     */
    public function getCloseDateConsentAction(array $params = []): mixed
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, '', __FUNCTION__);
        $arRequest = $this->getRequestData();

        if (empty($arRequest['sellerInn'])) {
            return $handler->handleError(400, "Пустой `sellerInn`", "invalid_request", $this->objectData);
        }

        $sellerInn = $arRequest['sellerInn'];
        $crmId = (int) ($arRequest['crmId'] ?? 0);

        $this->objectData['ITEM_TITLE'] = "Получение даты окончания согласия по ИНН: {$sellerInn}";
        $data = $this->sellerService->getCloseDateConsent($sellerInn, $crmId);

        return $handler->handleSuccess($data, $this->objectData);
    }

    /**
     * Получение банковских реквизитов по ИНН
     * @param array $params
     * @return array|EventResult|mixed
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    /**
     * @OA\Get(
     *     path="/sellers/getBankAccount/",
     *     tags={"Sellers"},
     *     summary="Получение счетов Селлера",
     *     operationId="getBankAccountAction",
     *     @OA\Parameter(ref="#/components/parameters/crmId"),
     *     @OA\Parameter(ref="#/components/parameters/sellerInn"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="object",
     *                      @OA\Property(property="crmId", type="string"),
     *                      @OA\Property(property="sellerInn", type="string"),
     *                      @OA\Property(
     *                          property="bankAccounts",
     *                          type="array",
     *                          @OA\Items(
     *                              @OA\Property(property="title", type="string", enum={"расчетный", "номинальный"}, example="расчетный"),
     *                              @OA\Property(property="nameBank", type="string"),
     *                              @OA\Property(property="bankIdCode", type="string"),
     *                              @OA\Property(property="checkAccount", type="string"),
     *                              @OA\Property(property="adjAccount", type="string"),
     *                              @OA\Property(property="accCurrency", type="string")
     *                          )
     *                      )
     *                  ),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getBankAccountAction(array $params = []): EventResult|Json
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, '', __FUNCTION__);
        $arRequest = $this->getRequestData();


        $this->objectData['ITEM_TITLE'] = "Получение банковских реквизитов по ИНН:";

        // Валидация запроса
        if (empty($arRequest)) {
            return $handler->handleError(400, 'Тело запроса не удалось декодировать как JSON.', 'invalid_request', $this->objectData);
        }
        if (empty($arRequest['sellerInn'])) {
            return $handler->handleError(400, 'Пустой `sellerInn`', 'invalid_request', $this->objectData);
        }
        if (empty($arRequest['crmId'])) {
            return $handler->handleError(400, 'Пустой `crmId`', 'invalid_request', $this->objectData);
        }



        $sellerInn = $arRequest['sellerInn'];
        $crmId     = (int) $arRequest['crmId'];
        $this->objectData['ITEM_TITLE'] = "Получение банковских реквизитов по ИНН: {$sellerInn}";

        Logs\File::AddMessage($arRequest, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);


        // Поиск карточки
        $sellerCardId = $this->sellerService->findCard($sellerInn);
        if (!is_int($sellerCardId) && $crmId > 0) {
            $sellerCardId = $this->sellerService->findCard($sellerInn, $crmId);
        }
        if (!is_int($sellerCardId)) {
            return $handler->handleError(404, 'Не существует Селлера с таким ИНН или CRMID', 'invalid_request', $this->objectData);
        }

        // Получение реквизита
        $requisites = \CRest::call("crm.requisite.list", [
            "filter" => [
                "ENTITY_ID" => $sellerCardId,
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Company
            ],
            "select" => ["ID", "PRESET_ID", "ENTITY_ID", "ENTITY_TYPE_ID", "UF_*"]
        ])['result'];

        Logs\File::AddMessage($requisites, "requisite", LOG_API_SYNC_SELLER_CONTROLLER);

        if (empty($requisites[0]['ID'])) {
            return $handler->handleError(404, 'Реквизит не найден', 'not_found', $this->objectData);
        }

        $rqId = $requisites[0]['ID'];

        // Получение банковских реквизитов
        $bankAccounts = \CRest::call('crm.requisite.bankdetail.list', [
            "filter" => [
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                "ENTITY_ID" => $rqId
            ]
        ])['result'];

        Logs\File::AddMessage($bankAccounts, "bankAccounts", LOG_API_SYNC_SELLER_CONTROLLER);

        // Определяем тип списания
        $companyItem = \Bitrix\Crm\Service\Container::getInstance()
            ->getFactory(\CCrmOwnerType::Company)
            ->getItem($sellerCardId);

        $typeofBankingService = 'nominal';
        if ($companyItem) {
            $userFieldId = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_TYPE_OF_WRITE_OFF'
                ]
            ])->fetch()['ID'] ?? null;

            if ($userFieldId) {
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $userFieldId]);
                while ($enum = $res->Fetch()) {
                    if ($enum['ID'] == $companyItem->get('UF_CRM_TYPE_OF_WRITE_OFF')) {
                        $typeofBankingService = $enum['XML_ID'];
                    }
                }
            }
        }

        // Обработка счетов
        $lastCurrentAccount = null;
        $lastNominalAccount = null;
        foreach ($bankAccounts as $bank) {
            $account = [
                'title'        => $bank['NAME'],
                'nameBank'     => $bank['RQ_BANK_NAME'],
                'bankIdCode'   => $bank['RQ_BIK'],
                'checkAccount' => $bank['RQ_ACC_NUM'],
                'adjAccount'   => $bank['RQ_COR_ACC_NUM'],
                'accCurrency'  => $bank['RQ_ACC_CURRENCY'],
                'comments'     => $bank['COMMENTS'],
            ];
            if ($bank['NAME'] === 'Расчетный счет') {
                $lastCurrentAccount = $account;
            } elseif ($bank['NAME'] === 'Номинальный счет') {
                $lastNominalAccount = $account;
            }
        }

        $response = [
            'crmId'               => $crmId,
            'sellerInn'           => $sellerInn,
            'typeofBankingService'=> $typeofBankingService,
            'bankAccounts'        => array_values(array_filter([
                $lastCurrentAccount,
                $lastNominalAccount
            ])),
        ];

        return $handler->handleSuccess($response, $this->objectData);
    }

    /**
     * Получение лимитов по ИНН
     * @param array $params
     * @return array|EventResult|mixed
     * @throws LoaderException
     */
    /**
     * @OA\Get(
     *     path="/sellers/getLimits/",
     *     tags={"Sellers"},
     *     summary="Получение лимитов Селлера",
     *     operationId="getLimitsAction",
     *     @OA\Parameter(ref="#/components/parameters/crmId"),
     *     @OA\Parameter(ref="#/components/parameters/sellerInn"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="object",
     *                      @OA\Property(property="availableLimit", type="integer", example="8350000"),
     *                      @OA\Property(property="allLimit", type="integer", example="8350000"),
     *                      @OA\Property(property="minLoanAmount", type="integer", example="150000"),
     *                      @OA\Property(property="possibleLimitIncrease", type="integer", example="0"),
     *                      @OA\Property(property="dolg", type="integer", example="0"),
     *                      @OA\Property(property="tarif", type="integer", example="0"),
     *                      @OA\Property(property="interestRate", type="integer", example="0"),
     *                      @OA\Property(property="commissionRate", type="integer", example="0")
     *                  ),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getLimitsAction(array $params = []): mixed
    {
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $ctx        = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, '', __FUNCTION__);
        $request  = $this->requestData;
        $this->objectData['ITEM_TITLE'] = "Получение лимитов по ИНН:";

        // Валидация входных данных
        if (empty($request)) {
            return $handler->handleError(400, 'Тело запроса не удалось декодировать как JSON.', 'invalid_request', $this->objectData);
        }

        if (empty($request['sellerInn'])) {
            return $handler->handleError(400, 'Пустой `sellerInn`', 'invalid_request', $this->objectData);
        }

        $sellerInn = $request['sellerInn'];
        $crmId     = (int)($request['crmId'] ?? 0);
        $this->objectData['ITEM_TITLE'] = "Получение лимитов по ИНН: {$sellerInn}";

        // Поиск карточки
        $sellerCardId = $crmId > 0
            ? $this->sellerService->findCard($sellerInn, $crmId)
            : $this->sellerService->findCard($sellerInn);

        if (!is_int($sellerCardId)) {
            return $handler->handleError(400, "Не существует Селлера с таким ИНН или CRMID", 'invalid_request', $this->objectData);
        }

        // Получение смарт-карточек по компании
        $entityTypeId = 134;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        $items = $factory->getItems(['filter' => ['=COMPANY_ID' => $sellerCardId]]);

        $cardData = [];
        foreach ($items as $item) {
            $cardData = $item->getData();
            break;
        }

        // Обработка значений
        $availableLimit        = floatval(str_replace("|RUB", "", $cardData['UF_CRM_56_1684744875738'] ?? 0));
        $allLimit              = floatval(str_replace("|RUB", "", $cardData['UF_CRM_56_1684744846487'] ?? 0));
        $possibleLimitIncrease = floatval(str_replace("|RUB", "", $cardData['UF_CRM_LIMIT_TO_INCREASE'] ?? 0));
        $dolg                  = floatval(str_replace("|RUB", "", $cardData['UF_CRM_56_1684744827969'] ?? 0));
        $interestRate          = floatval($cardData['UF_CRM_INTEREST_RATE'] ?? 0);
        $commissionRate        = floatval($cardData['UF_CRM_COMMISSION_RATE'] ?? 0);
        $commentOnStatus       = $cardData['UF_CRM_COMMENT_ON_STATUS'] ?? '';

        // Получаем XML_ID тарифа
        $tarifId = $cardData['UF_CRM_TARIF_OF_SELLERS'] ?? null;
        $xmlId = null;
        if ($tarifId) {
            $res = \CUserFieldEnum::GetList([], ['ID' => $tarifId]);
            if ($enum = $res->Fetch()) {
                $xmlId = $enum['XML_ID'];
            }
        }

        if (!$xmlId) {
            return $handler->handleError(400, "Тариф не выбран", 'invalid_json', $this->objectData);
        }

        // Определяем тариф по XML_ID
        $tarifMap = [
            'Rate_1' => 3.5,
            'Rate_2' => 3.2,
            'Rate_3' => 3.0,
            'Rate_4' => 2.83
        ];
        $tarif = $tarifMap[$xmlId] ?? 0;

        $response = [
            'availableLimit'        => $availableLimit,
            'allLimit'              => $allLimit,
            'minLoanAmount'         => 150000.00,
            'possibleLimitIncrease' => $possibleLimitIncrease,
            'dolg'                  => $dolg,
            'tarif'                 => $tarif,
            'interestRate'          => $interestRate,
            'commissionRate'        => $commissionRate,
            'commentOnStatus'       => $commentOnStatus,
        ];

        return $handler->handleSuccess($response, $this->objectData);
    }

    /**
     * Получение списка займов по ИНН
     * @param array $params
     * @return array|EventResult|mixed
     * @throws LoaderException
     */
    /**
     * @OA\Get(
     *     path="/sellers/getLoans/",
     *     tags={"Sellers"},
     *     summary="Получение списка займов Селлера",
     *     operationId="getLoansAction",
     *     @OA\Parameter(ref="#/components/parameters/crmId"),
     *     @OA\Parameter(ref="#/components/parameters/sellerInn"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="array",
     *                      @OA\Items(
     *                               type="object",
     *                              @OA\Property(property="numberDog", type="string", example="МСК-29286-ЗС-3"),
     *                              @OA\Property(property="sumDog", type="string", example="800 000.00"),
     *                              @OA\Property(property="dateDog", type="string", format="date", example="22.12.2023"),
     *                              @OA\Property(property="nextPayDay", type="string", format="date", example="26.05.2024"),
     *                              @OA\Property(property="nextPaySum", type="string", example="39 000.00"),
     *                              @OA\Property(property="prosrochenoDays", type="integer", example=null),
     *                              @OA\Property(property="sumProsrocheno", type="string", example=null),
     *                              @OA\Property(property="ostatok", type="string", example="100 000.07")
     *                      ),
     *
     *                  ),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     )
     * )
     * @throws LoaderException
     */
    public function getLoansAction(array $params = []): EventResult|Json
    {
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $ctx = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, '', __FUNCTION__);
        $request  = $this->requestData;

        $this->objectData['ITEM_TITLE'] = "Получение списка займов по ИНН:";

        // Валидация
        if (empty($request)) {
            return $handler->handleError(400, 'Тело запроса не удалось декодировать как JSON.', 'invalid_request', $this->objectData);
        }

        if (empty($request['sellerInn'])) {
            return $handler->handleError(400, 'Пустой `sellerInn`', 'invalid_request', $this->objectData);
        }

        $sellerInn = $request['sellerInn'];
        $crmId     = $request['crmId'] ?? null;

        $this->objectData['ITEM_TITLE'] = "Получение списка займов по ИНН: {$sellerInn}";

        $sellerCardId = $crmId
            ? $this->sellerService->findCard($sellerInn, $crmId)
            : $this->sellerService->findCard($sellerInn);

        if (!is_int($sellerCardId)) {
            return $handler->handleError(404, 'Не существует Селлера с таким ИНН или CRMID', 'invalid_request', $this->objectData);
        }

        // Сбор займов
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(188);
        $params = [
            'filter' => [
                [
                    "LOGIC" => "OR",
                    ["STAGE_ID" => "DT188_28:NEW"],
                    ["STAGE_ID" => "DT188_28:CLIENT"],
                    ["STAGE_ID" => "DT188_28:UC_EQ8KZU"]
                ],
                '=COMPANY_ID' => $sellerCardId,
                '=UF_CRM_15_SS_FILIAL' => 17611
            ],
            'select' => [
                'UF_CRM_15_1679907467', 'UF_CRM_15_SS_NOMER',
                'UF_CRM_15_SS_SUMMADOGOVORA', 'UF_CRM_15_1679925201',
                'UF_CRM_15_SS_NEXTPAYDAY', 'UF_CRM_15_SS_NEXTPAYSUMMA',
                'UF_CRM_15_1679907525', 'UF_CRM_15_SS_NOMINAL',
                'UF_CRM_15_SS_FILIAL'
            ]
        ];

        $result = [];
        foreach ($factory->getItems($params) as $item) {
            $data = $item->getData();

            $sumProsrochenoValue = floatval(str_replace("|RUB", "", $data['UF_CRM_15_1679907467']));
            $sumProsrocheno = $sumProsrochenoValue === 0.00 ? null : number_format($sumProsrochenoValue, 2, ".", " ");

            $result[] = [
                'numberDog'       => $data["UF_CRM_15_SS_NOMER"],
                'sumDog'          => number_format((float) str_replace("|RUB", "", $data["UF_CRM_15_SS_SUMMADOGOVORA"]), 2, ".", " "),
                'dateDog'         => date('Y-m-d\TH:i:s.vp', strtotime($data["UF_CRM_15_1679925201"])),
                'nextPayDay'      => date('Y-m-d\TH:i:s.vp', strtotime($data["UF_CRM_15_SS_NEXTPAYDAY"])),
                'nextPaySum'      => number_format((float) str_replace("|RUB", "", $data["UF_CRM_15_SS_NEXTPAYSUMMA"]), 2, ".", " "),
                'prosrochenoDays' => $data['UF_CRM_15_1679907525'],
                'sumProsrocheno'  => $sumProsrocheno,
                'ostatok'         => number_format((float) str_replace("|RUB", "", $data["UF_CRM_15_SS_NOMINAL"]), 2, ".", " ")
            ];
        }

        return $handler->handleSuccess($result, $this->objectData);
    }

    /**
     * Получение списка ИНН по Когортам
     * @param array $params
     * @return array|EventResult|mixed
     * @throws LoaderException
     */
    /**
     * @OA\Parameter(parameter="cohort",
     *     name="cohort",
     *     description="Когорта селлера",
     *     @OA\Schema(
     *       type="string",
     *       enum={"approved","close","delay"},
     *       example="approved"
     *     ),
     *     in="query",
     *     required=true
     *   )
     */
    /**
     * @OA\Get(
     *     path="/sellers/getInnByCohorts/",
     *     tags={"Sellers"},
     *     summary="Получение списка ИНН Селлеров по когортам",
     *     operationId="getInnByCohortsAction",
     *     @OA\Parameter(ref="#/components/parameters/cohort"),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный ответ",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="status", type="string", example="success"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="array",
     *                      @OA\Items(
     *                          type="string", example="616270066366"
     *                      ),
     *
     *                  ),
     *                  @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     example="[]",
     *                     @OA\Items(ref="#/components/schemas/ErrorItem"),
     *                     description="Список ошибок (может быть пустым)"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getInnByCohortsAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $ctx = Application::getInstance()->getContext();
        // 2. Инициализируем Handler и логирование
        $handler = $this->handlerResponse->initHandler($this, $ctx, '', __FUNCTION__);
        $data    = $this->requestData;

        if (empty($data)) {
            return $handler->handleError(400, 'Тело запроса не удалось декодировать как JSON.', 'invalid_request', $this->objectData);
        }

        if (empty($data['cohort'])) {
            return $handler->handleError(400, 'Параметр `cohort` обязателен.', 'invalid_request', $this->objectData);
        }

        $cohort = $data['cohort'];
        $filter = [];

        switch ($cohort) {
            case 'approved':
                $filter = [
                    'UF_CRM_56_1684744827969' => 0.00,
                    '>UF_CRM_56_1684744846487' => 150000,
                    [
                        'LOGIC' => 'OR',
                        ["STAGE_ID" => "DT134_104:UC_EQ8KZU"],
                        ["STAGE_ID" => "DT134_104:UC_7VE0GD"],
                        ["STAGE_ID" => "DT134_104:NEW"]
                    ]
                ];
                break;

            case 'close':
                $filter = ['STAGE_ID' => 'DT134_104:UC_S4RR8K'];
                break;

            case 'delay':
                $filter = [
                    'LOGIC' => 'OR',
                    ["STAGE_ID" => "DT134_104:CLIENT"],
                    ["STAGE_ID" => "DT134_104:UC_IYGEPM"],
                    ["STAGE_ID" => "DT134_104:UC_V3JEQZ"]
                ];
                break;

            default:
                return $handler->handleError(400, "Недопустимое значение `cohort`: {$cohort}", 'invalid_request', $this->objectData);
        }

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(134);
        $items   = $factory->getItems(['filter' => $filter]);

        $innList = [];
        foreach ($items as $item) {
            $inn = $item->getData()['UF_CRM_56_1684841075'] ?? null;
            if ($inn) {
                $innList[] = $inn;
            }
        }

        return $handler->handleSuccess($innList, $this->objectData);
    }
    //endregion GET

    protected function getRequestData(): array
    {
        return $this->requestData ?? [];
    }
}
