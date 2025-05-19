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
use KPLab\API\V2\Model\Service\SellerService;
use KPLab\Logs;
use Bitrix\Main\Context;
use \KPLab\API\V2\Helpers\HandlerResponse;

define("LOG_API_SYNC_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/SellersController.log");
define("LOG_API_SYNC_SET_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/SetSellersController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");

/**
 *  @OA\Tag(
 *       name="Sellers",
 *       description="API методы над селлерами"
 *   )
 */
class Sellers extends \Bitrix\Main\Engine\Controller
{
    public string $rqId;
    public string $itemDatatitle;
    public int $entityTypeId;
    public int $entityId;
    public int $companyId;
    public int $contactId;
    public array $CURLObjectData;

    /**
     *  @OA\Schema(schema="SellerData",
     *      required={"type"},
     *     @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="FL"),
     *     anyOf={
     *         @OA\Schema(ref="#/components/schemas/DataIP"),
     *         @OA\Schema(ref="#/components/schemas/DataFL"),
     *         @OA\Schema(ref="#/components/schemas/DataUL")
     *     },
     *     discriminator=@OA\Discriminator(propertyName="type", mapping={
     *          "FL": "#/components/schemas/DataFL",
     *         "IP": "#/components/schemas/DataIP",
     *         "UL": "#/components/schemas/DataUL"
     *     })
     *  )
     */
    /**
     *  @OA\Schema(schema="GuarantorData",
     *           required={"type"},
     *          @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="FL"),
     *          oneOf={
     *              @OA\Schema(ref="#/components/schemas/DataFL"),
     *              @OA\Schema(ref="#/components/schemas/DataIP"),
     *              @OA\Schema(ref="#/components/schemas/DataUL")
     *          },
     *          discriminator=@OA\Discriminator(
     *              propertyName="type",
     *              mapping={
     *                  "IP": "#/components/schemas/DataIP",
     *                  "FL": "#/components/schemas/DataFL",
     *                  "UL": "#/components/schemas/DataUL"
     *              }
     *          )
     *  )
     */
    /**
     *      @OA\Schema(schema="DataFL", type="object",
     *          @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="FL"),
     *          @OA\Property(property="synchId", type="string", example="1c2c8733-e5d6-41b4-929c-bc196879f785"),
     *          @OA\Property(property="inn", type="string", example="667100354160"),
     *          @OA\Property(property="firstName", type="string", example="Андрей "),
     *          @OA\Property(property="lastName", type="string", example="Татарченков "),
     *          @OA\Property(property="secondName", type="string", example="Павлович"),
     *          @OA\Property(property="birthday", type="string", example="1989-03-25T00:00:00Z"),
     *          @OA\Property(property="birthPlace", type="string", example="ГОР.ВЛАДИВОСТОК ПРИМОРСКОГО КРАЯ"),
     *          @OA\Property(property="serviceEDO", type="string", example="Diadoc"),
     *          @OA\Property(property="isManual", type="boolean", example=false),
     *          @OA\Property(property="address", type="array", @OA\Items(ref="#/components/schemas/Address")),
     *          @OA\Property(property="passport", type="array", @OA\Items(ref="#/components/schemas/Passport"))
     *     )
     */
    /**
     *      @OA\Schema(schema="DataIP", type="object",
     *           @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="IP"),
     *           @OA\Property(property="synchId", type="string", example="1c2c8733-e5d6-41b4-929c-bc196879f785"),
     *           @OA\Property(property="inn", type="string", example="667100354160"),
     *           @OA\Property(property="ogrnip", type="string", example="323665800170896"),
     *           @OA\Property(property="okpo", type="string", example="2025313195"),
     *           @OA\Property(property="okved", type="string", example="62.01"),
     *           @OA\Property(property="companyRegDate", type="string", example="2023-08-22T00:00:00Z"),
     *           @OA\Property(property="fnsDepartment", type="string", nullable=true),
     *           @OA\Property(property="firstName", type="string", example="Андрей "),
     *           @OA\Property(property="lastName", type="string", example="Татарченков "),
     *           @OA\Property(property="secondName", type="string", example="Павлович"),
     *           @OA\Property(property="birthday", type="string", example="1989-03-25T00:00:00Z"),
     *           @OA\Property(property="birthPlace", type="string", example="ГОР.ВЛАДИВОСТОК ПРИМОРСКОГО КРАЯ"),
     *           @OA\Property(
     *               property="marketplaceLinks",
     *               type="array",
     *               @OA\Items(type="string", example="https://www.wildberries.ru/seller/https://www.wildberries.ru/brands/lizun-toys")
     *           ),
     *           @OA\Property(property="serviceEDO", type="string", example="Diadoc"),
     *           @OA\Property(property="isManual", type="boolean", example=false),
     *           @OA\Property(property="address", type="array", @OA\Items(ref="#/components/schemas/Address")),
     *           @OA\Property(property="passport", type="array", @OA\Items(ref="#/components/schemas/Passport"))
     *      )
     *
     */
    /**
     *      @OA\Schema(schema="DataUL", type="object",
     *         @OA\Property(property="synchId", type="string", example="123456789"),
     *         @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="UL"),
     *         @OA\Property(property="inn", type="string", example="7701234567"),
     *         @OA\Property(property="kpp", type="string", example="770101001"),
     *         @OA\Property(property="companyName", type="string", example="ООО Ромашка"),
     *         @OA\Property(property="companyFullName", type="string", example="Общество с ограниченной ответственностью 'Ромашка'"),
     *         @OA\Property(property="companyRegDate", type="string", format="date", example="2010-05-20"),
     *         @OA\Property(property="fnsDepartment", type="string", example="46 МИ ФНС России по г. Москве"),
     *         @OA\Property(property="ogrn", type="string", example="1107746693200"),
     *         @OA\Property(property="okpo", type="string", example="12345678"),
     *         @OA\Property(property="oktmo", type="string", example="45384000"),
     *         @OA\Property(property="okved", type="string", example="62.01"),
     *         @OA\Property(property="serviceEDO", type="string", example="Контур.Эльба"),
     *         @OA\Property(
     *             property="marketplaceLinks",
     *             type="array",
     *             @OA\Items(type="string", example="https://www.wildberries.ru/seller/https://www.wildberries.ru/brands/lizun-toys")
     *         ),
     *         @OA\Property(property="address", type="array", @OA\Items(ref="#/components/schemas/Address")),
     *         @OA\Property(property="director", ref="#/components/schemas/DataFL"),
     *         @OA\Property(property="beneficiars", type="array", @OA\Items(ref="#/components/schemas/Beneficiar"))
     *     )
     */
    /**
     *      @OA\Schema(schema="Address", type="object",
     *         @OA\Property(property="type", type="string", enum={"registration", "actual", "legal"}, example="registration"),
     *         @OA\Property(property="fiasId", type="string", example="6c37c61c-e195-4651-b4fe-0707efc77be6")
     *      )
     */
    /**
     *      @OA\Schema(schema="Beneficiar", type="object",
     *         @OA\Property(property="type", type="string", enum={"FL","IP","UL"}, example="FL"),
     *         oneOf={
     *              @OA\Schema(ref="#/components/schemas/DataIP"),
     *              @OA\Schema(ref="#/components/schemas/DataFL")
     *          },
     *          discriminator=@OA\Discriminator(propertyName="type", mapping={
     *              "IP": "#/components/schemas/DataIP",
     *              "FL": "#/components/schemas/DataFL"
     *          })
     *     )
     */
    /**
     *      @OA\Schema(schema="Passport", type="object",
     *         @OA\Property(property="files", ref="#/components/schemas/PassportFiles"),
     *         @OA\Property(property="issuer", type="string", example="ОВД Пресненского района г. Москвы"),
     *         @OA\Property(property="number", type="string", example="123456"),
     *         @OA\Property(property="series", type="string", example="4510"),
     *         @OA\Property(property="issuedAt", type="string", format="date", example="2005-06-15"),
     *         @OA\Property(property="issuerCode", type="string", example="770-001")
     *      )
     */
    /**
     *      @OA\Schema(schema="BankAccount", type="object",
     *         @OA\Property(property="crmId", type="string"),
     *         @OA\Property(property="sellerInn", type="string"),
     *         @OA\Property(property="title", type="string", enum={"расчетный", "номинальный"}, example="расчетный"),
     *         @OA\Property(property="nameBank", type="string"),
     *         @OA\Property(property="bankIdCode", type="string"),
     *         @OA\Property(property="checkAccount", type="string"),
     *         @OA\Property(property="adjAccount", type="string")
     *      )
     */
    /**
     *      @OA\Schema(schema="PassportFiles", type="object",
     *          @OA\Property(property="fileName", type="string", example="charter.pdf"),
     *          @OA\Property(property="file", type="string", format="binary")
     *      )
     *
     */
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

