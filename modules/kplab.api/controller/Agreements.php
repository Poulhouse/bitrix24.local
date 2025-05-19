<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Web\JWT;
use KPLab\API\V2\Auth\ActionFilter\Authentication;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_AGREEMENTS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/AgreementsController.log");

/**
 *
 * @OA\Tag(
 *       name="Agreements",
 *       description="Соглашения пользователя"
 *   )
 */
class Agreements extends \Bitrix\Main\Engine\Controller
{
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
     * Установка данных о Соглашениях пользователя
     *
     * @OA\Post(
     *      path="/agreements/add/",
     *      tags={"Agreements"},
     *      summary="Добавления данных о Соглашениях пользователя",
     *      operationId="addAction",
     *      @OA\Response(
     *          response=200,
     *          description="Успешный ответ"
     *      ),
     *      @OA\RequestBody(
     *          description="Новая информация о соглашении пользователя",
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="ipAddress",
     *                      description="IP адрес пользователя",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="inn",
     *                      description="ИНН пользователя",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="type",
     *                      description="ID типа соглашения",
     *                      type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="siteUrl",
     *                      description="URL страницы, на которой было оставлено согласие",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="docUrl",
     *                      description="URL документа, с которым согласились",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="formData",
     *                      description="Даннаые отправленной формы",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="field_1",type="string"),
     *                          @OA\Property(property="field_2",type="string")
     *                      )
     *                  )
     *              )
     *          )
     *      )
     *  )
     *
     *
     */
    public function addAction(array $params = []): mixed
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
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления соглашение пользователя: ";
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
        if(empty($arRequest['ipAddress'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `ipAddress`';
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        Loader::includeModule('iblock');

        $IBLOCK_ID = 235;

        $ipAddress = $arRequest['ipAddress'];
        $inn = $arRequest['inn'];
        $type = $arRequest['type'];
        $siteUrl = $arRequest['siteUrl'];

        $arFilter = array(
            "IBLOCK_ID" => $IBLOCK_ID,
            "CODE" => "TYPE" // Код вашего свойства типа "Список"
        );
        $rsPropsType = \CIBlockPropertyEnum::GetList(array(), $arFilter);
        while ($arPropType = $rsPropsType->Fetch()) {
            $arTypeId[$arPropType["XML_ID"]] = $arPropType["ID"];
            $arTypeName[$arPropType["XML_ID"]] = $arPropType["VALUE"];
        }

        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления соглашение пользователя: {$inn}";


        // Подготовка массива свойств для добавления в инфоблок
        $arProperties = [
            'TYPE' => $arTypeId[$type],
            'INN' => $inn,
            'IP_ADDRESS' => $ipAddress,
            'FORM_DATA' => json_encode($arRequest['formData'],JSON_UNESCAPED_UNICODE), // Если FORM_DATA - HTML/текст, то можно использовать json_encode или просто строковое представление
            'DOC_LINK' => $arRequest['docLink'],
            'SITE_URL' => $siteUrl,
            'DATE_DATA' => date('d.m.Y H:i:s'),
        ];

        // Добавление нового элемента в инфоблок
        $arFields = [
            "IBLOCK_ID" => $IBLOCK_ID,
            "NAME" => "Новое $arTypeName[$type] для $inn", // Название элемента
            "ACTIVE" => "Y",
            "PROPERTY_VALUES" => $arProperties
        ];
        //Logs\File ::AddMessage($arFields, "arFields", LOG_API_SYNC_AGREEMENTS_CONTROLLER);
        $el = new \CIBlockElement;
        $elementId = $el->Add($arFields);

        if ($elementId === false) {
            $message[] = "Ошибка при добавлении элемента: " . $el->LAST_ERROR;
        } else {
            self::bizProc($IBLOCK_ID, $elementId, 1);
            $message[] = "Информация о соглашении успешно добавлено с ID: " . $elementId;
        }

        $objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($message, $objectData);
    }
    //endregion POST

    //region GET

    /**
     * Получения данных о Соглашениях пользователя
     *
     * @OA\Parameter(
     *    parameter="inn",
     *    name="inn",
     *    description="ИНН пользователя",
     *    @OA\Schema(
     *      type="string"
     *    ),
     *    in="query",
     *    required=true
     *  )
     *
     * @OA\Parameter(
     *     parameter="type",
     *     name="type",
     *     description="ID Типа согласия",
     *     @OA\Schema(type="integer"),
     *     in="query",
     *     required=false
     *   )
     *
     * @OA\Get(
     *      path="/agreements/",
     *      summary="Получения данных о Соглашениях пользователя",
     *      tags={"Agreements"},
     *      operationId="getAction",
     *      @OA\Parameter(ref="#/components/parameters/inn"),
     *      @OA\Parameter(ref="#/components/parameters/type"),
     *      @OA\Response(
     *          response=200,
     *          description="Успешный ответ"
     *      )
     *  )
     * @return array|EventResult|mixed
     * @throws LoaderException
     */
    public function getAction(array $params = []): mixed
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
        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат добавления соглашение пользователя: ";
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
        /*$controllerName = get_class($this);
        $methodName = __FUNCTION__;
        $statusRequest = 'Success'; // Статус запроса
        $requestTypeId = 0;
        $outRequest = false;
        $jsonRes = ['status' => $statusRequest, 'response' => null];
        $partnerName = "SE";
        $taskId = 0;*/
        $arRequest = [];
        if($requestMethod === 'GET') {
            \KPLab\Logs\File::AddMessage($queryParamsArray,"queryParamsArray", LOG_API_SYNC_AGREEMENTS_CONTROLLER);
            $arRequest = $queryParamsArray;
        } else {
            \KPLab\Logs\File::AddMessage($requestJson,"requestJson", LOG_API_SYNC_AGREEMENTS_CONTROLLER);
            $arRequest = json_decode($requestJson,true);
        }
        \Bitrix\Main\Loader ::IncludeModule('crm');
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = 'Строка запроса пуста.';
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        if(empty($arRequest['inn'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `inn`';
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        $queryInn = $arRequest['inn'];
        $queryType = (!empty($arRequest['type']) ? $arRequest['type'] : null);

        $arResponse = [];
        //$arTypeId = [];
        $arTypeName = [];

        Loader::includeModule('iblock');
        $IBLOCK_ID = 235;
        if(is_null($queryType)) {
            $arFilter = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "PROPERTY_INN" => $queryInn
            );
        }
        else {
            $filter = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "CODE" => "TYPE" // Код вашего свойства типа "Список"
            );
            $rsPropsType = \CIBlockPropertyEnum::GetList(array(), $filter);

            while ($arPropType = $rsPropsType->Fetch()) {
                //$arTypeId[$arPropType["XML_ID"]] = $arPropType["ID"];
                $arTypeName[$arPropType["XML_ID"]] = $arPropType["VALUE"];
            }

            $arFilter = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "PROPERTY_INN" => $queryInn,
                "PROPERTY_TYPE_VALUE" => $arTypeName[$queryType]
            );
        }
        $rs = \CIBlockElement::GetList([], $arFilter);
        while ($ar = $rs->GetNextElement()) {
            $arFields = $ar->GetFields();
            $arProps = $ar->GetProperties();
            print_r($arProps);
            $type = [];
            $idAgreementForm = $arProps['ID_AGREEMENT_FORM']['VALUE'];

            switch ($idAgreementForm) {
                case 26:
                    $type['id'] = 4;
                    $type['title'] = "02.11.23. Оферта ООО «Селлер Капитал» МКК";
                    break;

                case 28:
                    $type['id'] = 3;
                    $type['title'] = "Политика в отношении обработки персональных данных Seller сайт (11.05.23)";
                    break;

                case 39:
                    $type['id'] = 2;
                    $type['title'] = "Соглашение о простой электронной подписи (ПЭП) (публичная оферта)";
                    break;

                case null:
                    $type['id'] = $arProps['TYPE']['VALUE_XML_ID'];
                    $type['title'] = $arProps['TYPE']['VALUE_ENUM'];
                    break;
            }

            $ipAddress = $arProps['IP_ADDRESS']['VALUE'];
            $inn = $arProps['INN']['VALUE'];
            $siteUrl = $arProps['SITE_URL']['VALUE'];
            $date = date('Y-m-d\TH:i:s.msp', strtotime($arProps['DATE_DATA']['VALUE']));
            $arResponse[] = [
                'ipAddress' => $ipAddress,
                'inn' => $inn,
                'siteUrl' => $siteUrl,
                'date' => $date,
                'type' => $type
            ];
        }


        $this->CURLObjectData['ITEM_TITLE'] = "SE: Результат поиска соглашение пользователя по ИНН: {$queryInn}";
        $objectData = $this->CURLObjectData;

        return $HandlerResponse->handleSuccess($arResponse, $objectData);
    }
    //endregion GET

    /**
     * Запуск БП списка (внутр.)
     * @param $IBLOCK_ID
     * @param $elementId
     * @param int $AUTO_EXECUTE
     * @return void
     * @throws LoaderException
     */
    private static function bizProc($IBLOCK_ID, $elementId, int $AUTO_EXECUTE = 0): void
    {
        global $USER;
        $arWorkflowParameters = [];
        $arErrorsTmp = [];
        $errorMessage = null;

        if (!is_object($USER)) {
            $USER = new \CUser();
        }

        // Бизнес процесс
        if (Loader::IncludeModule('bizproc')) {
            $arWorkflowTemplates = \CBPDocument::GetWorkflowTemplatesForDocumentType([
                'lists', 'Bitrix\Lists\BizprocDocumentLists', 'iblock_' . $IBLOCK_ID
            ]);

            foreach ($arWorkflowTemplates as $arTemplate) {
                /*
                    * AUTO_EXECUTE = 1 - запускать при создании
                    * AUTO_EXECUTE = 2 - запускать при изменении
                    * AUTO_EXECUTE = 3 - запускать при создании И изменении
                */
                if ($arTemplate['AUTO_EXECUTE'] == $AUTO_EXECUTE) {
                    $wfId = \CBPDocument::StartWorkflow(
                        $arTemplate['ID'],
                        [ 'lists', 'Bitrix\Lists\BizprocDocumentLists', $elementId ],
                        array_merge($arWorkflowParameters, [ 'TargetUser' => "user_{$USER->GetID()}" ]),
                        $arErrorsTmp
                    );


                    if (count($arErrorsTmp) > 0) {
                        foreach ($arErrorsTmp as $e) {
                            $errorMessage .= "[".$e["code"]."] ".$e["message"]."";
                        }
                    }
                }
            }
        }

    }
}