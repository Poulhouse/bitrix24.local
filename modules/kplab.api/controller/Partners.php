<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\JWT;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_SYNC_PARTNER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/PartnersController.log");

class Partners extends \Bitrix\Main\Engine\Controller
{
    protected function getDefaultPreFilters(): array
    {
        // Возвращаем пустой массив или только нужные фильтры
        return [
            new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
        ];
    }
    /**
     * Получение конкретного партнера по ИНН
     */
    /**
     * @OA\Get(
     *     path="/partners/",
     *     tags={"Partners"},
     *     summary="Получение конкретного партнера по ИНН",
     *     operationId="getListAction",
     *     @OA\Parameter(name="partnerInn", required=true, in="query", @OA\Schema(type="string", example="616270066366")),
     *     @OA\Parameter(name="qty", in="query", @OA\Schema(type="integer", example="50")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", example="1")),
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
     *                      @OA\Property(property="object", type="string", example="partner"),
     *                      @OA\Property(
     *                          property="results",
     *                          type="array",
     *                          @OA\Items(
     *                               @OA\Property(property="inn", type="string", example="425000517257"),
     *                               @OA\Property(property="companyTitle", type="string"),
     *                               @OA\Property(property="all_deals", type="string", example="0"),
     *                               @OA\Property(property="all_leads", type="string", example="10"),
     *                               @OA\Property(property="last_sum_scp", type="string", example="0.00"),
     *                               @OA\Property(property="all_sum_scp", type="string", example="0.00"),
     *                               @OA\Property(property="qr", type="string", example="https://crm.seller-capital.ru/~ajIFM"),
     *                               @OA\Property(property="referral_link", type="string", example="https://seller-capital.ru/?utm_source=seller&utm_medium=referral&utm_campaign=online&utm_content=425000517257"),
     *                          )
     *                      ),
     *                      @OA\Property(property="total", type="integer", example="10"),
     *                      @OA\Property(property="total_pages", type="integer", example="5"),
     *                      @OA\Property(property="has_more", type="boolean", example=true)
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
    /**
     * @param array $params
     * @return array|EventResult|null
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
	public function getListAction(array $params = []): array|EventResult|null {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$headers = $request->getHeaders()->toArray();
		$server = $context -> getServer();
		$serverArray = $server->toArray();
		$serverName = $serverArray['SERVER_NAME'];
        $totalPartners = 0;

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$point = "EXTRANET_BX";
		$QUERY_STRING = $server['QUERY_STRING'];
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$url = $server['SCRIPT_URI']."?".$QUERY_STRING;
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headersValues = [];
		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}

		$requestArray = json_decode($request->getInput(),true);
		parse_str($QUERY_STRING, $queryArray);


		if(isset($queryArray['qty'])) {
			$qty = $queryArray['qty'];
		} else {
			$qty = 50;
		}
		if(isset($queryArray['page'])) {
			$offset = ($queryArray['page'] - 1) * $qty;
		} else {
			$offset = 0;
		}

		/*if(isset($queryArray['partnerInn'])) {
			$partnerInn = $queryArray['partnerInn'];
		} else {
			$partnerInn = null;
		}*/

		/*$_sellers = new Sellers;
		$partnerId = $_sellers->findCard($partnerInn);*/

		if($qty > 50) {
			$errorMessage = "400 Bad Request | `qty` not must more 50!";
			$this->addError(new Error($errorMessage, 400));
			$jsonRes['success'] = null;
			$jsonRes['error'] = $errorMessage;
			Logs\IBlock::setData($url, $requestArray, $jsonRes, $objectData, $timeData, $point, $headersValues);
			return null;
		}

		/*
				if(empty($params)) {

					Logs\File ::AddMessage($requestArray, "requestArray", LOG_API_SYNC_PARTNER_CONTROLLER);

					if($requestArray == NULL) {
						Context::getCurrent()->getResponse()->setStatus(400);
						$errorMessage = "400 Bad Request | Тело запроса не удалось декодировать как JSON.";
						$this->addError(new Error($errorMessage, "invalid_json"));
						$jsonRes['success'] = null;
						$jsonRes['error'] = $errorMessage;
						Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData, $point, $headersValues);
						return new EventResult(EventResult::ERROR, null, null, $this);
					}
				}
		*/

