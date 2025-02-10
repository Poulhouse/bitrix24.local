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

\CBitrixComponent::includeComponentClass("kplab:scpreward");

define("LOG_API_SYNC_PARTNER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/PartnersController.log");

class Partners extends \Bitrix\Main\Engine\Controller
{
	public function getDefaultPreFilters()
	{
		return [
			new \KPLab\API\V2\Controller\ActionFilter\Authentication(),
		];
	}

	public function getDefaultPostFilters()
	{
		return array();
	}

	protected function prepareParams()
	{
		return parent ::prepareParams();
	}

	public function getListAction(array $params = [])
	{
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

        if(isset($queryArray['partnerInn'])) {
            $partnerInn = $queryArray['partnerInn'];
        } else {
            $partnerInn = 0;
        }

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
            $params = [
                'filter' => $filterSCP,
                'select' => ['*'],
                'limit' => $qty,
                'offset' => $offset,
            ];
        }
        else {
            $filterSCP = [
                'UF_CRM_87_1723615916' => $partnerInn,
                'CATEGORY_ID' => $entityTypeCategoryIdSCP,
            ];
            $params = [
                'filter' => $filterSCP,
                'select' => ['*'],
                'limit' => $qty,
                'offset' => $offset,
            ];
        }

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



        /*$arSelect = ['ID','NAME','CODE','ACTIVE_DATE','ACTIVE','IBLOCK_ID','IBLOCK_TYPE_ID','PROPERTY_INN_REFERRAL'];
        $arOrder = ['ID' => 'ASC'];

        \Bitrix\Main\Loader::includeModule('iblock');*/

        //$arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM");
        /*if(isset($queryArray['partnerInn'])) {
            $arFilter = array("IBLOCK_ID"=>166, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y", "=PROPERTY_INN_REFERRAL" => $queryArray['partnerInn']);
        } else {
            $arFilter = array("IBLOCK_ID"=>166, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
        }

        $res = \CIBlockElement::GetList(
            $arOrder,
            $arFilter,
            false,
            array(
                "nTopCount"=>$qty,
                //"nPageSize"=>$qty,
                "nOffset"=>$offset
            ),
            $arSelect
        );
        $res2 = \CIBlockElement::GetList(
            $arOrder,
            $arFilter,
            false,
            array(
                //"nTopCount"=>$qty,
                //"nPageSize"=>$qty,
                //"bShowAll"
            ),
            ['ID']
        );
        while ($row = $res2->Fetch())
        {
            $totalPartners++;
        }

        $resultReferrals = array();
        while($el = $res->GetNextElement())
        {

            $arFields = $el->GetFields();
            $arProps = $el->GetProperties();
            $SUM_SCP_KB = 0;
            $SUM_DEALS = 0;
            if($arProps['SUM_SCP_KB']['VALUE'] !== "") $SUM_SCP_KB = str_replace('|RUB', '', $arProps['SUM_SCP_KB']['VALUE']);
            if($arProps['SUM_DEALS']['VALUE'] !== "") $SUM_DEALS = str_replace('|RUB', '', $arProps['SUM_DEALS']['VALUE']);

            $arProp['ASSIGN'] = $arProps['ASSIGN']['VALUE'];
            $arProp['COUNT_DEALS'] = $arProps['COUNT_DEALS']['VALUE'];
            $arProp['COUNT_LEADS'] = $arProps['COUNT_LEADS']['VALUE'];
            $arProp['ID_REFERRAL'] = $arProps['ID_REFERRAL']['VALUE'];
            $arProp['ID_SDELKI_SCP'] = $arProps['ID_SDELKI_SCP']['VALUE'];
            $arProp['INN_REFERRAL'] = $arProps['INN_REFERRAL']['VALUE'];
            $arProp['QR_KOD'] = $arProps['QR_KOD']['VALUE'];
            $arProp['QR_LINK'] = $arProps['QR_LINK']['VALUE'];
            $arProp['REFERRAL_LINK'] = $arProps['REFERRAL_LINK']['VALUE'];
            $arProp['SCP_KB'] = $arProps['SCP_KB']['VALUE'];
            $arProp['SUM_SCP_KB'] = number_format($SUM_SCP_KB,2,'.',' ');
            $arProp['SUM_DEALS'] = number_format($SUM_DEALS,2,'.',' ');
            $resultReferrals[] = array_merge($arProp, $arFields);
        }

        //$resultReferrals = \CRest::call('lists.referral.get')['result'];

        foreach ($resultReferrals as $resultReferral) {
            if(isset($queryArray['partnerInn']) && $resultReferral['INN_REFERRAL'] == $queryArray['partnerInn']) {
                $idReferral = $resultReferral['ID_REFERRAL'];
                $sumDeals = $resultReferral['SUM_DEALS'];
                $resultPartner['all_deals'] = $resultReferral['COUNT_DEALS'];
                $resultPartner['all_leads'] = $resultReferral['COUNT_LEADS'];
                $resultPartner['all_sum_scp'] = $resultReferral['SUM_SCP_KB'];
                $resultPartner['inn'] = $resultReferral['INN_REFERRAL'];
                $resultPartner['qr'] = $resultReferral['QR_LINK'];
                $resultPartner['referral_link'] = $resultReferral['REFERRAL_LINK'];
                // Считаем сумму вознагрождений за предыдущий месяц
                $resultPartner['last_sum_scp'] = $this->getSumScp($sumDeals, $idReferral, $REQUEST_TIME);
                $result = $resultPartner;
            } else {
                $idReferral = $resultReferral['ID_REFERRAL'];
                $sumDeals = $resultReferral['SUM_DEALS'];
                $result[$idReferral]['all_deals'] = $resultReferral['COUNT_DEALS'];
                $result[$idReferral]['all_leads'] = $resultReferral['COUNT_LEADS'];
                $result[$idReferral]['all_sum_scp'] = $resultReferral['SUM_SCP_KB'];
                $result[$idReferral]['inn'] = $resultReferral['INN_REFERRAL'];
                $result[$idReferral]['qr'] = $resultReferral['QR_LINK'];
                $result[$idReferral]['referral_link'] = $resultReferral['REFERRAL_LINK'];
                // Считаем сумму вознагрождений за предыдущий месяц
                $result[$idReferral]['last_sum_scp'] = $this->getSumScp($sumDeals, $idReferral, $REQUEST_TIME);
            }
        }*/

