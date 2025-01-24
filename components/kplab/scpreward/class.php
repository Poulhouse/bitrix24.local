<?
use \Bitrix\Main\Loader;
use \Bitrix\Main\Application;
use KPLab\Logs;



if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

require_once ($_SERVER['DOCUMENT_ROOT'] .'/crest/crest.php');
define("LOG_REPORTS_2", $_SERVER['DOCUMENT_ROOT']."/local/LOG_REPORTS_2.log");
//define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/KPlabReports_2.log");

class KPlabReports_2 extends CBitrixComponent
{

	public static function getReferral($dateFromTo = array(), $empty = false)
	{
        //AddMessage2Log($dateFromTo, "dateFromTo");
        $dateFromLead = strtotime($dateFromTo['FROM']['LEAD']);
        $dateToLead = strtotime($dateFromTo['TO']['LEAD']);
        $dateFromDeal = strtotime($dateFromTo['FROM']['DEAL']);
        $dateToDeal = strtotime($dateFromTo['TO']['DEAL']);

		//AddMessage2Log(["ЛидДата от: ".$dateFromLead, "ЛидДата до: ".$dateToLead], "general");

		$items = \CRest::call('lists.referral.get')['result'];
		//Logs\File::AddMessage($items, "lists.referral.get", LOG_REPORTS_2);

		$result = array();
        $res = array();
		$countR = 0;
        foreach ($items as $k => $item)
        {
        	if($item['ID_REFERRAL'] !== ""):
	            $countLeads = 0;
	            $countDeals = 0;
	            $sellerSumDeal = 0;
	            $sumSCPKB = 0;

	            $sellers = self::getSellerByReferralId($item['ID_REFERRAL'], $dateFromTo);

	            foreach ($sellers as $seller)
	            {
	                $dateLead = strtotime($seller['DATE_LEAD']);
	                //проверка, если дата лида между дат фильтра
		            if(!empty($dateFromTo['FROM']['LEAD']) && !empty($dateFromTo['TO']['LEAD']))
		            {
		                if($dateLead >= $dateFromLead && $dateLead <= $dateToLead)
			            {
				            //дата лида между дат фильтра
				            if (!empty($dateFromTo['FROM']['DEAL']) && !empty($dateFromTo['TO']['DEAL']))
				            {
					            if (strtotime($seller['DATA_DOGOVORA_ZAYMA']) >= $dateFromDeal && strtotime($seller['DATA_DOGOVORA_ZAYMA']) <= $dateToDeal)
					            {
						            $countDeals++;
						            $sellerSumDeal = $sellerSumDeal + intval($seller['SUMMA_PO_DOGOVORU']);
						            $sumSCPKB = $sumSCPKB + intval($seller['SUM_SCP_KB_LEAD']);
						            $countLeads++;
					            }
				            }
				            elseif(empty($dateFromTo['FROM']['DEAL']) && empty($dateFromTo['TO']['DEAL'])) {
					            if($seller['DATA_DOGOVORA_ZAYMA'] !== '') {
					                $sellerSumDeal = $sellerSumDeal + intval($seller['SUMMA_PO_DOGOVORU']);
						            $sumSCPKB = $sumSCPKB + intval($seller['SUM_SCP_KB_LEAD']);
						            $countDeals++;
					            }
					            $countLeads++;
				            }
			            }
		            }
		            elseif(empty($dateFromTo['FROM']['LEAD']) && empty($dateFromTo['TO']['LEAD']))
		            {
			            //AddMessage2Log($seller['DATE_LEAD']." дата лида между дат фильтра ОТСУТСТВУЕТ", "seller");
			            //иначе, проверяем есть ли договор
			            if($dateFromDeal && $dateToDeal && strtotime($seller['DATA_DOGOVORA_ZAYMA']) >= $dateFromDeal && strtotime($seller['DATA_DOGOVORA_ZAYMA']) <= $dateToDeal) {
				            $sellerSumDeal = $sellerSumDeal + intval($seller['SUMMA_PO_DOGOVORU']);
				            $sumSCPKB = $sumSCPKB + intval($seller['SUM_SCP_KB_LEAD']);
				            $countDeals++;
				            $countLeads++;
			            } else {
				            $sellerSumDeal = $item['SUM_DEALS'];
				            $countDeals = intval($item['COUNT_DEALS']);
				            $countLeads = intval($item['COUNT_LEADS']);
				            $sumSCPKB = $item['SUM_SCP_KB'];
			            }
		            }
	            }

	            if($empty) {
	                if($countDeals !== 0) {
	                    $result[$countR]['ID_REFERRAL'] = $item['ID_REFERRAL'];
		                $result[$countR]['ID_DEAL_REFERRAL'] = $item['ID_SDELKI_SCP'];
	                    $result[$countR]['SCP_KB'] = $item['SCP_KB'];
	                    $result[$countR]['NAME'] = $item['NAME'];
	                    $result[$countR]['INN_REFERRAL'] = $item['INN_REFERRAL'];
	                    $result[$countR]['ASSIGN'] = $item['ASSIGN'];
	                    $result[$countR]['SUM_DEALS'] = $sellerSumDeal;
	                    $result[$countR]['COUNT_DEALS'] = $countDeals;
	                    $result[$countR]['COUNT_LEADS'] = $countLeads;
	                    $result[$countR]['SUM_SCP_KB'] = $sumSCPKB;

		                Logs\File::AddMessage($result[$countR], "result[{$countR}]", LOG_REPORTS_2);
		                $countR++;
	                }
	            }
	            else {
	                $result[$k]['ID_REFERRAL'] = $item['ID_REFERRAL'];
		            $result[$k]['ID_DEAL_REFERRAL'] = $item['ID_SDELKI_SCP'];
	                $result[$k]['SCP_KB'] = $item['SCP_KB'];
	                $result[$k]['NAME'] = $item['NAME'];
	                $result[$k]['INN_REFERRAL'] = $item['INN_REFERRAL'];
	                $result[$k]['ASSIGN'] = $item['ASSIGN'];
	                $result[$k]['SUM_DEALS'] = $sellerSumDeal;
	                $result[$k]['COUNT_DEALS'] = $countDeals;
	                $result[$k]['COUNT_LEADS'] = $countLeads;
	                $result[$k]['SUM_SCP_KB'] = $sumSCPKB;
	            }
            endif;
        }

        $res = $result;
/*
		if($datevidachiFrom !== '' && $datevidachiTo !== '') {



			foreach ($items as $k => $item)
			{
				$countLeads = 0;
				$countDeals = 0;
				$sellerSumDeal = 0;
				$sumSCPKB = 0;

				$sellers = self::getSellerByReferralId($item['ID_REFERRAL'],$datevidachiFrom, $datevidachiTo);

				foreach ($sellers as $seller)
				{
					$sellerSumDeal = $sellerSumDeal + intval($seller['SUMMA_PO_DOGOVORU']);
					$sumSCPKB = $sumSCPKB + intval($seller['SUM_SCP_KB_LEAD']);
					$countLeads++;
					$countDeals++;
				}

				if($empty) {
					if($countDeals !== 0) {
						$result[$k]['ID_REFERRAL'] = $item['ID_REFERRAL'];
						$result[$k]['SCP_KB'] = $item['SCP_KB'];
						$result[$k]['NAME'] = $item['NAME'];
						$result[$k]['INN_REFERRAL'] = $item['INN_REFERRAL'];
						$result[$k]['ASSIGN'] = $item['ASSIGN'];
						$result[$k]['SUM_DEALS'] = $sellerSumDeal;
						$result[$k]['COUNT_DEALS'] = $countDeals;
						$result[$k]['COUNT_LEADS'] = $countLeads;
						$result[$k]['SUM_SCP_KB'] = $sumSCPKB;
					}
				} else {
					$result[$k]['ID_REFERRAL'] = $item['ID_REFERRAL'];
					$result[$k]['SCP_KB'] = $item['SCP_KB'];
					$result[$k]['NAME'] = $item['NAME'];
					$result[$k]['INN_REFERRAL'] = $item['INN_REFERRAL'];
					$result[$k]['ASSIGN'] = $item['ASSIGN'];
					$result[$k]['SUM_DEALS'] = $sellerSumDeal;
					$result[$k]['COUNT_DEALS'] = $countDeals;
					$result[$k]['COUNT_LEADS'] = $countLeads;
					$result[$k]['SUM_SCP_KB'] = $sumSCPKB;
				}
			}
			$res = $result;
		} else {
			if($empty) {
				foreach ($items as $k => $item)
				{


					if(intval($item['COUNT_DEALS']) !== 0){
						//print_r($item['COUNT_DEALS']);
						$result[$k]['ID_REFERRAL'] = $item['ID_REFERRAL'];
						$result[$k]['SCP_KB'] = $item['SCP_KB'];
						$result[$k]['NAME'] = $item['NAME'];
						$result[$k]['INN_REFERRAL'] = $item['INN_REFERRAL'];
						$result[$k]['ASSIGN'] = $item['ASSIGN'];
						$result[$k]['SUM_DEALS'] = $item['SUM_DEALS'];
						$result[$k]['COUNT_DEALS'] = $item['COUNT_DEALS'];
						$result[$k]['COUNT_LEADS'] = $item['COUNT_LEADS'];
						$result[$k]['SUM_SCP_KB'] = $item['SUM_SCP_KB'];
					}
				}
				$res = $result;
			} else {
				$res = $items;
			}
		}
*/
		return $res;
	}
	public static function getSellerByReferralId($referralId, $dateFromTo = array()) {
		if($referralId == "") return [];

		$res = array();

        $dateFromLead = strtotime($dateFromTo['FROM']['LEAD']);
        $dateToLead = strtotime($dateFromTo['TO']['LEAD']);
        $dateFromDeal = strtotime($dateFromTo['FROM']['DEAL']);
        $dateToDeal = strtotime($dateFromTo['TO']['DEAL']);


        /*
       */
		$arParams = array('referralId' => $referralId);

		$items = CRest::call('lists.seller.get',$arParams)['result'];

		foreach ($items as $item) {

			$dateLead = strtotime($item['DATE_LEAD']);

			//проверка, если дата лида между дат фильтра
			if(!empty($dateFromTo['FROM']['LEAD']) && !empty($dateFromTo['TO']['LEAD']))
			{
				if($dateLead >= $dateFromLead && $dateLead <= $dateToLead)
				{
					//дата лида между дат фильтра

					if (!empty($dateFromTo['FROM']['DEAL']) && !empty($dateFromTo['TO']['DEAL']))
					{
						if (strtotime($item['DATA_DOGOVORA_ZAYMA']) >= $dateFromDeal && strtotime($item['DATA_DOGOVORA_ZAYMA']) <= $dateToDeal)
						{
							$res[] = $item;
						}
					} elseif(empty($dateFromTo['FROM']['DEAL']) && empty($dateFromTo['TO']['DEAL'])) {
						if($item['DATA_DOGOVORA_ZAYMA'] !== '') {
							$res[] = $item;
						} elseif($item['DATA_DOGOVORA_ZAYMA'] == '') {
							$res[] = $item;
						}
					}
				}
			} elseif(empty($dateFromTo['FROM']['LEAD']) && empty($dateFromTo['TO']['LEAD']))
			{
				//иначе, проверяем есть ли договор
				if(!empty($dateFromTo['FROM']['DEAL']) && !empty($dateFromTo['TO']['DEAL']) && strtotime($item['DATA_DOGOVORA_ZAYMA']) >= $dateFromDeal && strtotime($item['DATA_DOGOVORA_ZAYMA']) <= $dateToDeal) {
					$res[] = $item;
				} elseif(empty($dateFromTo['FROM']['DEAL']) && empty($dateFromTo['TO']['DEAL'])) {
					if($item['DATA_DOGOVORA_ZAYMA'] !== '') {
						$res[] = $item;
					} elseif($item['DATA_DOGOVORA_ZAYMA'] == '') {
						$res[] = $item;
					}
				}
			}
		}

		return $res;
	}

	public function executeComponent()
	{
		$this->arResult['referrals'] = "";
		$this -> IncludeComponentTemplate();
	}
}