        $partnerInn = $queryArray['partnerInn'] ?? 0;

        $entityId = false;
        $entityTypeIdSCP = 1054;
        $entityTypeCategoryIdSCP = 237;
        $entityTypeIdLead = \CCrmOwnerType::Lead;
        $entityTypeIdCompany = \CCrmOwnerType::Company;

        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_LEAD',
                'FIELD_NAME' => 'UF_CRM_LEAD_STATUS_FOR_PARTNER'
            ]
        ]);
        $arUserFieldValues = [];
        while ($arUserField = $userFields->fetch()){
            $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID']]);
            while ($arUserFieldData = $res->fetch()) {
                $arUserFieldValues[] = $arUserFieldData;
            }
        }
        foreach($arUserFieldValues as $arUserFieldValue){
            $statusLead = $arUserFieldValue['XML_ID'];
            if($statusLead == 'issued') {
                $statusLeadId = $arUserFieldValue['ID'];
            }
        }

        $factorySCP = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdSCP);
        $factoryLead = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLead);
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdCompany);
        if (!$factorySCP)
        {
            Context::getCurrent()->getResponse()->setStatus(500);
            $this -> addError(new Error('Ошибка на сервере', "invalid_server"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        if(!$partnerInn) {
            $filterSCP = [];
        }
        else {
            $filterSCP = [
                'UF_CRM_87_1723615916' => $partnerInn,
                'CATEGORY_ID' => $entityTypeCategoryIdSCP,
            ];
        }
        $params = [
            'filter' => $filterSCP,
            'select' => ['*'],
            'limit' => $qty,
            'offset' => $offset,
        ];

        Logs\File ::AddMessage($params, "params", LOG_API_SYNC_PARTNER_CONTROLLER);
        $result = [];
        $itemsSCP = $factorySCP -> getItems($params);
        $totalPartners = $factorySCP -> getItemsCount($filterSCP);
        if($itemsSCP) {
            foreach ($itemsSCP as $itemSCP)
            {

                $itemSCPData = $itemSCP->getData();
                //$totalPartners++;
                //Logs\File ::AddMessage($itemSCPData, "itemSCP", LOG_API_SYNC_PARTNER_CONTROLLER);
                $entityId = $itemSCP->getId();
                $companyId = $itemSCPData['COMPANY_ID'];
                $companyTitle = $factoryCompany->getItem($companyId)->getTitle();
                $last_sum_scp = (float) str_replace("|RUB","", $itemSCPData['UF_CRM_87_1723631985']);
                $referral_link = $itemSCPData['UF_CRM_87_1723616716'];
                $qr = $itemSCPData['UF_CRM_87_1723616830'];
                $inn = $itemSCPData['UF_CRM_87_1723615916'];
                $all_sum_scp = 0;

                $filterAllLeads = [
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => (string) $inn,
                ];
                $filterAllDeals = [
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => (string) $inn,
                    '!UF_CRM_63DBAB918A894' => null,
                ];
                $paramsLeads = [
                    'filter' => [
                        'UF_CRM_1689230879' => true,
                        'UTM_CONTENT.VALUE' => (string) $inn,
                        'UF_CRM_LEAD_STATUS_FOR_PARTNER' => $statusLeadId,
                    ],
                    'select' => ['ID']
                ];
                $all_leads = $factoryLead -> getItemsCount($filterAllLeads);
                $all_deals = $factoryLead -> getItemsCount($filterAllDeals);
                $itemsLead = $factoryLead -> getItems($paramsLeads);

                $itemSCP->set('UF_CRM_87_1723628572', $all_leads);
                $itemSCP->set('UF_CRM_87_1723628880', $all_deals);

                Logs\File ::AddMessage($all_leads, "all_leads", LOG_API_SYNC_PARTNER_CONTROLLER);
                Logs\File ::AddMessage($all_deals, "all_deals", LOG_API_SYNC_PARTNER_CONTROLLER);

                if($itemsLead) {
                    foreach ($itemsLead as $itemLead) {
                        $sum_scp = $itemLead->getData()['UF_CRM_1595501790987'];
                        $all_sum_scp += (float) str_replace("|RUB","", $sum_scp);
                        //Logs\File ::AddMessage($itemLead->getData(), "itemLeadData", LOG_API_SYNC_PARTNER_CONTROLLER);
                    }
                } //'UTM_CONTENT' => '7721546864',*/
                $itemSCP->set('UF_CRM_87_1723632160', "{$all_sum_scp}|RUB");
                $result = [
                    'inn' => $inn,
                    'companyTitle' => $companyTitle,
                    'all_deals' => $all_deals,
                    'all_leads' => $all_leads,
                    'last_sum_scp' => number_format($last_sum_scp, 2,"."," "),
                    'all_sum_scp' => number_format($all_sum_scp, 2,"."," "),
                    'qr' => $qr,
                    'referral_link' => $referral_link,
                ];
                $operation = $factorySCP->getUpdateOperation($itemSCP);
                $operation->disableAllChecks();
                $saveResult = $operation->launch();
            }
        }

        $objectData['ITEM_TITLE'] = "Получение партнеров";
        $arPartners['object'] = (string) "partner";
        $arPartners['results'][] = $result;

        //Logs\File ::AddMessage($crmId, "crmId", LOG_API_SYNC_PARTNER_CONTROLLER);
        $totalPages = ceil($totalPartners / $qty);
        $arPartners['total'] = (integer) $totalPartners;
        $arPartners['total_pages'] = (integer) $totalPages;

        if ($offset + $qty >= $totalPartners) {
            $arPartners['has_more'] = false;
        } else {
            $arPartners['has_more'] = true;
        }

        $jsonRes['success'] = $arPartners;
        $jsonRes['error'] = "";
        Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData,
            $point, $headersValues);
        return $jsonRes['success'];
	}

    /**
     * Получение селлеров конкретного партнера по ИНН и по внешнему id партнера
     */

    /**
     * @OA\Get(
     *     path="/partners/getSellers/",
     *     tags={"Partners"},
     *     summary="Получение селлеров конкретного партнера по ИНН и по внешнему id партнера",
     *     operationId="getSellersAction",
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", example="2000-01-01")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", example="2025-01-01")),
     *     @OA\Parameter(name="partnerInn", required=true, in="query", @OA\Schema(type="string", example="616270066366")),
     *     @OA\Parameter(name="qty", in="query", @OA\Schema(type="integer", example="50")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", example="1")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"new","in_processed","issued","refused","approved"}, example="issued")),
     *     @OA\Parameter(name="lk", in="query", required=true, @OA\Schema(type="boolean", example=false)),
     *     @OA\Parameter(name="internalId", in="query", @OA\Schema(type="string", example="0e57f79b-e98c-4ef4-8f13-fca1d6b3b0a3")),
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
     *                      @OA\Property(property="object", type="string", example="partnerSellers"),
     *                      @OA\Property(
     *                          property="results",
     *                          type="array",
     *                          @OA\Items(
     *                               @OA\Property(property="status", type="string", enum={"new","in_processed","issued","refused","approved"}, example="issued"),
     *                               @OA\Property(property="seller_id", type="string", example="235177"),
     *                               @OA\Property(property="internalId", type="string", example="0e57f79b-e98c-4ef4-8f13-fca1d6b3b0a3"),
     *                               @OA\Property(property="name", type="string"),
     *                               @OA\Property(property="firstname", type="string"),
     *                               @OA\Property(property="surname", type="string"),
     *                               @OA\Property(property="patronymic", type="string"),
     *                               @OA\Property(property="companyTitle", type="string"),
     *                               @OA\Property(property="dateLead", type="string", example="2024-08-01T00:00:00.0800+05:00"),
     *                               @OA\Property(property="updateDateLead", type="string", example="2024-08-15T16:08:02.0802+05:00"),
     *                               @OA\Property(property="dateDeal", type="string", example="2025-02-15T00:00:00.0800+05:00"),
     *                               @OA\Property(property="sumDeal", type="integer", example="0"),
     *                               @OA\Property(property="sumSCP", type="integer", example="0"),
     *                          )
 *                          ),
     *                      @OA\Property(property="sumSCPDeals", type="string", example="0.00"),
     *                      @OA\Property(property="partnerInn", type="string", example="7721546864"),
     *                      @OA\Property(property="total", type="integer", example="10"),
     *                      @OA\Property(property="total_pages", type="integer", example="5"),
     *                      @OA\Property(property="has_more", type="boolean", example=true)
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
    /**
     * @param array $params
     * @return array|EventResult
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
	public function getSellersAction(array $params = []): array|EventResult
    {
        //region Подготовка к обработке запроса
        \Bitrix\Main\Loader ::IncludeModule('crm');
        $serverName = Application::getInstance()->getContext()->getServer()->toArray()['SERVER_NAME'];
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $server = $context -> getServer();
        $requestTime = $server['REQUEST_TIME'];

        $requestHeaders = $context->getRequest()->getHeaders()->toArray();
        $requestJson = $context->getRequest()->getInput();
        $requestMethod = $server['REQUEST_METHOD'];
        $queryParamsArray = $context->getRequest()->toArray();

        $url = $server -> get('SCRIPT_URI') .'?'. $server -> get('QUERY_STRING');
        $this->CURLObjectData['METHOD'] = $requestMethod;
        $this->CURLObjectData['ITEM_TITLE'] = "Получение списка селлеров по ИНН партнера: ";
        $objectData = $this->CURLObjectData;

        $headersValues = [];
        foreach ($requestHeaders as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }

        // Получаем имя текущего контроллера и метода
        $HandlerResponse = (new HandlerResponse())->handleInit(
            $this,
            __FUNCTION__,
            "EXTRANET",
            $requestMethod,
            $url,
            $timeData,
            $headersValues,
            $requestJson,
            $context
        );
        $arRequest = [];
        if($requestMethod === 'GET') {
            $arRequest = $queryParamsArray;
        } else {
            $arRequest = json_decode($requestJson,true);
        }
        //endregion

        //region Обработка ошибок
        if($arRequest == NULL) {
            $errorMessage = "400 Bad Request | Запрос не удалось распознать";

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }

        $partnerInn = $arRequest['partnerInn'] ?? 0;

        if(!$partnerInn) {
            $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Пустой `partnerInn`";
            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        $startDate = (isset($arRequest['startDate']))
            ? date('d.m.Y', strtotime($arRequest['startDate']))
            : date('d.m.Y', strtotime('2000-01-01'));

        $endDate = (isset($arRequest['endDate']))
            ? date('d.m.Y', strtotime($arRequest['endDate']))
            : date('d.m.Y');

        $qty = (isset($arRequest['qty']))
            ? $arRequest['qty']
            : 50;

        $offset = (isset($arRequest['page']))
            ? ($arRequest['page'] - 1) * $qty
            : 0;

        $internalId = $arRequest['internalId'] ?? null;

        $lk = $arRequest['lk'] ?? false;

        $statusLeadId = 0;

        if(!empty($arRequest['status'])) {
            switch($arRequest['status']) {
                case 'new':
                case 'issued':
                case 'refused':
                case 'in_processed':
                case 'approved':
                    $statusLeadXMLId = $arRequest['status'];
                    break;
                case 'all':
                    $statusLeadXMLId = 0;
                    break;
                default:
                    $errorMessage = "400 Bad Request | Этот запрос не поддерживается. Неизвестный `status`";
                    return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
            }

            if($statusLeadXMLId) {
                $userFields = \Bitrix\Main\UserFieldTable::getList([
                    'select' => ['ID'],
                    'filter' => [
                        '=ENTITY_ID' => 'CRM_LEAD',
                        'FIELD_NAME' => 'UF_CRM_LEAD_STATUS_FOR_PARTNER'
                    ]
                ]);

                while ($arUserField = $userFields->fetch()){
                    $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => $statusLeadXMLId]);
                    while ($arUserFieldData = $res->fetch()) {
                        $statusLeadId = $arUserFieldData['ID'];
                    }
                }
            }
        }

        $entityTypeIdLead = \CCrmOwnerType::Lead;
        $factoryLead = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLead);

        if(!$statusLeadId) {
            if(is_null($internalId)) {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => $partnerInn
                ];
            } else {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => $partnerInn,
                    'UF_OUT_INTERNALID' => $internalId
                ];
            }

        }
        else {
            if(is_null($internalId)) {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => $partnerInn,
                    'UF_CRM_LEAD_STATUS_FOR_PARTNER' => $statusLeadId
                ];
            } else {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => $partnerInn,
                    'UF_CRM_LEAD_STATUS_FOR_PARTNER' => $statusLeadId,
                    'UF_OUT_INTERNALID' => $internalId
                ];
            }

        }

        $paramsLeads = [
            'filter' => $filter,
            'select' => ['*','UF_*'],
            'limit' => $qty,
            'offset' => $offset,
        ];

        $totalPartnerSellers = $factoryLead -> getItemsCount($filter);
        $itemsLead = $factoryLead -> getItems($paramsLeads);
        $sellerTotalSum = 0;
        $result = [];

        if($itemsLead) {
            foreach ($itemsLead as $itemLead) {
                $itemLeadData = $itemLead->getData();

                $itemLeadId = $itemLead->getId();
                $titleLead = $itemLead->getTitle();
                $nameLead = ($itemLeadData['NAME'] != "") ? (string) $itemLeadData['NAME'] : null;
                $internalIdLead = ($itemLeadData['UF_OUT_INTERNALID'] != "") ? (string) $itemLeadData['UF_OUT_INTERNALID'] : null;
                $lastNameLead = ($itemLeadData['LAST_NAME'] != "") ? (string) $itemLeadData['LAST_NAME'] : null;
                $secondNameLead = ($itemLeadData['SECOND_NAME'] != "") ? (string) $itemLeadData['SECOND_NAME'] : null;
                $companyTitleLead = ($itemLeadData['COMPANY_TITLE'] != "") ? (string) $itemLeadData['COMPANY_TITLE'] : null;
                $dateLead = ($itemLeadData['UF_CRM_1712815273']) ? date('Y-m-d\TH:i:s.msp', strtotime($itemLeadData['UF_CRM_1712815273'])) : null;
                $updateDateLead = ($itemLeadData['UPDATED_TIME']) ? date('Y-m-d\TH:i:s.msp', strtotime($itemLeadData['UPDATED_TIME'])) : null;
                $dateDeal = ($itemLeadData['UF_CRM_63DBAB918A894']) ? date('Y-m-d\TH:i:s.msp', strtotime($itemLeadData['UF_CRM_63DBAB918A894'])) : null;
                $sumDeal = (float) str_replace("|RUB","", $itemLeadData['UF_CRM_1595501790987']);
                $scp_kb = $itemLeadData['UF_CRM_1682069017302']; //коэффициент вознаграждения
                $sumSCP = $sumDeal * ($scp_kb/100);

                $statusLeadId = $itemLeadData['UF_CRM_LEAD_STATUS_FOR_PARTNER'];
                $userFields = \Bitrix\Main\UserFieldTable::getList([
                    'select' => ['ID'],
                    'filter' => [
                        '=ENTITY_ID' => 'CRM_LEAD',
                        'FIELD_NAME' => 'UF_CRM_LEAD_STATUS_FOR_PARTNER'
                    ]
                ]);
                while ($arUserField = $userFields->fetch()){
                    $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'ID' => $statusLeadId]);
                    while ($arUserFieldData = $res->fetch()) {
                        $statusLeadXMLId = $arUserFieldData['XML_ID'];
                        //Logs\File ::AddMessage($statusLeadXMLId, "statusLeadXMLId", LOG_API_SYNC_PARTNER_CONTROLLER);
                    }
                }

                if($lk) {
                    if($statusLeadXMLId == "new"
                        || ($statusLeadXMLId == "in_processed")
                        || ($statusLeadXMLId == "refused")
                        || ($statusLeadXMLId == "approved")
                    ) {
                        $result[] = [
                            'status' => $statusLeadXMLId,
                            'seller_id' => $itemLeadId,
                            'internalId' => $internalIdLead,
                            'name' => null,
                            'firstname' => $nameLead,
                            'surname' => $lastNameLead,
                            'patronymic' => $secondNameLead,
                            'companyTitle' => $companyTitleLead,
                            'dateLead' => $dateLead,
                            'updateDateLead' => $updateDateLead,
                            'dateDeal' => null,
                            'sumDeal' => null,
                            'sumSCP' => null,
                        ];
                    }
                    else {
                        $result[] = [
                            'status' => $statusLeadXMLId,
                            'seller_id' => $itemLeadId,
                            'internalId' => $internalId,
                            'name' => $titleLead,
                            'firstname' => $nameLead,
                            'surname' => $lastNameLead,
                            'patronymic' => $secondNameLead,
                            'companyTitle' => $companyTitleLead,
                            'dateLead' => $dateLead,
                            'updateDateLead' => $updateDateLead,
                            'dateDeal' => $dateDeal,
                            'sumDeal' => $sumDeal,
                            'sumSCP' => $sumSCP,
                        ];
                    }
                }
                else {
                    $result[] = [
                        'status' => $statusLeadXMLId,
                        'seller_id' => $itemLeadId,
                        'internalId' => $internalIdLead,
                        'name' => $titleLead,
                        'firstname' => $nameLead,
                        'surname' => $lastNameLead,
                        'patronymic' => $secondNameLead,
                        'companyTitle' => $companyTitleLead,
                        'dateLead' => $dateLead,
                        'updateDateLead' => $updateDateLead,
                        'dateDeal' => $dateDeal,
                        'sumDeal' => $sumDeal,
                        'sumSCP' => $sumSCP,
                    ];
                }


                $sellerTotalSum = $sellerTotalSum + $sumSCP;
            }
        }

        $sellerTotalSum = number_format($sellerTotalSum, 2, '.', ' ');

        $objectData['ITEM_TITLE'] = "Получение селлеров от партнера";
        $arPartnerSellers['object'] = "partnerSellers";
        $arPartnerSellers['results'] = $result;
        $arPartnerSellers['sumSCPDeals'] = $sellerTotalSum;
        $arPartnerSellers['partnerInn'] = $partnerInn;

        $totalPages = ceil($totalPartnerSellers / $qty);
        $arPartnerSellers['total'] = $totalPartnerSellers;
        $arPartnerSellers['total_pages'] = $totalPages;

        if ($offset + $qty >= $totalPartnerSellers) {
            $arPartnerSellers['has_more'] = false;
        } else {
            $arPartnerSellers['has_more'] = true;
        }

        $jsonRes['success'] = $arPartnerSellers;
        $jsonRes['error'] = "";

        return $jsonRes['success'];

	}

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

    public static function getSumScp($referralSumDeal, $referralId,$requestTime): string {
        $leadDateFrom = "";
        $leadDateTo = "";

        $curM = date('n',$requestTime);
        $curY = date('Y',$requestTime);

        $lastM = date('m',strtotime('01-'.($curM-1).'-'.$curY));

        $tasksFrom = "01-".$curM.'-'.$curY;
        $tasksTo = "31-".$curM.'-'.$curY;

        $dateFromTo = [
            "FROM" => [
                "LEAD" => $leadDateFrom,
                "DEAL" => $tasksFrom
            ],
            "TO" => [
                "LEAD" => $leadDateTo,
                "DEAL" => $tasksTo
            ]
        ];

        $sellerTotalSum = 0;
        if($referralSumDeal > 0) {
            //получаем информацию о каждом селлере в период, по ID реферала(КОМПАНИИ/КОНТАКТА)
            $sellers = \KPlabReports_2::getSellerByReferralId($referralId, $dateFromTo);

            foreach ($sellers as $k => $seller)
            {
                $sellerTotalSum = $sellerTotalSum + $seller['SUM_SCP_KB_LEAD'];
            }
        }

        $sellerTotalSum = number_format($sellerTotalSum, 2, '.', ' ');

        return $sellerTotalSum;
    }
}