        //$totalPartners = count($result);

        /*$partnerInn = $requestArray['partnerInn'];
        $crmId = (int)$requestArray['crmId'];*/

        $objectData['ITEM_TITLE'] = "Получение парнеров";
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
	public static function getSumScp($referralSumDeal, $referralId,$requestTime) {
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
	public function getSellersAction(array $params = []) {
		$timeData = Logs\TimeData::start();
		$context = Application ::getInstance() -> getContext();
		$request = $context -> getRequest();
		$headers = $request->getHeaders()->toArray();
		$server = $context -> getServer();
		$serverArray = $server->toArray();
		$serverName = $serverArray['SERVER_NAME'];

		\Bitrix\Main\Loader ::IncludeModule('crm');
		$point = "EXTRANET_BX";
		$QUERY_STRING = $server['QUERY_STRING'];
		$REQUEST_TIME = $server['REQUEST_TIME'];
		$url = $server['SCRIPT_URI']."?".$QUERY_STRING;
		$objectData['METHOD'] = $server['REQUEST_METHOD'];

		foreach ($headers as $key => $header) {
			$headersValues[$header['name']] = $header['values'][0];
		}

		$requestArray = json_decode($request->getInput(),true);
		parse_str($QUERY_STRING, $queryArray);

        Logs\File ::AddMessage($queryArray, "queryArray", LOG_API_SYNC_PARTNER_CONTROLLER);

		if(isset($queryArray['startDate'])) {
			$startDate = date('d.m.Y', strtotime($queryArray['startDate']));
		}
        else {
			$startDate = date('d.m.Y', strtotime('2000-01-01'));
		}

		if(isset($queryArray['endDate'])) {
			$endDate = date('d.m.Y', strtotime($queryArray['endDate']));
		}
        else {
			$endDate = date('d.m.Y', strtotime('now'));
		}

		if(isset($queryArray['qty'])) {
			$qty = $queryArray['qty'];
		}
        else {
			$qty = 50;
		}

		if(isset($queryArray['page'])) {
			$offset = ($queryArray['page'] - 1) * $qty;
		} else {
			$offset = 0;
		}

		$curM = date('m', $REQUEST_TIME);
		$curY = date('Y', $REQUEST_TIME);


        /*$endDate = date('d.m.Y', strtotime($endDate));
        $startDate = date('d.m.Y', strtotime($startDate));*/
        Logs\File ::AddMessage([$startDate, $endDate], "dateLead", LOG_API_SYNC_PARTNER_CONTROLLER);

        $leadDateStart = $startDate."T00:00:00";
        $leadDateEnd = $endDate."T23:59:59";

        $tasksFrom = "01-".$curM.'-'.$curY;
        $tasksTo = "31-".$curM.'-'.$curY;



		$dateFromTo = [
			"FROM" => [
				"LEAD" => $leadDateStart,
				"DEAL" => $tasksFrom
			],
			"TO" => [
				"LEAD" => $leadDateEnd,
				"DEAL" => $tasksTo
			]
		];

        if(isset($queryArray['partnerInn'])) {
            $partnerInn = $queryArray['partnerInn'];
        } else {
            $partnerInn = 0;
        }

        $statusLeadXMLId = (!empty($queryArray['status']) && $queryArray['status'] !== 'all') ? $queryArray['status'] : 0;

        if(!$statusLeadXMLId) {
            $statusLeadId = 0;
        } else {
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


        $entityTypeIdLead = \CCrmOwnerType::Lead;
        $factoryLead = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeIdLead);
        if (!$partnerInn)
        {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Ошибка параметра запроса, `partnerInn` не может быть равен 0', "invalid_request"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }
        else {
            if(!$statusLeadId) {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => (string) $partnerInn,
                ];
                $paramsLeads = [
                    'filter' => $filter,
                    'select' => ['*','UF_*'],
                    'limit' => $qty,
                    'offset' => $offset,
                ];
            }
            else {
                $filter = [
                    '>=UF_CRM_1712815273' => $startDate,
                    '<=UF_CRM_1712815273' => $endDate,
                    'UF_CRM_1689230879' => true,
                    'UTM_CONTENT.VALUE' => (string) $partnerInn,
                    'UF_CRM_LEAD_STATUS_FOR_PARTNER' => $statusLeadId,
                ];
                $paramsLeads = [
                    'filter' => $filter,
                    'select' => ['*','UF_*'],
                    'limit' => $qty,
                    'offset' => $offset,
                ];
            }

            $totalPartnerSellers = $factoryLead -> getItemsCount($filter);
            $itemsLead = $factoryLead -> getItems($paramsLeads);
            $sellerTotalSum = 0;
            $result = [];

            if($itemsLead) {
                foreach ($itemsLead as $itemLead) {
                    $itemLeadData = $itemLead->getData();
                    $itemLeadId = $itemLead->getId();
                    $name = $itemLead->getTitle();
                    $dateLead = date('d.m.Y', strtotime($itemLeadData['UF_CRM_1712815273']));
                    $dateDeal = date('d.m.Y', strtotime($itemLeadData['UF_CRM_63DBAB918A894']));
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
                    Logs\File ::AddMessage($itemLeadId, "itemLeadId", LOG_API_SYNC_PARTNER_CONTROLLER);
                    Logs\File ::AddMessage($name, "name", LOG_API_SYNC_PARTNER_CONTROLLER);
                    Logs\File ::AddMessage($statusLeadId, "statusLeadId", LOG_API_SYNC_PARTNER_CONTROLLER);

                    while ($arUserField = $userFields->fetch()){
                        $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'ID' => $statusLeadId]);
                        while ($arUserFieldData = $res->fetch()) {
                            $statusLeadXMLId = $arUserFieldData['XML_ID'];
                            //Logs\File ::AddMessage($statusLeadXMLId, "statusLeadXMLId", LOG_API_SYNC_PARTNER_CONTROLLER);
                        }
                    }
                    if($statusLeadXMLId == "new" || ($statusLeadXMLId == "in_processed") || ($statusLeadXMLId == "refused") || ($statusLeadXMLId == "approved")) {
                        $result[] = [
                            'status' => $statusLeadXMLId,
                            'seller_id' => $itemLeadId,
                            'name' => null,
                            'dateLead' => $dateLead,
                            'dateDeal' => null,
                            'sumDeal' => null,
                            'sumSCP' => null,
                        ];
                    }
                    else {
                        $result[] = [
                            'status' => $statusLeadXMLId,
                            'seller_id' => $itemLeadId,
                            'name' => "Лид",
                            'dateLead' => $dateLead,
                            'dateDeal' => $dateDeal,
                            'sumDeal' => $sumDeal,
                            'sumSCP' => $sumSCP,
                        ];
                    }
                    $sellerTotalSum = $sellerTotalSum + $sumSCP;
                    Logs\File ::AddMessage($result, "result", LOG_API_SYNC_PARTNER_CONTROLLER);
                }
            }
        }