    /**
     *  Добавление Селлера из ЛК
     */
    /**
     * @OA\Post(path="/sellers/",
     *       tags={"Sellers"},
     *       summary="Добавление Селлера",
     *       operationId="setAction",
     *       @OA\Response(
     *           response=200,
     *           description="Успешный ответ"
     *       ),
     *       @OA\RequestBody(ref="#/components/requestBodies/setSeller")
     * )
     *
     */
    /**
     * @OA\RequestBody(request="setSeller",
     *     description="Новый селлер",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="sellerInn", type="string", example="667100354160"),
     *           @OA\Property(property="crmId", type="string", example="300"),
     *           @OA\Property(property="sellerData", ref="#/components/schemas/SellerData")
     *        )
     *     )
     *  )
     * @throws ArgumentException
     */
    public function setAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();
        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();
        $url = $server -> get('SCRIPT_URI') . "?" .$server -> get('QUERY_STRING');

        $token = str_replace('BitrixAuth ', '', $server->get('REMOTE_USER'));
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления Селлера из ЛК: ";
        $objectData = $this->CURLObjectData;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        Logs\File ::AddMessage(json_encode($requestJson,JSON_UNESCAPED_UNICODE), "requestJson", LOG_API_SYNC_SELLER_CONTROLLER);

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        /*$controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса
        $requestTypeId = 0;
        $outRequest = false;
        $jsonRes = ['status' => $statusRequest, 'response' => null];
        $partnerName = "SE";
        $taskId = 0;*/

        $arRequest = json_decode($requestJson,true);

