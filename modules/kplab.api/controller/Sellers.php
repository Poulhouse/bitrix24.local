<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use Bitrix\Main\Context;
use \KPLab\API\V2\Helpers\HandlerResponse;

define("LOG_API_SYNC_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/SellersController.log");
define("LOG_API_SYNC_SET_SELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/SetSellersController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");

/**
 *
 * @OA\Tag(
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
     * @return ActionFilter\Authentication[]
     */
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new ActionFilter\Authentication(),
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
     *  @OA\RequestBody(request="setSeller",
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

                $directorCardId = self::findCard($directorDataArray['inn'], false); //поиск руководителя по inn
                //Logs\File ::AddMessage($directorCardId, "directorCardId", LOG_API_SYNC_SELLER_CONTROLLER);
                //region Создаем руководителя
                if(!$directorCardId) {
                    //Logs\File ::AddMessage("Создаем карточку и реквизиты руководителя", "create",LOG_API_SYNC_SELLER_CONTROLLER);
                    $_directorCardId = $this->createOrUpdateCard($sellerCardId, $directorDataArray,true, null,"director");
                    $this->createOrUpdateCard($sellerCardId, $directorDataArray,false, $_directorCardId,"director");
                    $this->createOrUpdateRQ($_directorCardId, $directorDataArray, true);
                }
                //endregion
                //region Обновляем руководителя
                else {
                    //Logs\File ::AddMessage("Обновляем карточку и реквизиты руководителя", "update", LOG_API_SYNC_SELLER_CONTROLLER);
                    $this->createOrUpdateCard($sellerCardId, $directorDataArray, false, $directorCardId, "director");
                    $this->createOrUpdateRQ($directorCardId, $directorDataArray);
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
                    $beneficiarCardId = $this->findCard($beneficiarDataArray['inn'], false); //поиск клиента по sellerInn или crmId
                    $beneficiarCardIds[] = $beneficiarCardId;
                    //region Создаем бенефициара
                    if(!$beneficiarCardId) {
                        //Logs\File ::AddMessage("Создаем карточку и реквизиты бенефициара", "create", LOG_API_SYNC_SELLER_CONTROLLER);
                        $_beneficiarCardId = $this->createOrUpdateCard(
                            $sellerCardId,
                            $beneficiarDataArray,
                            true,
                            null,
                            "beneficiar"
                        );
                        $this->createOrUpdateCard(
                            $sellerCardId,
                            $beneficiarDataArray,
                            false,
                            $_beneficiarCardId,
                            "beneficiar"
                        );
                        $this->createOrUpdateRQ($_beneficiarCardId, $beneficiarDataArray, true);
                    }
                    //endregion
                    //region Обновляем бенефициара
                    else {
                        //Logs\File ::AddMessage("Обновляем карточку и реквизиты бенефициара", "update", LOG_API_SYNC_SELLER_CONTROLLER);
                        $this->createOrUpdateCard($sellerCardId, $beneficiarDataArray, false, $beneficiarCardId, "beneficiar");
                        $this->createOrUpdateRQ($beneficiarCardId, $beneficiarDataArray);
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
                $sellerCardId = $this->findCard($inn); //поиск клиента по sellerInn
            } else {
                $sellerCard = $this->findCardByDealGUID($inn, $dealGUID); //поиск клиента по sellerInn или crmId
                if(is_array($sellerCard)) {
                    $sellerCardId = $sellerCard['COMPANY_ID']; //поиск клиента по sellerInn или crmId
                    $dealCardId = $sellerCard['ID']; //поиск сделки по $dealGUID
                    $this->updateDealCard($dealCardId);
                }  else {
                    $errorMessage = 'Карточка клиента не найдена';
                    return $HandlerResponse->handleError(404, $errorMessage, "invalid_request", $objectData);
                }
            }
            $this->updateCompanyCard($sellerCardId, $arRequest);
            $this->createOrUpdateRQ($sellerCardId, $arRequest);

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
            $sellerCardId = $this->findCard($sellerInn, $crmId); //поиск клиента по sellerInn или crmId
            //endregion

            $guarantorDataArray = $requestArray['guarantorData'];
            $guarantorCardId = $this -> findCard($guarantorDataArray['inn'], false); //поиск клиента по sellerInn или crmId

            //region Создаем Поручителя
            if (!$guarantorCardId)
            {
                Logs\File ::AddMessage("Создаем карточку и реквизиты Поручителя", "create",
                    LOG_API_SYNC_SELLER_CONTROLLER);
                $_guarantorCardId = $this -> createOrUpdateCard(
                    $sellerCardId,
                    $guarantorDataArray,
                    true,
                    null,
                    "guarantor",
                    $crmId
                );
                $this -> createOrUpdateCard(
                    $sellerCardId,
                    $guarantorDataArray,
                    true,
                    $_guarantorCardId,
                    "guarantor",
                    $crmId
                );
                $this -> createOrUpdateRQ($_guarantorCardId, $guarantorDataArray, true);
            }
            //endregion

            //region Обновляем Поручителя
            else
            {
                Logs\File ::AddMessage("Обновляем карточку и реквизиты Поручителя", "update",
                    LOG_API_SYNC_SELLER_CONTROLLER);
                $this -> createOrUpdateCard($sellerCardId, $guarantorDataArray, false, $guarantorCardId, "guarantor", $crmId);
                $this -> createOrUpdateRQ($guarantorCardId, $guarantorDataArray);
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
                    $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
                } else {
                    $sellerCardId = self::findCard($sellerInn);
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
            $sellerCardId = self::findCard($sellerInn, $crmId);
        } else {
            $sellerCardId = self::findCard($sellerInn);
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
            $this->getCompanyInfoById($entityId);
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        }
        elseif (str_contains($queryParamsArray['crmEntityId'], 'contact_')) {
            $entityId = str_replace('contact_', '', $queryParamsArray['crmEntityId']);
            $this->getContactInfoById($entityId);
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
        $saveResult = $this->saveAllData($factory, $item, $services);
        $this->setCURLObjectData($item->getId());
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
                $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
            } else {
                $sellerCardId = self::findCard($sellerInn);
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
            $sellerCardId = self::findCard($sellerInn);

            Logs\File ::AddMessage($sellerCardId, "sellerCardId", LOG_API_SYNC_SELLER_CONTROLLER);

            if(!is_int($sellerCardId)) {
                if($crmId > 0) {
                    $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
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
            $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
        } else {
            $sellerCardId = self::findCard($sellerInn);
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
            $sellerCardId = self::findCard($sellerInn, $crmId); //поиск клиента
        } else {
            $sellerCardId = self::findCard($sellerInn);
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

    //region Внутренние функции
    /**
     * Отправка данных в СМЭВ (внутр.)
     */
    public function postPassportData($companyId = null, $contactId = null): array|string
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
        $idSERequest = '';

        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();

        $headersValues = array(
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json; charset=utf-8",
            "accept" => "application/json"
        );

        $requestJson = $context->getRequest()->getInput();
        $requestMethod = "POST";
        $queryParamsArray = $context->getRequest()->toArray();
        $this->CURLObjectData['METHOD'] = $requestMethod;

        $errors = [];
        if (str_contains($serverName, 'test')) {
            $apiUrl = "https://api.dev.seller-capital.ru";
        } else {
            $apiUrl = "https://api.seller-capital.ru";
        }
        $url = $apiUrl . "/SendRequest";

        $data = [];
        if(!is_null($companyId)) {
            $this->getCompanyInfoById($companyId);
            $this->setCURLObjectData($companyId);
            $rqId = $this->rqId;
            $this->CURLObjectData['ITEM_TITLE'] = "Отправка данных в СМЭВ: ". $this->itemDatatitle;
            $payload = [
                "name" => (string) $this->sellerFirstName,
                "surname" => (string) $this->sellerLastName,
                "patronymic" => (string) $this->sellerSecondName,
                "pass_series" => (string) $this->sellerPassportSeries,
                "pass_number" => (string) $this->sellerPassportNumber,
                "birthdate" => (string) $this->sellerPassportBirthday,
                //"inn" => (string) $this->sellerInn,
                "gender" => null
            ];
            $data["request"] = [
                "payload" => $payload,
                "callback_url" => "https://{$serverName}/api/v2/sellers/smavInfo/?authId=5d0e5072-889b-52cd-950c-af8d58221115&crmEntityId=company_{$companyId}&rqId={$rqId}"
            ];
        }
        elseif(!is_null($contactId)) {
            $this->getContactInfoById($contactId);
            $this->setCURLObjectData($contactId);
            $rqId = $this->rqId;
            $this->CURLObjectData['ITEM_TITLE'] = "Отправка данных в СМЭВ: ". $this->itemDatatitle;
            $payload = [
                "name" => (string) $this->sellerFirstName,
                "surname" => (string) $this->sellerLastName,
                "patronymic" => (string) $this->sellerSecondName,
                "pass_series" => (string) $this->sellerPassportSeries,
                "pass_number" => (string) $this->sellerPassportNumber,
                "birthdate" => (string) $this->sellerPassportBirthday,
                "inn" => (string) $this->sellerInn,
                "gender" => null
            ];
            $data["request"] = [
                "payload" => $payload,
                "callback_url" => "https://{$serverName}/api/v1/sellers/smavInfo/?authId=5d0e5072-889b-52cd-950c-af8d58221115&crmEntityId=contact_{$contactId}&rqId={$rqId}"
            ];
        }
        $requestJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $objectData = $this->CURLObjectData;

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
            true
        );
        //endregion

        $jsonResponse = $HandlerResponse->getResponse($objectData);

        if(!is_null($companyId)) {
            $item = $factoryCompany->getItem($companyId);
            $arResponse = json_decode($jsonResponse['response'],true);
            $idSERequest = $arResponse['id'];
            $item->set('UF_CRM_SMEV_ID_REQUEST',$idSERequest);

            $operation = $factoryCompany->getUpdateOperation($item);
            $operation->disableAllChecks();

            // Сохраняем элемент CRM после установки всех полей
            $saveResult = $operation->launch();

            if (!$saveResult->isSuccess()) {
                return array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
            }
        }

        if(!is_null($contactId)) {
            $item = $factoryContact->getItem($contactId);
            $arResponse = json_decode($jsonResponse['response'],true);
            $idSERequest = $arResponse['id'];
            $item->set('UF_CRM_SMEV_ID_REQUEST',$idSERequest);

            $operation = $factoryContact->getUpdateOperation($item);
            $operation->disableAllChecks();

            // Сохраняем элемент CRM после установки всех полей
            $saveResult = $operation->launch();

            if (!$saveResult->isSuccess()) {
                return array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
            }
        }

        return $jsonResponse;

    }

    /**
     * Получение данных от СМЭВ (внутр.)
     */
    public function getSMEVStatus($companyId = null, $contactId = null): array|string
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        $API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJJZCI6IjIiLCJTZXJ2aWNlIjoiQml0cml4In0.CpUj1LJ_otMm6_slHFRAVnqsQtLeswkSVu7_jIgedTU';
        $idSERequest = '';

        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();

        $headersValues = array(
            "key" => "{$API_KEY}",
            "Content-Type" => "application/json; charset=utf-8",
            "accept" => "application/json"
        );

        $requestJson = $context->getRequest()->getInput();
        $requestMethod = "GET";
        $queryParamsArray = $context->getRequest()->toArray();
        $this->CURLObjectData['METHOD'] = $requestMethod;

        if (str_contains($serverName, 'test')) {
            $apiUrl = "https://api.dev.seller-capital.ru";
        } else {
            $apiUrl = "https://api.seller-capital.ru";
        }

        if (!is_null($companyId)) {
            $this->getCompanyInfoById($companyId);
            $this->setCURLObjectData($companyId);

            $item = $factoryCompany->getItem($companyId);
            $itemData = $item->getData();
            $idSERequest = $itemData['UF_CRM_SMEV_ID_REQUEST'];
        }
        if (!is_null($contactId)) {
            $this->getContactInfoById($contactId);
            $this->setCURLObjectData($contactId);

            $item = $factoryContact->getItem($contactId);
            $itemData = $item->getData();
            $idSERequest = $itemData['UF_CRM_SMEV_ID_REQUEST'];
        }

        $url = $apiUrl . "/GetResponse?id={$idSERequest}";
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Получение данных от СМЭВ: " . $this->itemDatatitle;
        $objectData = $this->CURLObjectData;

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
            true
        );
        //endregion

        $jsonResponse = $HandlerResponse->getResponse($objectData);

        $arResponse = json_decode($jsonResponse['response'], true);
        $services = $arResponse['response']["services"];

        //region Сохраняем данные и проверяем результат
        $saveResult = [];
        if(!is_null($contactId)) $saveResult = $this->saveAllData($factoryContact, $item, $services);
        if(!is_null($companyId)) $saveResult = $this->saveAllData($factoryCompany, $item, $services);

        if ($saveResult['status'] === 'error') {
            // Если произошла ошибка, добавляем комментарий с сообщениями об ошибках
            $message = "Ошибка при сохранении данных: " . implode(', ', $saveResult['messages']);
        }
        else {
            $message = "Данные успешно сохранились";
        }
        //endregion

        return $jsonResponse;
    }

    /**
     *  Поиск карточки (внутр.)
     * @param $dataInn
     * @param $crmId
     * @return EventResult|false|int|mixed
     */
    public function findCard($dataInn, $crmId = false) {
        $cardId = false;
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
        if (!$factoryCompany)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }


        if(!$crmId) {
            $params = [
                'filter' => [
                    'UF_CRM_6433D7C925893' => $dataInn,
                ],
                'select' => ['ID']
            ];
            $itemsCompany = $factoryCompany -> getItems($params);
            //Logs\File ::AddMessage($itemsCompany, "itemsCompany", LOG_API_SYNC_SELLER_CONTROLLER);
            foreach ($itemsCompany as $itemCompany)
            {
                $cardId = $itemCompany->getId();
            }

            return $cardId;
        }
        else {
            $entityTypeId = 128;
            $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
            $itemLK = $factory -> getItem($crmId);
            if($itemLK) {
                $itemLKData = $factory -> getItem($crmId)->getData();
                $cardId = $itemLKData['COMPANY_ID'];

                return $cardId;
            } else {
                $errorMessage = 'Ошибка `crmId` не известен';

                Context::getCurrent()->getResponse()->setStatus(404);
                $this -> addError(new Error($errorMessage, "invalid_request"));
                return new EventResult(EventResult::ERROR, null, null, $this);
            }
        }
    }

    /**
     * Поиск карточки по ГУИД сделки (внутр.)
     * @param $dataInn
     * @param string $dealGUID
     * @return array|EventResult|false|int|void
     */
    public function findCardByDealGUID($dataInn, string $dealGUID = "") {
        $cardId = false;
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
        if (!$factoryCompany)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        if($dealGUID == "") {
            $params = [
                'filter' => [
                    'UF_CRM_6433D7C925893' => $dataInn,
                ],
                'select' => ['ID']
            ];
            $itemsCompany = $factoryCompany -> getItems($params);
            //Logs\File ::AddMessage($itemsCompany, "itemsCompany", LOG_API_SYNC_SELLER_CONTROLLER);
            foreach ($itemsCompany as $itemCompany)
            {
                $cardId = $itemCompany->getId();
            }

            return $cardId;
        }
        else {
            $entityTypeId = \CCrmOwnerType::Deal;
            $factory = \Bitrix\Crm\Service\Container::getInstance() -> getFactory($entityTypeId);
            $params = [
                'filter' => [
                    'UF_CRM_GUID' => $dealGUID,
                ],
                'select' => ['ID','COMPANY_ID']
            ];
            $deals = $factory -> getItems($params);
            if($deals) {
                foreach ($deals as $deal) {
                    $dealData = $deal->getData();
                    $dealId = $dealData['ID'];
                    $companyId = $dealData['COMPANY_ID'];

                    return ['ID' => $dealId, 'COMPANY_ID' => $companyId];
                }
            } else {
                $errorMessage = 'Ошибка `GUID` не известен';

                Context::getCurrent()->getResponse()->setStatus(404);
                $this -> addError(new Error($errorMessage, "invalid_request"));
                return new EventResult(EventResult::ERROR, null, null, $this);
            }
        }
    }

    /**
     * Создание или обновление карточки компании (внутр.)
     * @param $sellerCardId
     * @param $dataArray
     * @param $createCard
     * @param $currentCardId
     * @param string $type
     * @param $crmId
     * @return EventResult|int
     * @throws ArgumentException
     */
    private function createOrUpdateCard($sellerCardId, $dataArray, $createCard, $currentCardId, string $type = "", $crmId = null): int|EventResult
    {
        $entityTypeIdCompany = \CCrmOwnerType::Company;
        $entityTypeIdLK = 128;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
        $factoryLK = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLK);
        if (!$factoryCompany)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        //region Обновление Карточки
        if(!$createCard && !is_null($currentCardId)) {
            $itemSeller = $factoryCompany -> getItem($sellerCardId);
            if($crmId) $itemLK = $factoryLK->getItem($crmId);
            Logs\File ::AddMessage($currentCardId, "currentCardId", LOG_API_SYNC_SELLER_CONTROLLER);
            $item = $factoryCompany -> getItem($currentCardId);

            //region "Тип клиента (Организационно-правовая форма)"
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()){
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => $dataArray['type']]);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }

            $item->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
            //endregion

            $item->set("UF_CRM_COMPANY_SS_ORG", [5]); //Организация
            $item->set("UF_CRM_6433DBB98DD53", 17611); //Филиал

            if (!empty($dataArray['phone'])) {
                $arPhone = array(
                    'ENTITY_ID' => 'COMPANY',   // Тип сущности - COMPANY
                    'ELEMENT_ID' => $currentCardId,   // ID Контакта
                    'TYPE_ID' => 'PHONE',
                    'VALUE_TYPE' => 'WORK',
                    'VALUE' => $dataArray['phone']      // Телефон
                );

                $multi = new \CCrmFieldMulti();
                $multi->Add($arPhone);
            }
            if (!empty($dataArray['email'])) {
                $arEmail = array(
                    'ENTITY_ID' => 'COMPANY',   // Тип сущности - COMPANY
                    'ELEMENT_ID' => $currentCardId,   // ID Контакта
                    'TYPE_ID' => 'EMAIL',
                    'VALUE_TYPE' => 'WORK',
                    'VALUE' => $dataArray['email']      // Email
                );
                $multi = new \CCrmFieldMulti();
                $multi->Add($arEmail);
            }

            //region "Сервис ЭДО"
            $serviceEDO = $dataArray['serviceEDO'];
            $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "Edo_".$serviceEDO,
            ));
            if ($arEnumEDO = $rsEnumEDO -> Fetch())
            {
                $serviceEDOId = $arEnumEDO['ID'];
            }
            Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
            $item->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
            //endregion

            //region "Ссылки на маркетплейсы"
            $marketplaceLinks = $dataArray['marketplaceLinks'];
            $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
            //endregion

            //region "id синхронизации с SE"
            $syncId = $dataArray['synchId'];
            $item -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
            //endregion

            //region "Изменено ЛК"
            $item->set("UF_CRM_UPDATE_INFO_LK", true);
            //endregion

            //region "Ручное заполнение паспорта"
            $isManual = $dataArray['isManual'];
            $item->set("UF_CRM_PASSPORT_IS_MANUAL", $isManual);
            //endregion

            //region "Устав компании SC"
            $dataCompanyCharterFile = $dataArray['charterFile'];
            if($dataCompanyCharterFile !== NULL) {
                $arFile = array();
                $fileName = floor(microtime(true) * 1000)."_".$dataCompanyCharterFile["fileName"];
                $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                file_put_contents($filePathName, base64_decode ($dataCompanyCharterFile["file"]));//Запись на системный диск
                $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                if ($fileId) {
                    $fileArray = \CFile::MakeFileArray($fileId);
                    array_push($arFile, $fileArray);
                } else {
                    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
                $fields = [
                    'UF_CRM_COMPANY_CHARTER' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            } else {
                Logs\File::AddMessage('Пустой массив dataCompanyCharterFile', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
            //endregion

            //region "Приказ на директора SC"
            $dataOrderDirectorFile = $dataArray['orderDirector'];
            if($dataOrderDirectorFile !== NULL) {
                $arFile = array();
                $fileName = floor(microtime(true) * 1000)."_".$dataOrderDirectorFile["fileName"];
                $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                file_put_contents($filePathName, base64_decode ($dataOrderDirectorFile["file"]));//Запись на системный диск
                $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                if ($fileId) {
                    $fileArray = \CFile::MakeFileArray($fileId);
                    array_push($arFile, $fileArray);
                } else {
                    Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
                $fields = [
                    'UF_CRM_ORDER_FOR_DIRECTOR' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            }
            else {
                Logs\File::AddMessage('Пустой массив dataOrderForDirectorArray', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
            //endregion

            //region "Паспорт, СНИЛС заемщика"
            $dataPassportArray = $dataArray['passport'];
            if($dataPassportArray !== NULL) {
                $dataPassportFiles = $dataPassportArray['files'];
                if(!empty($dataPassportFiles)) {
                    $arFile = array();
                    foreach ($dataPassportFiles as $file) {
                        $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arFile, $fileArray);
                        } else {
                            Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                        }
                    }
                    $fields = [
                        'UF_CRM_6433D94467769' => $arFile,
                    ];
                    $item->setFromCompatibleData($fields);
                } else {
                    Logs\File::AddMessage('Пустой массив dataPassportFiles', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                }
            }
            //endregion

            $operation = $factoryCompany->getUpdateOperation($item);
            $operation->disableAllChecks();
            $operation->launch();

            $itemId = $item->getId();

            //region Обновление Карточки бенефициаров
            if($type == "beneficiar")
            {
                //region "Бенефициар X" в Карточке Селлера
                $_beneficiars = [
                    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
                    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
                    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
                    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
                    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
                ];

                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_beneficiars)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_beneficiars as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemSeller->set($key, $currentCardId);
                            $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
                //endregion
            }
            //endregion

            //region Обновление Карточки руководителя
            if($type == "director") {
                //region "Руководитель (представитель)" в Карточке Селлера
                $itemSeller->set("UF_CRM_1615200179", "CO_".$currentCardId);
                //endregion

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion


            //region Обновление Карточки Поручителя
            if($type == "guarantor") {
                //region "Поручитель X" в Карточке ЛК
                if($crmId)
                {
                    $_guarantors = [
                        'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
                        'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
                        'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
                        'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
                        'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
                    ];
                    $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                    // Проверка на наличие ID в массиве
                    if (!in_array($currentCardId, $_guarantors))
                    {
                        // ID не найден, ищем первое свободное поле
                        foreach ($_guarantors as $key => $value)
                        {
                            if (empty($value))
                            {
                                // Нашли свободное поле, записываем туда ID
                                $itemLK -> set($key, $currentCardId);
                                break; // Выходим из цикла, так как запись произведена
                            }
                        }
                    }
                }
                //endregion
            }
            //endregion
        }
        //endregion

        //region Создание Карточки
        elseif(is_null($currentCardId)) {
            $newItem = $factoryCompany->createItem();
            $itemSeller = $factoryCompany->getItem($sellerCardId);
            if($crmId) $itemLK = $factoryLK->getItem($crmId);

            //region "Тип клиента (Организационно-правовая форма)"
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()){
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => $dataArray['type']]);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }
            $newItem->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
            //endregion

            $newItem->set("UF_CRM_COMPANY_SS_ORG", [5]); //Организация
            $newItem->set("UF_CRM_6433DBB98DD53", 17611); //Филиал

            //region "Сервис ЭДО"
            $serviceEDO = $dataArray['serviceEDO'];
            $rsEnumEDO = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "Edo_".$serviceEDO,
            ));
            if ($arEnumEDO = $rsEnumEDO -> Fetch())
            {
                $serviceEDOId = $arEnumEDO['ID'];
            }
            Logs\File ::AddMessage($serviceEDOId, "serviceEDOId for {$currentCardId}", LOG_API_SYNC_SELLER_CONTROLLER);
            $newItem->set("UF_CRM_COMPANY_SERVICE_EDO", $serviceEDOId); //выбранный сервис ЭДО
            //endregion

            //region "Ссылки на маркетплейсы"
            $marketplaceLinks = $dataArray['marketplaceLinks'];
            $newItem -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
            //endregion

            //region "id синхронизации с SE"
            $syncId = $dataArray['synchId'];
            $newItem -> set("UF_CRM_COMPANY_SYNC_SE_ID", $syncId); //id синхронизации с SE
            //endregion

            //region "Изменено ЛК"
            $newItem->set("UF_CRM_UPDATE_INFO_LK", true);
            //endregion

            //region "Ручное заполнение паспорта"
            $isManual = $dataArray['isManual'];
            $newItem->set("UF_CRM_PASSPORT_IS_MANUAL", $isManual);
            //endregion

            //region "Паспорт, СНИЛС заемщика"
            $dataPassportArray = $dataArray['passport'];
            if($dataPassportArray !== NULL) {
                $dataPassportFiles = $dataPassportArray['files'];
                if(!empty($dataPassportFiles)) {
                    $arFile = array();
                    foreach ($dataPassportFiles as $file) {
                        $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                        $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                        file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                        $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                        $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                        if ($fileId) {
                            $fileArray = \CFile::MakeFileArray($fileId);
                            array_push($arFile, $fileArray);
                        } else {
                            Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                        }
                    }
                    $fields = [
                        'UF_CRM_6433D94467769' => $arFile,
                    ];
                    $newItem->setFromCompatibleData($fields);
                }
            }
            //endregion

            //region "ИНН (SCP)"
            $newItem->set("UF_CRM_6433D7C925893", $dataArray['inn']);
            //endregion

            //region Создание Карточки руководителя
            if ($type == "director") {

                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion

            //region Создание Карточки бенефициаров
            if ($type == "beneficiar") {

                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);

                //region "Бенефициар X" в Карточке Селлера
                $_beneficiars = [
                    'UF_CRM_1702272911' => $itemSeller -> getData()['UF_CRM_1702272911'],
                    'UF_CRM_1702272991' => $itemSeller -> getData()['UF_CRM_1702272991'],
                    'UF_CRM_1702273016' => $itemSeller -> getData()['UF_CRM_1702273016'],
                    'UF_CRM_1702273043' => $itemSeller -> getData()['UF_CRM_1702273043'],
                    'UF_CRM_1702273072' => $itemSeller -> getData()['UF_CRM_1702273072'],
                ];

                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_beneficiars)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_beneficiars as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemSeller->set($key, $currentCardId);
                            $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
                //endregion

                //region "Изменено ЛК" в Карточке Селлера
                $itemSeller->set("UF_CRM_UPDATE_INFO_LK", true); //изменено ЛК
                //endregion
            }
            //endregion

            //region Создание Карточки Поручителя
            if($type == "guarantor") {
                $fullName = $dataArray['lastName'] . " " .$dataArray['firstName']. " " . $dataArray['secondName'];
                $newItem->setTitle($fullName);

                //region "Поручитель X" в Карточке ЛК
                $_guarantors = [
                    'UF_CRM_GUARANTOR_1' => $itemLK -> getData()['UF_CRM_GUARANTOR_1'],
                    'UF_CRM_GUARANTOR_2' => $itemLK -> getData()['UF_CRM_GUARANTOR_2'],
                    'UF_CRM_GUARANTOR_3' => $itemLK -> getData()['UF_CRM_GUARANTOR_3'],
                    'UF_CRM_GUARANTOR_4' => $itemLK -> getData()['UF_CRM_GUARANTOR_4'],
                    'UF_CRM_GUARANTOR_5' => $itemLK -> getData()['UF_CRM_GUARANTOR_5'],
                ];
                //endregion
                $currentCardId = "CO_" . $currentCardId; // Префиксируем ID

                // Проверка на наличие ID в массиве
                if (!in_array($currentCardId, $_guarantors)) {
                    // ID не найден, ищем первое свободное поле
                    foreach ($_guarantors as $key => $value) {
                        if (empty($value)) {
                            // Нашли свободное поле, записываем туда ID
                            $itemLK->set($key, $currentCardId);
                            break; // Выходим из цикла, так как запись произведена
                        }
                    }
                }
            }
            //endregion

            $operation = $factoryCompany->getAddOperation($newItem);
            $operation->disableAllChecks();
            $operation->launch();
            $itemId = $newItem->getId();

        }
        //endregion

        $operationOnlySeller = $factoryCompany->getUpdateOperation($itemSeller);
        $operationOnlySeller->disableAllChecks();
        $operationOnlySeller->launch();

        Logs\File ::AddMessage($itemId, "Получение ID карточки компании",LOG_API_SYNC_SELLER_CONTROLLER);

        return $itemId;
    }

    /**
     * Обновление карточки сделки (внутр.)
     * @param $dealId
     * @return EventResult|int|null
     */
    private function updateDealCard($dealId){
        $entityTypeId = \CCrmOwnerType::Deal;
        $factoryDeal = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factoryDeal)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
        $item = $factoryDeal->getItem($dealId);

        if($item) $item->setStageId('C23:UC_K2J0ML');

        $operation = $factoryDeal->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operationResult = $operation->launch();

        if (!$operationResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $operationResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item->getId(),
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b]{$message}[/b]"
                ]
            ]);
            return null;
        }

        return $item->getId();
    }

    /**
     * Обновление карточки компании (внутр.)
     * @param $companyId
     * @param $dataArray
     * @return EventResult|int|null
     * @throws ArgumentException
     */
    private function updateCompanyCard($companyId, $dataArray){
        $entityTypeId = \CCrmOwnerType::Company;
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factoryCompany)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        $isAcceptPersonalInfo = $dataArray['isAcceptPersonalInfo'];
        $isAcceptPEPInfo = $dataArray['isAcceptPEPInfo'];
        $_type = $dataArray['type'];
        $marketplaceLinks = $dataArray['marketplaceLinks'];

        $item = $factoryCompany -> getItem($companyId);

        //region "Тип клиента (Организационно-правовая форма)"
        if($_type == "IP") {
            $rsEnumType = \CUserFieldEnum::GetList(array(), array(
                "XML_ID" => "IP",
            ));
        }
        elseif($_type == "UL") {
            $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
                "XML_ID" => "ORG",
            ));
        }
        elseif($_type == "FL") {
            $rsEnumType = \CUserFieldEnum ::GetList(array(), array(
                "XML_ID" => "FL",
            ));
        }

        if ($arEnumType = $rsEnumType -> Fetch())
        {
            $TypeId = $arEnumType['ID'];
        }
        $item->set("UF_CRM_1684145100226", $TypeId); //Правовая форма
        //endregion

        //region "Ссылки на маркетплейсы"
        $item -> set("UF_CRM_COMPANY_LINKS_TO_MARKETPLACES", $marketplaceLinks); //ссылки на маркетплейсы
        //endregion

        //region Согласия
        $item->set("UF_CRM_ACCEPT_PERSONAL_INFO", $isAcceptPersonalInfo); //согласие человека на обработку перс данных
        $item->set("UF_CRM_1726058034", $isAcceptPEPInfo); //согласие человека на подписание ПЭП
        //endregion

        //region "Паспорт, СНИЛС заемщика"
        $dataPassportArray = $dataArray['passport'];
        if($dataPassportArray !== NULL) {
            $dataPassportFiles = $dataPassportArray['files'];
            if(!empty($dataPassportFiles)) {
                $arFile = array();
                foreach ($dataPassportFiles as $file) {
                    $fileName = floor(microtime(true) * 1000)."_".$file["fileName"];
                    $filePathName = $_SERVER["DOCUMENT_ROOT"]."/".\COption::GetOptionString("main", "upload_dir")."/services_sodeistvie/temp/".$fileName;
                    file_put_contents($filePathName, base64_decode ($file["file"]));//Запись на системный диск
                    $file = \CFile::MakeFileArray($filePathName);//сформировали массив
                    $fileId = \CFile::SaveFile($file,'');//Запись диск Битрикс
                    if ($fileId) {
                        $fileArray = \CFile::MakeFileArray($fileId);
                        array_push($arFile, $fileArray);
                    } else {
                        Logs\File::AddMessage('Failed to save file', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
                    }
                }
                $fields = [
                    'UF_CRM_6433D94467769' => $arFile,
                ];
                $item->setFromCompatibleData($fields);
            } else {
                Logs\File::AddMessage('Пустой массив dataPassportFiles', 'Error', LOG_API_SYNC_SELLER_CONTROLLER);
            }
        }
        //endregion

        $operation = $factoryCompany->getUpdateOperation($item);
        $operation->disableAllChecks();
        $operationResult = $operation->launch();

        if (!$operationResult->isSuccess()) {
            $message = "Ошибка при обновлении компании: " . implode(", ", $operationResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $item->getId(),
                    "ENTITY_TYPE" => "COMPANY",
                    "COMMENT" => "[b]{$message}[/b]"
                ]
            ]);
            return null;
        }

        return $item->getId();
    }

    /**
     * Получение информации о компании по ИД (внутр.)
     * @param $companyId
     * @return $this
     * @throws SqlQueryException
     */
    public function getCompanyInfoById($companyId): static {
        $this->companyId = $companyId;
        $this->entityTypeId = \CCrmOwnerType::Company;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->companyId);
        if($item) {
            $this->itemDatatitle = $item->getData()['TITLE'];
            $this->rqId = $this->findRequisite(\CCrmOwnerType::Company, $companyId);
            global $DB;
            $RQItemSQL = "SELECT * FROM b_crm_requisite INNER JOIN b_uts_crm_requisite ON b_crm_requisite.ID = b_uts_crm_requisite.VALUE_ID WHERE ENTITY_ID='{$companyId}' AND ID='" . $this->rqId . "' ORDER BY ID ASC;";
            $resRQItemsQuery = $DB->query($RQItemSQL);
            while($resRQItem = $resRQItemsQuery->Fetch()) {
                $this->sellerInn = (string) $resRQItem['RQ_INN'];
                $this->sellerLastName = (string) $resRQItem['RQ_LAST_NAME'];
                $this->sellerFirstName = (string) $resRQItem['RQ_FIRST_NAME'];
                $this->sellerSecondName = (string) $resRQItem['RQ_SECOND_NAME'];
                $this->sellerPassportBirthday = (string) date('Y-m-d', strtotime($resRQItem['UF_CRM_1684493639']));
                $this->sellerPassportNumber = (string) $resRQItem['RQ_IDENT_DOC_NUM'];
                $this->sellerPassportSeries = (string) $resRQItem['RQ_IDENT_DOC_SER'];
            }
        }
        return $this;
    }

    /**
     * Получение информации о контакте по ИД (внутр.)
     * @param $contactId
     * @return $this
     * @throws SqlQueryException
     */
    public function getContactInfoById($contactId): static {
        $this->contactId = $contactId;
        $this->entityTypeId = \CCrmOwnerType::Contact;

        $factory = \Bitrix\Crm\Service\Container ::getInstance() -> getFactory($this->entityTypeId);
        $item = $factory -> getItem($this->contactId);
        if($item) {
            $this->rqId = $this->findRequisite(\CCrmOwnerType::Contact, $contactId);
            global $DB;
            $RQItemSQL = "SELECT * FROM b_crm_requisite INNER JOIN b_uts_crm_requisite ON b_crm_requisite.ID = b_uts_crm_requisite.VALUE_ID WHERE ENTITY_ID='{$contactId}' AND ID='" . $this->rqId . "' ORDER BY ID ASC;";
            $resRQItemsQuery = $DB->query($RQItemSQL);
            while($resRQItem = $resRQItemsQuery->Fetch()) {
                $this->sellerInn = (string) $resRQItem['RQ_INN'];
                $this->sellerLastName = (string) $resRQItem['RQ_LAST_NAME'];
                $this->sellerFirstName = (string) $resRQItem['RQ_FIRST_NAME'];
                $this->sellerSecondName = (string) $resRQItem['RQ_SECOND_NAME'];
                $this->sellerPassportBirthday = (string) date('Y-m-d', strtotime($resRQItem['UF_CRM_1684493639']));
                $this->sellerPassportNumber = (string) $resRQItem['RQ_IDENT_DOC_NUM'];
                $this->sellerPassportSeries = (string) $resRQItem['RQ_IDENT_DOC_SER'];

            }

            $this->itemDatatitle = $this->sellerLastName . " " . $this->sellerFirstName . " " . $this->sellerSecondName;
        }
        return $this;
    }

    /**
     * Установка ObjectData элемента (внутр.)
     * @param $itemId
     * @return mixed
     */
    public function setCURLObjectData($itemId): mixed {
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $this->CURLObjectData['ITEM_ID'] = $itemId;
        $this->CURLObjectData['ITEM_TYPE_ID'] = $this->entityTypeId;
        $this->CURLObjectData['ITEM_TITLE'] = "SE: ". $this->itemDatatitle;

        if (strpos($serverName, 'test') !== false) {
            $this->CURLObjectData['INIT_OBJECT_URL'] = "https://testcrm.seller-capital.ru/crm/type/{$this->entityTypeId}/details/{$itemId}/";
        } else {
            $this->CURLObjectData['INIT_OBJECT_URL'] = "https://crm.seller-capital.ru/crm/type/{$this->entityTypeId}/details/{$itemId}/";
        }

        return $this->CURLObjectData;
    }

    /**
     * Поиск реквизитов (внутр.)
     * @param $entityTypeId
     * @param $cardId
     * @param $inn
     * @return mixed|null
     */
    private function findRequisite($entityTypeId, $cardId, $inn = null): mixed
    {
        $filter = ["ENTITY_TYPE_ID" => $entityTypeId, "ENTITY_ID" => $cardId];
        $filter["RQ_INN"] = $inn;

        $requisiteList = \CRest::call("crm.requisite.list", [
            "filter" => $filter,
            "select" => ['ID', "PRESET_ID", "ENTITY_ID", "ENTITY_TYPE_ID"]
        ]);
        Logs\File::AddMessage($requisiteList, "requisiteList Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        $requisite = $requisiteList['result'];

        return $requisite ? $requisite[0]['ID'] : null;
    }

    /**
     * Поиск реквизитов компании по ИНН (внутр.)
     * @param $cardId
     * @param false|null $inn
     * @return mixed|null
     */
    private function findCompanyRQ($cardId, $inn = null): mixed {
        if(!is_null($inn)) {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "ENTITY_ID" => $cardId],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "First requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        if(isset($requisite)) {
            return $requisite[0]['ID'];
        } else {
            return null;
        }


    }

    /**
     * Поиск реквизитов контакта по ИНН (внутр.)
     * @param $cardId
     * @param $inn
     * @return mixed|null
     */
    private function findContactRQ($cardId, $inn = null): mixed {
        if(!is_null($inn)) {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Contact, "ENTITY_ID" => $cardId, "RQ_INN" => $inn],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {
            $requisite = \CRest::call(
                "crm.requisite.list",
                array(
                    "filter" => ["ENTITY_TYPE_ID" => \CCrmOwnerType::Contact, "ENTITY_ID" => $cardId],
                    "select" => ['ID',"PRESET_ID", "ENTITY_ID",	"ENTITY_TYPE_ID"]
                )
            )['result'];

            Logs\File ::AddMessage($requisite, "First requisite Find for {$cardId}", LOG_API_SYNC_SELLER_CONTROLLER);

        }
        if(isset($requisite)) {
            return $requisite[0]['ID'];
        } else {
            return null;
        }
    }

    /**
     * Создание или обновление реквизитов (внутр.)
     * @param $cardId
     * @param $dataArray
     * @param $createRQ
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    private function createOrUpdateRQ($cardId, $dataArray, $createRQ = false): void
    {
        $type = $dataArray['type'];
        $inn = $dataArray['inn'];
        $kpp = $dataArray['kpp'];
        $ogrnip = $dataArray['ogrnip'];
        $ogrn = $dataArray['ogrn'];
        $okpo = $dataArray['okpo'];
        $okved = $dataArray['okved'];
        $companyRegDate = $dataArray['companyRegDate'];
        $companyName = $dataArray['companyName'];
        $companyFullName = $dataArray['companyFullName'];
        $fnsDepartment = $dataArray['fnsDepartment'];
        $firstName = $dataArray['firstName'];
        $lastName = $dataArray['lastName'];
        $secondName = $dataArray['secondName'];
        $birthday = $dataArray['birthday'];
        $birthPlace = $dataArray['birthPlace'];
        $serviceEDO = $dataArray['serviceEDO'];

        $passportArray = $dataArray['passport'];
        $passportIssuer = $passportArray['issuer'];
        $passportNumber = $passportArray['number'];
        $passportSeries = $passportArray['series'];
        $passportIssuedAt = $passportArray['issuedAt'];
        $passportIssuerCode = $passportArray['issuerCode'];

        $rqId = $this->findCompanyRQ($cardId, $inn);
        Logs\File ::AddMessage($rqId, "rqId {$cardId} Update", LOG_API_SYNC_SELLER_CONTROLLER);

        if(isset($rqId)) {

            if($type === "IP" || $type === "FL")
            {
                $params = [
                    "id" => $rqId,
                    "fields" => [
                        'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
                        'NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_FIRST_NAME' => $firstName,
                        'RQ_LAST_NAME' => $lastName,
                        'RQ_SECOND_NAME' => $secondName,
                        'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                        'RQ_IDENT_DOC_SER' => $passportSeries,
                        'RQ_IDENT_DOC_NUM' => $passportNumber,
                        'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
                        'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
                        'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
                        'UF_CRM_1647929611' => $birthPlace,
                        'UF_CRM_1684493639' => $birthday,
                        'RQ_INN' => $inn,
                        'RQ_OGRNIP' => $ogrnip,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                    ]
                ];
            }
            if($type === "UL")
            {
                $params = [
                    "id" => $rqId,
                    "fields" => [
                        'TITLE' => $companyName,
                        'NAME' => $companyName,
                        'RQ_INN' => $inn,
                        'RQ_KPP' => $kpp,
                        'RQ_OGRN' => $ogrn,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                        'RQ_COMPANY_NAME' => $companyName,
                        'RQ_COMPANY_FULL_NAME' => $companyFullName,
                    ]
                ];
            }

            \CRest ::call('crm.requisite.update', $params);



            $_requisite = \CRest::call(
                "crm.requisite.get",
                array("id" => $rqId)
            )['result'];

            Logs\File ::AddMessage($_requisite, "requisite Info After Update for {$cardId}",
                LOG_API_SYNC_SELLER_CONTROLLER);

        }
        else {

            if($type === "IP") $PRESET_ID = 2;
            if($type === "FL") $PRESET_ID = 2;
            if($type === "UL") $PRESET_ID = 1;

            if($type === "IP" || $type === "FL")
            {
                $params = [
                    "fields" => [
                        "ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
                        "ENTITY_ID" => $cardId,
                        "PRESET_ID" => $PRESET_ID,
                        'TITLE' => $type . " " . $lastName . " " . $firstName . " " . $secondName,
                        'NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_NAME' => $lastName . " " . $firstName . " " . $secondName,
                        'RQ_FIRST_NAME' => $firstName,
                        'RQ_LAST_NAME' => $lastName,
                        'RQ_SECOND_NAME' => $secondName,
                        'RQ_IDENT_DOC' => 'Паспорт гражданина Российской Федерации',
                        'RQ_IDENT_DOC_SER' => $passportSeries,
                        'RQ_IDENT_DOC_NUM' => $passportNumber,
                        'RQ_IDENT_DOC_DATE' => (string) date('d.m.Y',strtotime($passportIssuedAt)),
                        'RQ_IDENT_DOC_ISSUED_BY' => $passportIssuer,
                        'RQ_IDENT_DOC_DEP_CODE' => $passportIssuerCode,
                        'UF_CRM_1647929611' => $birthPlace,
                        'UF_CRM_1684493639' => $birthday,
                        'RQ_INN' => $inn,
                        'RQ_OGRNIP' => $ogrnip,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                    ]
                ];
                $rqId = \CRest ::call('crm.requisite.add', $params)['data'];
            }
            if($type === "UL")
            {
                $params = [
                    "fields" => [
                        "ENTITY_TYPE_ID" =>\CCrmOwnerType::Company,
                        "ENTITY_ID" => $cardId,
                        "PRESET_ID" => $PRESET_ID,
                        'TITLE' => $companyName,
                        'NAME' => $companyName,
                        'RQ_INN' => $inn,
                        'RQ_KPP' => $kpp,
                        'RQ_OGRN' => $ogrn,
                        'RQ_OKPO' => $okpo,
                        'RQ_OKVED' => $okved,
                        'RQ_COMPANY_REG_DATE' => (string) date('d.m.Y',strtotime($companyRegDate)),
                        'UF_CRM_1688964741' => $fnsDepartment,
                        'RQ_COMPANY_NAME' => $companyName,
                        'RQ_COMPANY_FULL_NAME' => $companyFullName,
                    ]
                ];
                $rqId = \CRest ::call('crm.requisite.add', $params)['data'];
            }

            Logs\File ::AddMessage($rqId, "rqId {$cardId}  Create", LOG_API_SYNC_SELLER_CONTROLLER);
        }

        if(isset($rqId))
        {
            $addressArray = $dataArray['address'];
            foreach ($addressArray as $address)
            {
                $addressFiasId = $address['fiasId'];

                $http = new HttpClient();
                $http->setHeader('Content-Type', 'application/json');
                $http->setHeader('Accept', 'application/json');
                $http->setHeader('Authorization', 'Token 440b60bed73f6e0d78a0eb09ca91971f8c079590');
                $requestBody = [
                    'query' => $addressFiasId
                ];
                $http->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address", json_encode($requestBody));

                $responseJson = $http->getResult();
                $responseArray = json_decode($responseJson, true);
                $addressData = $responseArray['suggestions'][0]['data'];

                if ($address['type'] == "registration") $addressTypeId = 4;
                if ($address['type'] == "actual") $addressTypeId = 1;
                if ($address['type'] == "legal") $addressTypeId = 6;
                $addressCity = $addressData['city'];
                $addressFlat = $addressData['flat'];
                $addressHouse = $addressData['house'];
                $addressRegion = $addressData['region'];
                $addressDistrict = $addressData['city_district'];
                $addressStreet = $addressData['street'];
                $addressBuilding = $addressData['block'];
                $addressStructure = $addressData['block'];
                $addressCountry = $addressData['country'];
                $addressPostalCode = $addressData['postalCode'];

                //код добавления данного типа адреса в реквизит карточки клиента
                $arAddress['ENTITY_ID'] = intval($rqId);//id requisite
                $arAddress['TYPE_ID'] = $addressTypeId;//Адрес регистрации
                $arAddress['ENTITY_TYPE_ID'] = \CCrmOwnerType::Requisite;//Реквизит 8
                $arAddress['ANCHOR_ID'] = $cardId;// ID Компании
                $arAddress['POSTAL_CODE'] = $addressPostalCode;// Индекс
                $arAddress['COUNTRY'] = $addressCountry;// Страна
                $arAddress['PROVINCE'] = $addressRegion;// регион
                $arAddress['REGION'] = $addressDistrict;// Район
                $arAddress['CITY'] = $addressCity;// Город
                $arAddress['ADDRESS_1'] = $addressStreet . ", " . $addressHouse . ", " . $addressStructure . ", " . $addressBuilding;
                //Улица, дом, корпус, строение.
                $arAddress['ADDRESS_2'] = $addressFlat;// Квартира / офис.
                $arAddress['COUNTRY_CODE'] = 643;// Код страны
                $resultAddress = \CRest ::call('crm.address.add', ['fields' => $arAddress]);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressStreet, 'STREET', $addressTypeId,$addressFiasId);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressHouse, 'BUILDING', $addressTypeId,$addressFiasId);
                self ::addressUpdate($cardId, \CCrmOwnerType::Company, $addressFiasId, 'FIAS_ID', $addressTypeId, $addressFiasId);

                Logs\File ::AddMessage($arAddress, "arAddress " . $address['type'], LOG_API_SYNC_SELLER_CONTROLLER);
            }
        }
    }

    /**
     * Обновление адреса (внутр.)
     * @param $id
     * @param $entityTypeId
     * @param $dataField
     * @param $nameField
     * @param $typeId
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SqlQueryException
     * @throws SystemException
     */
    private function addressUpdate($id, $entityTypeId, $dataField, $nameField, $typeId, $fiasId = null): void
    {
        global $DB;
        $Address = new \Bitrix\Location\Controller\Address;


        $resAddrList = \CRest::call('crm.address.list', array(
            'filter' => array('ANCHOR_ID' => $id, 'ANCHOR_TYPE_ID' => $entityTypeId),
            'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','ANCHOR_TYPE_ID','LOC_ADDR_ID')
        ))['result'];

        if(empty($resAddrList)) {
            return;
        }

        foreach($resAddrList as $i => $addrItem){
            if($addrItem['TYPE_ID'] == $typeId)
            {
                $LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];

                $beforeStrSQL = "SELECT * FROM b_location_addr_fld WHERE ADDRESS_ID = ".$LOC_ADDR_ID;
                $beforeResults = $DB->Query($beforeStrSQL);

                if($dataField !== "" && $nameField == "STREET")
                {
                    $streetBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 340)
                                $streetBool = true;
                        }

                    }

                    $streetTMP = str_replace(" ", "", $dataField);
                    $streetTMP = str_replace(".", "", $streetTMP);
                    $streetTMP = str_replace(",", "", $streetTMP);
                    $streetUPPER = strtoupper($streetTMP);
                    $fiasIdUPPER = strtoupper($fiasId);


                    if(!$streetBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.",340,'".$dataField."','".$streetUPPER."')";
                        //AddMessage2Log($strSQL, 'SQL STREET');

                    } else
                    {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 340";
                        //AddMessage2Log($strSQL, 'SQL STREET UPDATE');
                    }
                    $DB->Query($strSQL);



                }

                if($dataField !== "" && $nameField == "BUILDING")
                {
                    $houseBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 400)
                                $houseBool = true;
                        }

                    }
                    //$house = $dataField.' д.';
                    $houseTMP = str_replace(" ", "", $dataField);
                    $houseTMP = str_replace(".", "", $houseTMP);
                    $houseTMP = str_replace(",", "", $houseTMP);
                    $houseUPPER = strtoupper($houseTMP);

                    if(!$houseBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (" . $LOC_ADDR_ID . ",400,'" . $dataField . "','" . $houseUPPER . "')";
                        //AddMessage2Log($strSQL, 'SQL BUILDING');
                    } else {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 400";
                        //AddMessage2Log($strSQL, 'SQL BUILDING UPDATE');
                    }

                    $DB->Query($strSQL);
                }

                if($dataField !== "" && $nameField == "FIAS_ID")
                {
                    $fiasIdBool = false;
                    if (intval($beforeResults->SelectedRowsCount())>0)
                    {
                        while ($location_addr_fld = $beforeResults->Fetch()){
                            if($location_addr_fld['TYPE'] == 900)
                                $fiasIdBool = true;
                        }

                    }

                    $fiasIdUPPER = strtoupper($fiasId);
                    if(!$fiasIdBool)
                    {
                        $strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.", 900, '".$fiasId."', '".$fiasIdUPPER."')";
                        //AddMessage2Log($strSQL, 'SQL STREET');

                    } else
                    {
                        $strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$fiasId."', VALUE_NORMALIZED = '".$fiasIdUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 900";
                        //AddMessage2Log($strSQL, 'SQL STREET UPDATE');
                    }
                    $DB->Query($strSQL);
                }


                //AddMessage2Log($strSQL, 'SQLALL');
            }

            //$DB->Query("INSERT INTO b_location_addr_fld (ADDRESS_ID, TYPE, VALUE, VALUE_NORMALIZED) VALUES ({$LOC_ADDR_ID},400,'{$house}','{$houseUPPER}')");

            $addrId = $Address->findById($addrItem['LOC_ADDR_ID']);
            $resAddress[$i] = $addrId['fieldCollection'];

            if(!empty($resAddress[$i][340]) && !empty($resAddress[$i][400])) {
                \CRest::call('crm.address.update',	array(
                    'fields' => array(
                        'TYPE_ID' => $addrItem['TYPE_ID'],
                        'ENTITY_TYPE_ID' => $addrItem['ENTITY_TYPE_ID'],
                        'ENTITY_ID' => $addrItem['ENTITY_ID'],
                        'LOC_ADDR_ID' => $addrItem['LOC_ADDR_ID'],
                        'POSTAL_CODE' => $resAddress[$i][50],//Почтовый индекс
                        'COUNTRY' => $resAddress[$i][100],//Страна
                        'PROVINCE' => $resAddress[$i][200],//Регион
                        'REGION' => $resAddress[$i][210],//Район
                        'CITY' => $resAddress[$i][300],//Город+Населенный пункт
                        'STREET' => $resAddress[$i][340],//Улица
                        'BUILDING' => $resAddress[$i][400],//Номер дома
                        'ADDRESS_1' => $resAddress[$i][340].', '.$resAddress[$i][400],
                        'ADDRESS_2' => $resAddress[$i][600]
                    )
                ));
            }
        }

    }

    /**
     * Сохранение данных (внутр.)
     * @param $factory
     * @param $item
     * @param $services
     * @return array
     */
    private function saveAllData($factory, $item, $services): array
    {
        $crmUpdateResult = $this->crmUpdate($factory, $item, $services);

        Logs\File ::AddMessage($crmUpdateResult, "crmUpdateResult", LOG_API_SYNC_SELLER_CONTROLLER);
        if ($crmUpdateResult !== true) {  // Если вернулся массив ошибок
            return [
                'status' => 'error',
                'messages' => $crmUpdateResult,
            ];
        }
        return [
            'status' => 'success',
            'messages' => ['Все данные успешно сохранены.'],
        ];
    }

    /**
     * Обновление CRM данными от служб СМЭВ (внутр.)
     * @param $factory
     * @param $item
     * @param $services
     * @return true|array
     */
    private function crmUpdate($factory, $item, $services): true|array
    {
        $errors = [];

        // Проверяем и устанавливаем нужные поля на основании данных из $requestArray['response']['services']
        if (isset($services) && is_array($services)) {
            foreach ($services as $serviceData) {
                $serviceName = $serviceData['service'];
                $result = (isset($serviceData['result']['valid']) && $serviceData['result']['valid'] === true) ? '1' : '0';
                $description = $serviceData['result']['description'] ?? null;

                Logs\File ::AddMessage($result, "result", LOG_API_SYNC_SELLER_CONTROLLER);

                // Устанавливаем поля для компании или контакта в зависимости от `service`
                if ($serviceName === "fns") {
                    $item->set('UF_CRM_PFR_VALIDITY_OF_PASSPORT', (string) $result);
                    $item->set('UF_CRM_PFR_DECODING_PASSPORT_CHECK', $description);
                } elseif ($serviceName === "mvd") {
                    $item->set('UF_CRM_MVD_VALIDITY_OF_PASSPORT', (string) $result);
                    $item->set('UF_CRM_MVD_DECODING_PASSPORT_CHECK', $description);
                }
            }
        } else {
            $errors[] = "Отсутствует корректный массив 'services' в запросе.";
        }

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();

        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $errors = array_merge($errors, $saveResult->getErrorMessages()); // Возвращаем массив ошибок
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Функция для генерации GUID (внутр.)
     * @return string
     */
    private function generateGUID(): string
    {
        if (function_exists('com_create_guid')) {
            return strtolower(trim(com_create_guid(), '{}'));
        } else {
            return strtolower(sprintf(
                '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(16384, 20479), // 4XXX
                mt_rand(32768, 49151), // 8XXX
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535)
            ));
        }
    }

    /**
     * ? Генерация ссылки для анонимной формы DEV (внтур.)
     * @param $dealId
     * @return string|void|null
     * @throws ArgumentException
     */
    public function generateDevLinkForAnonimForm($dealId)
    {
        // Получаем фабрику для сделок через контейнер
        $entityTypeId = \CCrmOwnerType::Deal;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            die("Не удалось получить фабрику для сделок.");
        }

        // Получаем объект сделки по ID
        $item = $factory->getItem($dealId);
        if (!$item) {
            die("Сделка с ID $dealId не найдена.");
        }

        // Генерируем GUID и формируем ссылку
        $guid = $this->generateGUID();

        // Формируем ссылку с параметром GUID
        $testLink = "https://stage-umber.vercel.app/doc-loader?id=" . urlencode($guid);

        // Сохраняем GUID в пользовательское поле сделки
        $item->set('UF_CRM_GUID', $guid);

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();
        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $saveResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $dealId,
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b] {$message} [/b]"
                ]
            ]);
            return null;
        }
        $message = "Ссылка (тест) для анонимной формы: " . $testLink;
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                "ENTITY_ID" => $dealId,
                "ENTITY_TYPE" => "DEAL",
                "COMMENT" => "[b] {$message} [/b]"
            ]
        ]);
        return $testLink;
    }

    /**
     * ? Генерация ссылки для анонимной формы PROD (внтур.)
     * @param $dealId
     * @return string|void|null
     * @throws ArgumentException
     */
    public function generateLinkForAnonimForm($dealId)
    {
        // Получаем фабрику для сделок через контейнер
        $entityTypeId = \CCrmOwnerType::Deal;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            die("Не удалось получить фабрику для сделок.");
        }

        // Получаем объект сделки по ID
        $item = $factory->getItem($dealId);
        if (!$item) {
            die("Сделка с ID $dealId не найдена.");
        }

        // Генерируем GUID и формируем ссылку
        $guid = $this->generateGUID();

        // Формируем ссылку с параметром GUID
        $link = "https://seller-capital.ru/doc-loader?id=" . urlencode($guid);

        // Сохраняем GUID в пользовательское поле сделки
        $item->set('UF_CRM_GUID', $guid);

        $operation = $factory->getUpdateOperation($item);
        $operation->disableAllChecks();
        // Сохраняем элемент CRM после установки всех полей
        $saveResult = $operation->launch();

        if (!$saveResult->isSuccess()) {
            $message = "Ошибка при обновлении сделки: " . implode(", ", $saveResult->getErrorMessages());
            \CRest::call('crm.timeline.comment.add', [
                'fields' => [
                    "ENTITY_ID" => $dealId,
                    "ENTITY_TYPE" => "DEAL",
                    "COMMENT" => "[b] {$message} [/b]"
                ]
            ]);
            return null;
        }
        $message = "Ссылка для анонимной формы: " . $link;
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                "ENTITY_ID" => $dealId,
                "ENTITY_TYPE" => "DEAL",
                "COMMENT" => "[b] {$message} [/b]"
            ]
        ]);
        return $link;
    }
    //endregion Внутренние функции
}