		//Logs\File ::AddMessage($arParams, "arParams", LOG_API_SYNC_PARTNER_CONTROLLER);
        /*
		\Bitrix\Main\Loader::includeModule('iblock');

		$leadFrom = date('2000-01-01');
		$leadTo = date('Y-m-d');
		$curM = date('m');
		$curY = date('Y');
		$dogDateStart = $curY.'-'.$curM.'-'.'01';
		$dogDateEnd = $curY.'-'.$curM.'-'.'31';

		$arSelect = ['ID','NAME','CODE','IBLOCK_ID'];
		$arOrder = ['ID' => 'ASC'];
		$arPeriod = [
			'DATE_FROM' => $leadDateStart,
			'DATE_TO' => $leadDateEnd
		];
		$arLeadDates = [
			'DATE_FROM' => $leadDateStart,
			'DATE_TO' => $leadDateEnd
		];

		if(!empty($status)) {
			if($status == 'new') {
				$statusLead = "Новый";
			}elseif($status == 'in_processed') {
				$statusLead = "В обработке";
			}elseif( $status == 'issued') {
				$statusLead = "Выдан";
			}elseif( $status == 'refused') {
				$statusLead = "Отказ";
			} else {
				$statusLead = "";
			}
		}
		else {
			$statusLead = "";
		}*/
        /*
		$result = array();

		if(!empty($arPeriod))
		{

			if($arLeadDates['DATE_FROM'] == "" && $arLeadDates['DATE_TO'] == "")
			{
				if($statusLead == "") {
					$arFilterDeal = array(
						"::LOGIC" => "AND",
						"IBLOCK_ID" => 167,
						"=PROPERTY_REFERRAL_ID" => $referralId,
						">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
						"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
					);
					$res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(
							"nTopCount"=>$qty,
							//"nPageSize"=>$qty,
							"nOffset"=>$offset
						),
						$arSelect
					);

					$__r_res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(),
						['ID']
					);
					while ($row = $__r_res->Fetch())
					{
						$totalPartnerSellers++;
					}
				}
				else {
					$arFilterDeal = array(
						"::LOGIC" => "AND",
						"IBLOCK_ID" => 167,
						"=PROPERTY_REFERRAL_ID" => $referralId,
						"PROPERTY_STATUS_VALUE" => $statusLead,
						">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
						"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
					);
					$res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(
							"nTopCount"=>$qty,
							//"nPageSize"=>$qty,
							"nOffset"=>$offset
						),
						$arSelect
					);

					$_r_res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(),
						['ID']
					);
					while ($row = $_r_res->Fetch())
					{
						$totalPartnerSellers++;
					}
				}
			}
			else {
				if($statusLead == ""){
					$arFilterLead = array(
						"IBLOCK_ID" => 167,
						"=PROPERTY_REFERRAL_ID" => $referralId,
						">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
						"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
					);
					$resLead = \CIBlockElement::GetList(
						$arOrder,
						$arFilterLead,
						false,
						array(
							"nTopCount"=>$qty,
							//"nPageSize"=>$qty,
							"nOffset"=>$offset
						),
						$arSelect
					);
					$_resLead = \CIBlockElement::GetList(
						$arOrder,
						$arFilterLead,
						false,
						array(),
						['ID']
					);
					while ($row = $_resLead->Fetch())
					{
						$totalPartnerSellers++;
					}
        */
        /*
                            $arFilterDeal = array(
                                "IBLOCK_ID" => 167,
                                "=PROPERTY_REFERRAL_ID" => $referralId,
                                ">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
                                "<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
                            );
                            $resDeal = \CIBlockElement::GetList(
                                $arOrder,
                                $arFilterDeal,
                                false,
                                array(
                                    "nTopCount"=>$qty,
                                    //"nPageSize"=>$qty,
                                    "nOffset"=>$offset
                                ),
                                $arSelect
                            );
        */
        /*					if (!$resLead && !$resDeal)
                            {
                                return [
                                    'error' => 'ERROR_METHOD_NOT_FOUND',
                                    'error_description' => 'Method not found! ' . __LINE__,
                                ];
                            }
        */
        /*
					while($el = $resDeal->GetNextElement())
					{
						$arFields = $el->GetFields();
						$arProps = $el->GetProperties();

						$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
						$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
						$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
						$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
						$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
						$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
						$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
						$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
						$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
						$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
						$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
						$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
						if($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE']){
							$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) *intval($arProps['SCP_KB_LEAD']['VALUE'])/100;
						} else {
							$arProp['SUM_SCP_KB_LEAD'] = 0;
						}
						$resultDeals[] = array_merge($arProp, $arFields);
					}
					*/
        /*
					while($el = $resLead->GetNextElement())
					{
						$arFields = $el->GetFields();
						$arProps = $el->GetProperties();

						$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
						$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
						$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
						$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
						$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
						$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
						$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
						$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
						$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
						$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
						$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
						$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
						if($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE']){
							$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) *intval($arProps['SCP_KB_LEAD']['VALUE'])/100;
						} else {
							$arProp['SUM_SCP_KB_LEAD'] = 0;
						}
						$resultLeads[] = array_merge($arProp, $arFields);
					}

					//Logs\File ::AddMessage($resultLeads, "resultLeads", LOG_API_SYNC_PARTNER_CONTROLLER);
					$result = $resultLeads;
				}
				elseif($statusLead == "Выдан")
				{
					$arFilterDeal = array(
						"IBLOCK_ID" => 167,
						"=PROPERTY_REFERRAL_ID" => $referralId,
						"PROPERTY_STATUS_VALUE" => $statusLead,
						">=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_FROM'],
						"<=PROPERTY_DATA_DOGOVORA_ZAYMA" => $arPeriod['DATE_TO']
					);

					$res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(
							"nTopCount"=>$qty,
							//"nPageSize"=>$qty,
							"nOffset"=>$offset
						),
						$arSelect
					);

					$__res = \CIBlockElement::GetList(
						$arOrder,
						$arFilterDeal,
						false,
						array(),
						['ID']
					);
					while ($row = $__res->Fetch())
					{
						$totalPartnerSellers++;
					}
					if (!$res)
					{
						return [
							'error' => 'ERROR_METHOD_NOT_FOUND',
							'error_description' => 'Method not found! ' . __LINE__,
						];
					}

				}
				else {
					$arFilter = array(
						"::LOGIC" => "AND",
						"IBLOCK_ID" => 167,
						"=PROPERTY_REFERRAL_ID" => $referralId,
						"PROPERTY_STATUS_VALUE" => $statusLead,
						">=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_FROM'],
						"<=PROPERTY_DATE_LEAD" => $arLeadDates['DATE_TO']
					);
					Logs\File ::AddMessage($arFilter, "arFilter", LOG_API_SYNC_PARTNER_CONTROLLER);
					$res = \CIBlockElement::GetList(
						$arOrder,
						$arFilter,
						false,
						array(
							"nTopCount"=>$qty,
							//"nPageSize"=>$qty,
							"nOffset"=>$offset
						),
						$arSelect
					);

					$___res = \CIBlockElement::GetList(
						$arOrder,
						$arFilter,
						false,
						array(),
						['ID']
					);
					while ($row = $___res->Fetch())
					{
						$totalPartnerSellers++;
					}
					if (!$res)
					{
						return [
							'error' => 'ERROR_METHOD_NOT_FOUND',
							'error_description' => 'Method not found! ' . __LINE__,
						];
					}
				}
			}
		}

		if(!empty($result)) {
			$sellers = $result;
		}
        elseif(!empty($res))
		{
			while ($el = $res -> GetNextElement())
			{
				$arFields = $el -> GetFields();
				$arProps = $el -> GetProperties();

				$arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
				$arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
				$arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
				$arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
				$arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
				$arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
				$arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
				$arProp['STATUS'] = $arProps['STATUS']['VALUE'];
				$arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
				$arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
				$arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
				$arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
				if ($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'])
				{
					$arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) * intval($arProps['SCP_KB_LEAD']['VALUE']) / 100;
				} else
				{
					$arProp['SUM_SCP_KB_LEAD'] = 0;
				}
				$result[] = array_merge($arProp, $arFields);
			}


			$sellers = $result;
		}

		foreach ($sellers as $k => $seller)
		{
			$sellerTotalSum = $sellerTotalSum + $seller['SUM_SCP_KB_LEAD'];
		}

		foreach ($sellers as $k => $seller)
		{
			if ($seller["STATUS"] == "Новый") $sellersStatus = "new";
			if ($seller["STATUS"] == "В обработке") $sellersStatus = "in_processed";
			if ($seller["STATUS"] == "Выдан") $sellersStatus = "issued";
			if ($seller["STATUS"] == "Отказ") $sellersStatus = "refused";
			if ($seller["STATUS"] == "") $sellersStatus = "refused";

			$sellersId = $seller["ID"];
			$sellersName = $seller["NAME"];
			$sellersDateLead = $seller["DATE_LEAD"];
			$sellersDateDeal = $seller["DATA_DOGOVORA_ZAYMA"];
			$sellersSumDeal = $seller["SUMMA_PO_DOGOVORU"];
			$sellersSumSCP = $seller["SUM_SCP_KB_LEAD"];

			if (($sellersStatus == "new") || ($sellersStatus == "in_processed") || ($sellersStatus == "refused"))
			{
				$r_res[$k]['status'] = $sellersStatus;
				$r_res[$k]['seller_id'] = $sellersId;
				$r_res[$k]['name'] = null;
				$r_res[$k]['dateLead'] = $sellersDateLead;
				$r_res[$k]['dateDeal'] = null;
				$r_res[$k]['sumDeal'] = null;
				$r_res[$k]['sumSCP'] = null;
			} else
			{
				$r_res[$k]['status'] = $sellersStatus;
				$r_res[$k]['seller_id'] = $sellersId;
				$r_res[$k]['name'] = $sellersName;
				$r_res[$k]['dateLead'] = $sellersDateLead;
				$r_res[$k]['dateDeal'] = $sellersDateDeal;
				$r_res[$k]['sumDeal'] = $sellersSumDeal;
				$r_res[$k]['sumSCP'] = $sellersSumSCP;
			}
		}*/

		$sellerTotalSum = number_format($sellerTotalSum, 2, '.', ' ');

		$objectData['ITEM_TITLE'] = "Получение селлеров от партнера";
		$arPartnerSellers['object'] = "partnerSellers";
		$arPartnerSellers['results'] = $result;
		$arPartnerSellers['sumSCPDeals'] = $sellerTotalSum;

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
		/*Logs\IBlock::setData($url, json_encode($requestArray), $jsonRes, $objectData, $timeData,
			$point, $headersValues);*/
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
}