        \Bitrix\Main\Loader ::IncludeModule('crm');
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = 'Тело запроса не удалось декодировать как JSON.';

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($arRequest['sellerInn'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `sellerInn`';

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(is_null($arRequest['sellerData']['serviceEDO'])) {
            $errorMessage = 'Не заполнено поле `serviceEDO` в sellerData.';

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        else {
            Loader::includeModule('iblock');

            $sellerInn = $arRequest['sellerInn'];
            $this->CURLObjectData['ITEM_TITLE'] = "Результат добавления Селлера из ЛК: {$sellerInn}";

            $sellerDataArray = $arRequest['sellerData'];
            $directorDataArray = $arRequest['directorData'];
            $beneficiarsArray = $arRequest['beneficiars'];
            $sellerCardId = 0;
            //region Обработка sellerData
            if(isset($sellerDataArray)) {
                $sellerDataInn = $sellerDataArray['inn'];
                if(is_null($arRequest['crmId'])) {
                    $sellerCardId = $this->findCard($sellerDataInn); //поиск клиента по sellerInn
                } else {
                    $crmId = intval($arRequest['crmId']);
                    $sellerCardId = $this->findCard($sellerDataInn, $crmId); //поиск клиента по sellerInn или crmId
                }
                //region Обновляем Селлера
                $this->createOrUpdateCard($sellerCardId, $sellerDataArray, false, $sellerCardId, "seller");
                $this->createOrUpdateRQ($sellerCardId, $sellerDataArray);
                //endregion
            }
            //endregion

            //region Обработка directorData
            if(isset($directorDataArray)) {
                if(is_null($arRequest['directorData']['serviceEDO'])) {
                    $errorMessage = 'Не заполнено поле `serviceEDO` в directorData.';

                    return $HandlerResponse->handleError($errorMessage, "invalid_request",
                        $objectData, $url, $requestMethod, $timeData, $requestJson, $headersValues);
                }

                $directorCardId = (new SellerService)->findCard($directorDataArray['inn'], false); //поиск руководителя по inn
                //Logs\File ::AddMessage($directorCardId, "directorCardId", LOG_API_SYNC_SELLER_CONTROLLER);
                //region Создаем руководителя
                if(!$directorCardId) {
                    //Logs\File ::AddMessage("Создаем карточку и реквизиты руководителя", "create",LOG_API_SYNC_SELLER_CONTROLLER);
                    $_directorCardId = (new SellerService)->createOrUpdateCard($sellerCardId, $directorDataArray,true, null,"director");
                    (new SellerService)->createOrUpdateCard($sellerCardId, $directorDataArray,false, $_directorCardId,"director");
                    (new SellerService)->createOrUpdateRQ($_directorCardId, $directorDataArray, true);
                }
                //endregion
                //region Обновляем руководителя
                else {
                    //Logs\File ::AddMessage("Обновляем карточку и реквизиты руководителя", "update", LOG_API_SYNC_SELLER_CONTROLLER);
                    (new SellerService)->createOrUpdateCard($sellerCardId, $directorDataArray, false, $directorCardId, "director");
                    (new SellerService)->createOrUpdateRQ($directorCardId, $directorDataArray);
                }
                //endregion
            }
            //endregion

            //region Обработка beneficiars
            if(isset($beneficiarsArray)) {
                foreach ($beneficiarsArray as $beneficiarDataArray)
                {
                    if(is_null($beneficiarDataArray['serviceEDO'])) {
                        $errorMessage = 'Не заполнено поле `serviceEDO` в beneficiars.';

                        return $HandlerResponse->handleError($errorMessage, "invalid_request",
                            $objectData, $url, $requestMethod, $timeData, $requestJson, $headersValues);
                    }
                    $beneficiarCardId = (new SellerService)->findCard($beneficiarDataArray['inn'], false); //поиск клиента по sellerInn или crmId
                    $beneficiarCardIds[] = $beneficiarCardId;
                    //region Создаем бенефициара
                    if(!$beneficiarCardId) {
                        //Logs\File ::AddMessage("Создаем карточку и реквизиты бенефициара", "create", LOG_API_SYNC_SELLER_CONTROLLER);
                        $_beneficiarCardId = (new SellerService)->createOrUpdateCard(
                            $sellerCardId,
                            $beneficiarDataArray,
                            true,
                            null,
                            "beneficiar"
                        );
                        (new SellerService)->createOrUpdateCard(
                            $sellerCardId,
                            $beneficiarDataArray,
                            false,
                            $_beneficiarCardId,
                            "beneficiar"
                        );
                        (new SellerService)->createOrUpdateRQ($_beneficiarCardId, $beneficiarDataArray, true);
                    }
                    //endregion
                    //region Обновляем бенефициара
                    else {
                        //Logs\File ::AddMessage("Обновляем карточку и реквизиты бенефициара", "update", LOG_API_SYNC_SELLER_CONTROLLER);
                        (new SellerService)->createOrUpdateCard($sellerCardId, $beneficiarDataArray, false, $beneficiarCardId, "beneficiar");
                        (new SellerService)->createOrUpdateRQ($beneficiarCardId, $beneficiarDataArray);
                    }
                    //endregion
                }
                //Logs\File ::AddMessage($beneficiarCardIds, "beneficiarCardIds", LOG_API_SYNC_SELLER_CONTROLLER);
            }
            //endregion

            // Если все прошло успешно
            $message = "Данные успешно сохранились";
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $crmId,
                    "ENTITY_TYPE" => "DYNAMIC_128",
                    "COMMENT" => "[b]{$message} от Seller-Engine![/b]"
                ]
            ]);

            $this->CURLObjectData['INIT_OBJECT_URL'] = "https://{$serverName}/crm/type/128/details/{$crmId}/";
            $objectData = $this->CURLObjectData;

            return $HandlerResponse->handleSuccess([$message], $objectData);
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
        //region Подготовка к обработке запроса
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');

        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления Селлера из Анонимной формы: ";
        $objectData = $this->CURLObjectData;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );

        $arRequest = json_decode($requestJson,true);

        \Bitrix\Main\Loader ::IncludeModule('crm');
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = 'Тело запроса не удалось декодировать как JSON.';

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        elseif(empty($queryParamsArray['guid'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `guid`";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($arRequest['inn'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `inn`';

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        else {
            $dealGUID = $queryParamsArray['guid'];
            $inn = $arRequest['inn'];
            $this->CURLObjectData['ITEM_TITLE'] = "Результат добавления Селлера из Анонимной формы: {$inn}";

            //region Обработка sellerData

            if($dealGUID == '') {
                $sellerCardId = (new SellerService)->findCard($inn); //поиск клиента по sellerInn
            } else {
                $sellerCard = (new SellerService)->findCardByDealGUID($inn, $dealGUID); //поиск клиента по sellerInn или crmId
                if(is_array($sellerCard)) {
                    $sellerCardId = $sellerCard['COMPANY_ID']; //поиск клиента по sellerInn или crmId
                    $dealCardId = $sellerCard['ID']; //поиск сделки по $dealGUID
                    (new SellerService)->updateDealCard($dealCardId);
                }  else {
                    $errorMessage = 'Карточка клиента не найдена';
                    return $HandlerResponse->handleError(404, $errorMessage, "invalid_request", $objectData);
                }
            }
            (new SellerService)->updateCompanyCard($sellerCardId, $arRequest);
            (new SellerService)->createOrUpdateRQ($sellerCardId, $arRequest);

            //endregion

            // Если все прошло успешно
            $message = "Данные успешно сохранились";
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $sellerCardId,
                    "ENTITY_TYPE" => "COMPANY",
                    "COMMENT" => "[b]{$message} от Seller-Engine![/b]"
                ]
            ]);

            $this->CURLObjectData['INIT_OBJECT_URL'] = "https://{$serverName}/crm/type/company/details/{$sellerCardId}/";
            $objectData = $this->CURLObjectData;

            return $HandlerResponse->handleSuccess($message, $objectData);
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
     *           @OA\Property(property="guarantorData", ref="#/components/schemas/GuarantorData")
     *        )
     *     )
     *  )
     */
    /**
     * @param array $params
     * @return array|EventResult|mixed
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    public function setGuarantorAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса

        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();$url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');

        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления Поручителя из ЛК: ";
        $objectData = $this->CURLObjectData;
        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );

        $requestArray = json_decode($requestJson,true);
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = 'Тело запроса не удалось декодировать как JSON.';
            $objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }
        if(empty($requestArray['crmId'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `crmId`';
            $objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(is_null($requestArray['guarantorData']['serviceEDO'])) {
            $errorMessage = 'Не заполнено поле `serviceEDO` в guarantorData.';
            $objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК: {$errorMessage}";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion
        else
        {

            $sellerInn = $requestArray['sellerInn'];
            $objectData['ITEM_TITLE'] = "Результат добавления Поручителя из ЛК по ИНН Селлера: {$sellerInn}";

            //region Поиск карточки Селлера
            $crmId = intval($requestArray['crmId']);
            $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента по sellerInn или crmId
            //endregion

            $guarantorDataArray = $requestArray['guarantorData'];
            $guarantorCardId = (new SellerService)->findCard($guarantorDataArray['inn'], false); //поиск клиента по sellerInn или crmId

            //region Создаем Поручителя
            if (!$guarantorCardId)
            {
                Logs\File ::AddMessage("Создаем карточку и реквизиты Поручителя", "create",
                    LOG_API_SYNC_SELLER_CONTROLLER);
                $_guarantorCardId = (new SellerService)->createOrUpdateCard(
                    $sellerCardId,
                    $guarantorDataArray,
                    true,
                    null,
                    "guarantor",
                    $crmId
                );
                (new SellerService)->createOrUpdateCard(
                    $sellerCardId,
                    $guarantorDataArray,
                    true,
                    $_guarantorCardId,
                    "guarantor",
                    $crmId
                );
                (new SellerService)->createOrUpdateRQ($_guarantorCardId, $guarantorDataArray, true);
            }
            //endregion

            //region Обновляем Поручителя
            else
            {
                Logs\File ::AddMessage("Обновляем карточку и реквизиты Поручителя", "update",
                    LOG_API_SYNC_SELLER_CONTROLLER);
                (new SellerService)->createOrUpdateCard($sellerCardId, $guarantorDataArray, false, $guarantorCardId, "guarantor", $crmId);
                (new SellerService)->createOrUpdateRQ($guarantorCardId, $guarantorDataArray);
            }
            //endregion
        }
        $message = ["Изменения приняты"];

        return $HandlerResponse->handleSuccess($message, $objectData);
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
     * @return array|EventResult|mixed
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
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');

        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Добавление счетов по ИНН из ЛК: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );

        $requestArray = json_decode($requestJson,true);
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }

        if(empty($requestArray['bankAccounts'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `bankAccounts`";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }
        //endregion
        else
        {
            $sellerInn = null;
            $title = null;

            $token = str_replace('BitrixAuth ', '', $server -> get('REMOTE_USER'));

            Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

            foreach ($requestArray['bankAccounts'] as $bankAccount) {
                $sellerInn = $bankAccount['sellerInn'];
                $crmId = $bankAccount['crmId'];
                $title = $bankAccount['title'];
                $nameBank = $bankAccount['nameBank'];
                $bankIdCode = $bankAccount['bankIdCode'];
                $checkAccount = $bankAccount['checkAccount'];
                $adjAccount = $bankAccount['adjAccount'];

                $objectData['ITEM_TITLE'] = "Получение `{$title}` по ИНН: {$sellerInn}";

                if($crmId !== NULL) {
                    $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента
                } else {
                    $sellerCardId = (new SellerService)->findCard($sellerInn);
                }

                Logs\File ::AddMessage($sellerCardId, "sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);

                if(!is_int($sellerCardId)) {
                    $errorMessage = 'Не существует Селлера с таким ИНН или CRMID';

                    return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
                }

                $requisite = \CRest::call(
                    "crm.requisite.list",
                    array(
                        "filter" => ["ENTITY_ID" => $sellerCardId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
                        "select" => ["ID","PRESET_ID", "ENTITY_ID", "ENTITY_TYPE_ID"])
                )['result'];

                Logs\File ::AddMessage($requisite, "requisite", LOG_API_SYNC_SELLER_CONTROLLER);


                $rqId = $requisite[0]['ID'];

                $parameters = [
                    "fields" => [
                        "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                        "ENTITY_ID" => $rqId,
                        "NAME" => $title,
                        "RQ_BANK_NAME" => $nameBank,
                        "RQ_BIK" => $bankIdCode,
                        "RQ_BIC" => $bankIdCode,
                        "RQ_ACC_NUM" => $checkAccount,
                        "RQ_COR_ACC_NUM" => $adjAccount,
                        "RQ_ACC_CURRENCY" => "RUB",
                        "COMMENTS" => "МКК"
                    ]
                ];
                \CRest ::call('crm.requisite.bankdetail.add', $parameters);
            }
            $message = ["{$title} для {$sellerInn} успешно добавлен!"];

            return $HandlerResponse->handleSuccess($message, $objectData);
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
     * @return array|EventResult|mixed
     * @throws ArgumentException
     * @throws LoaderException
     */
    public function setLoanAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $server = $context->getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server->get('SCRIPT_URI') . '?' . $server->get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE:Новый транш для ИНН: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );

        $requestArray = json_decode($requestJson, true);
        //endregion

        //region Обработка ошибок
        if ($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }

        if (empty($requestArray['sellerInn'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        //region Процесс обработки
        $entityTypeIdLK = 128;
        $factoryLK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLK);

        $token = str_replace('BitrixAuth ', '', $server->get('REMOTE_USER'));

        $sellerInn = $requestArray['sellerInn'];
        $crmId = intval($requestArray['crmId']);
        $loanData = $requestArray['loanData'];
        $loanAmount = floatval($loanData['amount']);
        $loanTerm = intval($loanData['term']);


        //region $loanTermId
        $rsEnumTerm = \CUserFieldEnum::GetList(array(), array(
            "XML_ID" => "{$loanTerm}_MONTHS",
        ));
        if ($arEnumTerm = $rsEnumTerm->Fetch()) {
            $loanTermId = (int)$arEnumTerm['ID'];
        }
        //endregion $loanTermId

        $purposeLoan = $loanData['purposeLoan'];

        $typeContract = (bool)$loanData['typeContract'];
        $isfirstLoan = (bool)$loanData['isFirstTranche'];

        if ($crmId > 0) {
            $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId);
        } else {
            $sellerCardId = (new SellerService)->findCard($sellerInn);
        }

        if ($isfirstLoan) {
            $parametersLK = [
                'filter' => [
                    '=COMPANY_ID' => $sellerCardId,
                    'STAGE_ID' => 'DT128_226:UC_6GB0Q7', //Ожидание решения клиента
                    'CATEGORY_ID' => 226
                ],
                'select' => ['ID']
            ];
            $itemsLK = $factoryLK->getItems($parametersLK);
            if ($itemsLK) {
                foreach ($itemsLK as $itemLK) {
                    Logs\File::AddMessage($itemLK->getId(), "LKgetId", LOG_API_SYNC_SELLER_CONTROLLER);

                    $itemLK->set('UF_CRM_CRMID', $crmId);
                    $itemLK->set('UF_CRM_INN', $sellerInn);
                    $itemLK->set('UF_CRM_LOAN_AMOUNT', $loanAmount);
                    $itemLK->set('UF_CRM_LOAN_TERM', $loanTermId);
                    $itemLK->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purposeLoan);
                    if ($typeContract) {
                        $rsEnum = \CUserFieldEnum::GetList(array(), array(
                            "XML_ID" => "WITH_DELAY",
                        ));
                        if ($arEnum = $rsEnum->Fetch()) {
                            $typeContractTrueId = $arEnum['ID'];
                        }
                        $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractTrueId);
                    } else {
                        $rsEnum = \CUserFieldEnum::GetList(array(), array(
                            "XML_ID" => "NO_DELAY",
                        ));
                        if ($arEnum = $rsEnum->Fetch()) {
                            $typeContractFalseId = $arEnum['ID'];
                        }
                        $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractFalseId);
                    }
                    //$itemLK->setStageId('DT128_208:UC_VC1XA7');
                }
                $itemLK->save();
                $itemLK->setStageId('DT128_226:CLIENT');
                $itemLK->setCategoryId(226);

                $operationLK = $factoryLK->getUpdateOperation($itemLK);
                $operationLK->disableAllChecks();
                $operationLKResult = $operationLK->launch();

                // Проверка на ошибки
                if ($operationLKResult->isSuccess()) {
                    $this->CURLObjectData['ITEM_TITLE'] = "SE:Новый транш для ИНН: {$sellerInn}";
                    $message = "Новый транш для ИНН: {$sellerInn} успешно создан";
                    $objectData = $this->CURLObjectData;

                    return $HandlerResponse->handleSuccess($message, $objectData);
                } else {
                    Context::getCurrent()->getResponse()->setStatus(500);
                    $errorMessage = "Создание транша не удалось";
                    return $HandlerResponse->handleError(500, $errorMessage, "invalid_request", $objectData);
                }
            } else {
                Context::getCurrent()->getResponse()->setStatus(400);
                $errorMessage = "Создание первого транша невозможно, уже существует!";
                return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
            }
        }
        else {
            $itemPrevLK = $factoryLK->getItem($crmId);
            $titleItemPrevLk = $itemPrevLK->get('TITLE');
            $parentId134ItemPrevLK = $itemPrevLK->get('PARENT_ID_134');
            $createdByItemPrevLK = $itemPrevLK->get('CREATED_BY');

            $itemLK = $factoryLK->createItem();
            $itemLK->set('TITLE', "Повторный транш " . $titleItemPrevLk);
            $itemLK->set('COMPANY_ID', $sellerCardId);
            $itemLK->set('UF_CRM_CRMID', $crmId);
            $itemLK->set('PARENT_ID_134', $parentId134ItemPrevLK);
            $itemLK->set('UF_CRM_INN', $sellerInn);
            $itemLK->set('UF_CRM_LOAN_AMOUNT', $loanAmount);
            $itemLK->set('UF_CRM_LOAN_TERM', $loanTermId);
            $itemLK->set('UF_CRM_PURPOSE_OF_THE_LOAN', $purposeLoan);
            $itemLK->set('UF_CRM_REPEAT_ZAYAVKA', 1);
            if ($typeContract) {
                $rsEnum = \CUserFieldEnum::GetList(array(), array(
                    "XML_ID" => "WITH_DELAY",
                ));
                if ($arEnum = $rsEnum->Fetch()) {
                    $typeContractTrueId = $arEnum['ID'];
                }
                Logs\File::AddMessage($typeContractTrueId, "typeContractTrueId", LOG_API_SYNC_SELLER_CONTROLLER);
                $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractTrueId);
            } else {
                $rsEnum = \CUserFieldEnum::GetList(array(), array(
                    "XML_ID" => "NO_DELAY",
                ));
                if ($arEnum = $rsEnum->Fetch()) {
                    $typeContractFalseId = $arEnum['ID'];
                }
                $itemLK->set('UF_CRM_LKSC_TYPE_OF_CONTRACT', $typeContractFalseId);
            }

            $context = new \Bitrix\Crm\Service\Context;
            $context->setUserId($createdByItemPrevLK);

            $itemLK->save();
            $itemLK->setStageId('DT128_226:NEW');
            $itemLK->setCategoryId(226);

            $operationLK = $factoryLK->getAddOperation($itemLK, $context);
            $operationLK->disableAllChecks();
            $operationLKResult = $operationLK->launch();

            // Проверка на ошибки
            if ($operationLKResult->isSuccess()) {
                $this->CURLObjectData['ITEM_TITLE'] = "SE: Повторный транш для ИНН: {$sellerInn}";
                $objectData = $this->CURLObjectData;
                $message = "Повторный транш для ИНН: {$sellerInn} успешно создан";

                return $HandlerResponse->handleSuccess($message, $objectData);
            } else {
                $errorMessage = "Создание транша не удалось";
                return $HandlerResponse->handleError(500, $errorMessage, "invalid_request", $objectData);
            }
        }
        //endregion
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
     * @return array|EventResult|mixed
     * @throws LoaderException
     * @throws SqlQueryException
     */
    public function setSmavInfoAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Получение данных от СМЭВ: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }
        $requestArray = json_decode($requestJson,true);

        $taskId = $requestArray['Id'];

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context,
            false,
            false,
            $taskId
        );
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        elseif(empty($queryParamsArray['crmEntityId']) || empty($queryParamsArray['rqId'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `crmEntityId` or `rqId`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        elseif(!isset($requestArray['Response']["services"]) || !is_array($requestArray['Response']["services"])) {
            $errorMessage = "400 Bad Request | Отсутствует массив 'services' в запросе.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        //region Процесс обработки
        if (str_contains($queryParamsArray['crmEntityId'], 'company_')) {
            $entityId = str_replace('company_', '', $queryParamsArray['crmEntityId']);
            (new SellerService)->getCompanyInfoById($entityId);
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        }
        elseif (str_contains($queryParamsArray['crmEntityId'], 'contact_')) {
            $entityId = str_replace('contact_', '', $queryParamsArray['crmEntityId']);
            (new SellerService)->getContactInfoById($entityId);
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        }
        else {
            $errorMessage = "400 Bad Request | Некорректный параметр 'crmEntityId' в запросе.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }

        $services = $requestArray['Response']["services"];
        $item = $factory->getItem($entityId);

        if (!$item) {
            $errorMessage = "400 Bad Request | Некорректный параметр 'crmEntityId' в запросе.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }


        Logs\File ::AddMessage($item->getId(), "->getId()", LOG_API_SYNC_SELLER_CONTROLLER);

        // Сохраняем данные и проверяем результат
        $saveResult = (new SellerService)->saveAllData($factory, $item, $services);
        (new SellerService)->setCURLObjectData($item->getId());
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Получение данных от СМЭВ: ". $this->itemDatatitle;

        if ($saveResult['status'] === 'error') {
            // Если произошла ошибка, добавляем комментарий с сообщениями об ошибках
            $errorMessage = "Ошибка при сохранении данных: " . implode(', ', $saveResult['messages']);
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item->getId(),
                    "ENTITY_TYPE" => "COMPANY",
                    "COMMENT" => "[b]{$errorMessage}[/b]"
                ]
            ]);
            $objectData = $this->CURLObjectData;
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        // Если все прошло успешно
        $message = "Данные успешно сохранились";
        $objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($message, $objectData);
        //endregion
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
     */
    public function getCloseDateConsentAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE:Получение даты окончания согласия по ИНН: ";
        $objectData = $this->CURLObjectData;

        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        $arRequest = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_SELLER_CONTROLLER);
            $arRequest = $queryParamsArray;
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_SELLER_CONTROLLER);
            $arRequest = json_decode($requestJson,true);
        }
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }

        $sellerInn = $arRequest['sellerInn'];
        $crmId = (int) $arRequest['crmId'];

        if(empty($sellerInn)) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        else {
            $this->CURLObjectData['ITEM_TITLE'] = "Получение даты окончания согласия по ИНН: {$sellerInn}";

            if ($crmId !== 0) {
                $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента
            } else {
                $sellerCardId = (new SellerService)->findCard($sellerInn);
            }

            if (!is_int($sellerCardId)) {
                $errorMessage = "Не существует Селлера с таким ИНН или CRMID";
                return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
            }

            $entityTypeIdOSK = 134;
            $factoryOSK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdOSK);
            $parametersOSK = [
                'filter' => [
                    '=COMPANY_ID' => $sellerCardId
                ]
            ];
            $itemsOSK = $factoryOSK->getItems($parametersOSK);
            $arResultJson = '';
            //$arResult['closeDateConsent'] = null;
            foreach ($itemsOSK as $itemOSK) {
                $cardOSKData = $itemOSK->getData();
                $endDateConsent = date('Y-m-d\TH:i:s.msp', strtotime($cardOSKData['UF_CRM_END_DATE_OF_CONSENT']));
                $arResult['closeDateConsent'] = $endDateConsent;
                $arResultJson = json_encode($arResult, JSON_UNESCAPED_UNICODE);
                break;
            }

            $objectData = $this->CURLObjectData;

            return $HandlerResponse->handleSuccess($arResultJson, $objectData);
        }
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
    public function getBankAccountAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE:Получение банковских реквизитов по ИНН: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );

        $requestArray = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = $queryParamsArray;
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = json_decode($requestJson,true);
        }
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }

        if(empty($requestArray['sellerInn'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        elseif(empty($requestArray['crmId'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `crmId`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion
        else
        {
            $token = str_replace('BitrixAuth ', '', $server -> get('REMOTE_USER'));
            Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_SELLER_CONTROLLER);

            //foreach ($requestArray['bankAccounts'] as $bankAccount) {
            $sellerInn = $requestArray['sellerInn'];
            $crmId = (int) $requestArray['crmId'];
            //$title = $bankAccount['title'];
            //$nameBank = $bankAccount['nameBank'];
            //$bankIdCode = $bankAccount['bankIdCode'];
            //$checkAccount = $bankAccount['checkAccount'];
            //$adjAccount = $bankAccount['adjAccount'];

            $this->CURLObjectData['ITEM_TITLE'] = "SE:Получение банковских реквизитов по ИНН: {$sellerInn}";
            $sellerCardId = (new SellerService)->findCard($sellerInn);

            Logs\File ::AddMessage($sellerCardId, "sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);

            if(!is_int($sellerCardId)) {
                if($crmId > 0) {
                    $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента
                }
                if(!is_int($sellerCardId)) {
                    $errorMessage = 'Не существует Селлера с таким ИНН или CRMID';
                    return $HandlerResponse->handleError(404, $errorMessage, "invalid_request", $objectData);
                }
            }

            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_ID" => $sellerCardId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
                    "select" => ["*","UF_*"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "requisite", LOG_API_SYNC_SELLER_CONTROLLER);

            $rqId = $requisite[0]['ID'];
            $parameters = [
                "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,	"ENTITY_ID" => $rqId]
            ];
            Logs\File ::AddMessage($parameters, "parameters for bankdetaillist", LOG_API_SYNC_SELLER_CONTROLLER);
            $arResult = \CRest::call('crm.requisite.bankdetail.list', $parameters)['result'];

            $entityTypeIdCompany = \CCrmOwnerType::Company;
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);

            $companyItem = $factoryCompany->getItem($sellerCardId);

            $typeofBankingService = 'nominal';
            if($companyItem) {

                $userFields = \Bitrix\Main\UserFieldTable::getList([
                    'select' => ['ID'],
                    'filter' => [
                        '=ENTITY_ID' => 'CRM_COMPANY',
                        'FIELD_NAME' => 'UF_CRM_TYPE_OF_WRITE_OFF'
                    ]
                ]);

                while ($arUserField = $userFields->fetch()){
                    $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID']]);
                    while ($arUserFieldData = $res->fetch()) {
                        if($arUserFieldData['ID'] == $companyItem->getData()['UF_CRM_TYPE_OF_WRITE_OFF']) {
                            $typeofBankingService = $arUserFieldData['XML_ID'];
                        }
                    }
                }
            }

            // Инициализация переменных для хранения крайних счетов
            $lastCurrentAccount = null;
            $lastNominalAccount = null;

            $newResult['crmId'] = $crmId;
            $newResult['sellerInn'] = $sellerInn;
            $newResult['typeofBankingService'] = $typeofBankingService;

            foreach ($arResult as $bankAccount) {
                $result = [];
                $result['title'] = $bankAccount['NAME'];
                $result['nameBank'] = $bankAccount['RQ_BANK_NAME'];
                $result['bankIdCode'] = $bankAccount['RQ_BIK'];
                $result['checkAccount'] = $bankAccount['RQ_ACC_NUM'];
                $result['adjAccount'] = $bankAccount['RQ_COR_ACC_NUM'];
                $result['accCurrency'] = $bankAccount['RQ_ACC_CURRENCY'];
                $result['comments'] = $bankAccount['COMMENTS'];

                // Проверяем тип счета и сохраняем крайний "Расчетный" или "Номинальный" счет
                if ($bankAccount['NAME'] === 'Расчетный счет') {
                    $lastCurrentAccount = $result;
                } elseif ($bankAccount['NAME'] === 'Номинальный счет') {
                    $lastNominalAccount = $result;
                }
            }

            // Формируем результирующий массив только с непустыми значениями
            if (!isset($newResult['bankAccounts'])) {
                $newResult['bankAccounts'] = [];
            }
            if ($lastCurrentAccount !== null) {
                $newResult['bankAccounts'][] = $lastCurrentAccount;
            }
            if ($lastNominalAccount !== null) {
                $newResult['bankAccounts'][] = $lastNominalAccount;
            }

            $objectData = $this->CURLObjectData;

            return $HandlerResponse->handleSuccess($newResult, $objectData);
        }
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
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $token = str_replace('BitrixAuth ', '', $server -> get('REMOTE_USER'));

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Получение лимитов по ИНН: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $arRequest = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_SELLER_CONTROLLER);
            $arRequest = $queryParamsArray;
            //$requestJson = json_encode($arRequest);
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_SELLER_CONTROLLER);
            $arRequest = json_decode($requestJson,true);
        }


        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($arRequest['sellerInn'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        //region Процесс обработки
        $sellerInn = $arRequest['sellerInn'];
        $crmId = (int) $arRequest['crmId'];

        $this->CURLObjectData['ITEM_TITLE'] = "Получение лимитов по ИНН: {$sellerInn}";

        if($crmId > 0) {
            $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента
        } else {
            $sellerCardId = (new SellerService)->findCard($sellerInn);
        }

        if(!is_int($sellerCardId)) {
            $errorMessage = "Не существует Селлера с таким ИНН или CRMID";
            $objectData = $this->CURLObjectData;
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }

        $entityTypeIdOSK = 134;
        $factoryOSK = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeIdOSK);
        $parametersOSK = [
            'filter' => [
                '=COMPANY_ID' => $sellerCardId
            ]
        ];
        $itemsOSK = $factoryOSK -> getItems($parametersOSK);
        foreach ($itemsOSK as $itemOSK)
        {
            $cardOSKData = $itemOSK->getData();
        }

        $availableLimit = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744875738']));
        $allLimit = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744846487']));
        $possibleLimitIncrease = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_LIMIT_TO_INCREASE']));
        $dolg = floatval(str_replace("|RUB", "", $cardOSKData['UF_CRM_56_1684744827969']));
        $interestRate = floatval($cardOSKData['UF_CRM_INTEREST_RATE']);
        $commissionRate = floatval($cardOSKData['UF_CRM_COMMISSION_RATE']);
        $commentOnStatus = $cardOSKData['UF_CRM_COMMENT_ON_STATUS'];

        $tarifId = $cardOSKData['UF_CRM_TARIF_OF_SELLERS'];
        $res = \CUserFieldEnum::GetList([], ['ID' => $tarifId]);
        if ($element = $res->Fetch()) {
            $xmlId = $element['XML_ID'];
        } else {
            $errorMessage = "Тариф не выбран";
            $objectData = $this->CURLObjectData;
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }
        $tarif = 0;
        if($xmlId == 'Rate_1') $tarif = 3.5;
        if($xmlId == 'Rate_2') $tarif = 3.2;
        if($xmlId == 'Rate_3') $tarif = 3;
        if($xmlId == 'Rate_4') $tarif = 2.83;

        $arLimits['availableLimit'] = $availableLimit;
        $arLimits['allLimit'] = $allLimit;
        $arLimits['minLoanAmount'] = 150000.00;
        $arLimits['possibleLimitIncrease'] = $possibleLimitIncrease;
        $arLimits['dolg'] = $dolg;
        $arLimits['tarif'] = $tarif;
        $arLimits['interestRate'] = $interestRate;
        $arLimits['commissionRate'] = $commissionRate;
        $arLimits['commentOnStatus'] = $commentOnStatus;

        $arLimitsJson = json_encode($arLimits, JSON_UNESCAPED_UNICODE);
        $objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($arLimitsJson, $objectData);
        //endregion
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
     */
    public function getLoansAction(array $params = []): mixed
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $token = str_replace('BitrixAuth ', '', $server -> get('REMOTE_USER'));

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "Получение списка займов по ИНН: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $requestArray = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = $queryParamsArray;
            //$requestJson = json_encode($arRequest);
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = json_decode($requestJson,true);
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($requestArray['sellerInn'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `sellerInn`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        //region Процесс обработки
        $sellerInn = $requestArray['sellerInn'];
        $crmId = $requestArray['crmId'];
        $this->CURLObjectData['ITEM_TITLE'] = "Получение списка займов по ИНН и CRMID: {$sellerInn}";

        if(!is_null($crmId)) {
            $sellerCardId = (new SellerService)->findCard($sellerInn, $crmId); //поиск клиента
        } else {
            $sellerCardId = (new SellerService)->findCard($sellerInn);
        }

        if(!is_int($sellerCardId)) {
            $errorMessage = 'Не существует Селлера с таким ИНН или CRMID';
            $objectData = $this->CURLObjectData;
            return $HandlerResponse->handleError(404, $errorMessage, "invalid_request", $objectData);
        }

        $entityTypeIdDZ = 188;
        $factoryDZ = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeIdDZ);

        $parametersDZ = [
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
        $res = [];

        $itemsDZ = $factoryDZ -> getItems($parametersDZ);

        foreach ($itemsDZ as $c => $itemDZ)
        {
            $itemDZData = $itemDZ->getData();
            if(number_format((float) str_replace("|RUB","",$itemDZData['UF_CRM_15_1679907467']), 2,"."," ") == 0.00) {
                $sumProsrocheno = null;
            } else {
                $sumProsrocheno = number_format((float) str_replace("|RUB","",$itemDZData['UF_CRM_15_1679907467']), 2,"."," ");
            }

            $res[$c]['numberDog'] = $itemDZData["UF_CRM_15_SS_NOMER"];
            $res[$c]['sumDog'] = number_format((float) str_replace("|RUB","",$itemDZData["UF_CRM_15_SS_SUMMADOGOVORA"]), 2,"."," ");
            $res[$c]['dateDog'] = date('Y-m-d\TH:i:s.msp', strtotime($itemDZData["UF_CRM_15_1679925201"]));
            $res[$c]['nextPayDay'] = date('Y-m-d\TH:i:s.msp', strtotime($itemDZData["UF_CRM_15_SS_NEXTPAYDAY"]));
            $res[$c]['nextPaySum'] = number_format((float) str_replace("|RUB","", $itemDZData["UF_CRM_15_SS_NEXTPAYSUMMA"]), 2,"."," ");
            $res[$c]['prosrochenoDays'] = $itemDZData['UF_CRM_15_1679907525'];
            $res[$c]['sumProsrocheno'] = $sumProsrocheno;
            $res[$c]['ostatok'] = number_format((float) str_replace("|RUB","",$itemDZData["UF_CRM_15_SS_NOMINAL"]), 2,"."," ");
        }

        $objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($res, $objectData);
        //endregion
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
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $token = str_replace('BitrixAuth ', '', $server -> get('REMOTE_USER'));

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "Получение списка ИНН по когортам: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        $requestArray = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = $queryParamsArray;
            //$requestJson = json_encode($arRequest);
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_SELLER_CONTROLLER);
            $requestArray = json_decode($requestJson,true);
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "SE",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        //endregion

        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($requestArray['cohort'])) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `cohort`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        //region Процесс обработки
        $cohort = $requestArray['cohort'];
        $parametersOSK = [];
        $inn = [];
        switch($cohort) {
            case 'approved':
                $parametersOSK = [
                    'filter' => [
                        'UF_CRM_56_1684744827969' => 0.00,
                        '>UF_CRM_56_1684744846487' => 150000,
                        [
                            'LOGIC' => 'OR',
                            ["STAGE_ID" => "DT134_104:UC_EQ8KZU"],
                            ["STAGE_ID" => "DT134_104:UC_7VE0GD"],
                            ["STAGE_ID" => "DT134_104:NEW"]
                        ]
                    ]
                ];
                break;
            case 'close':
                $parametersOSK = [
                    'filter' => [
                        'STAGE_ID' => 'DT134_104:UC_S4RR8K'
                    ]
                ];
                break;
            case 'delay':
                $parametersOSK = [
                    'filter' => [
                        'LOGIC' => 'OR',
                        ["STAGE_ID" => "DT134_104:CLIENT"],
                        ["STAGE_ID" => "DT134_104:UC_IYGEPM"],
                        ["STAGE_ID" => "DT134_104:UC_V3JEQZ"]
                    ]
                ];
                break;
            default:
                break;
        }
        $entityTypeIdOSK = 134;
        $factoryOSK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdOSK);

        $itemsOSK = $factoryOSK->getItems($parametersOSK);
        foreach($itemsOSK as $item) {
            $inn[] = $item->getData()['UF_CRM_56_1684841075'];
        }

        return $HandlerResponse->handleSuccess($inn, $objectData);
        //endregion
    }
    //endregion GET
}
