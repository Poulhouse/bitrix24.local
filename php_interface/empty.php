<?php
/*
    public static function getLeadsSeller($referralINN){
        $sumDZ = 0;
        $resultLeadList__ = CRest::call('crm.lead.list', array(
            "order" => ["ID"=>"ASC"],
            "filter" =>["STATUS_ID"=>"CONVERTED", "UTM_MEDIUM" => "referral", "UTM_CONTENT"=>$referralINN],
            "select" => ["STATUS_ID", "COMPANY_ID", "CONTACT_ID", "UTM_CONTENT", "DATE_CREATE"]
        ));

        $resultLeadList1 = $resultLeadList__['result'];
        if ($resultLeadList__['total'] > 50)
        {
            $ik = 50;
            while ($ik < $resultLeadList__['total'])
            {
                $res_x = CRest ::call('crm.lead.list', [
                    "filter" =>["STATUS_ID"=>"CONVERTED", "UTM_MEDIUM" => "referral", "UTM_CONTENT"=>$referralINN],
                    "select" => ["STATUS_ID", "COMPANY_ID", "CONTACT_ID", "UTM_CONTENT", "DATE_CREATE"],
                    'start' => $ik
                ]);
                $resultLeadList1 = array_merge($resultLeadList1, $res_x['result']);
                $ik = $ik + 50;
            }
        }
        //AddMessage2Log($resultLeadList1,"resultLeadList1");

        $countL = 1;
        foreach ($resultLeadList1 as $k => $resultLeadItem)
        {

            $company__ = CRest ::call('crm.company.get', array("id" => $resultLeadItem['COMPANY_ID']));
            //AddMessage2Log($company__,"company__");
			//AddMessage2Log($resultLeadItem,"resultLeadItem");

            $seller = self::getSellerByReferralINN($referralINN);
			//AddMessage2Log($seller,"seller2");

            if($seller) {
                $DZ = self::getSellerDZbyID($seller['UF_CRM_1684985932']);
				//AddMessage2Log($DZ,"DZ2");
                $sumDZ = str_replace('|RUB','', $DZ['ufCrm15SsSummadogovora']);

                $arr2['NAME'] = $company__['result']['TITLE'];    //$arr['NAME_REFERRAL_2'] =
                // $contact['result']['TITLE'];
                $arr2['PROPERTY_895'] = $company__['result']['ID'];
                $arr2['PROPERTY_896'] = $referralINN;
                $arr2['PROPERTY_902'] = $countL; //Количество лидов
                $arr2['PROPERTY_903'] = $company__['result']['UF_CRM_6442676E5B421']; //SCP_KB
                $arr2['PROPERTY_904'] = $sumDZ * $company__['result']['UF_CRM_6442676E5B421']/100; //SUM_SCP_KB
                $arr2['PROPERTY_898'] = $company__['result']['ASSIGNED_BY_ID'];
                $arr2['PROPERTY_897'] = $resultLeadItem['DATE_CREATE']; //DATE_LEAD
                $arr2['PROPERTY_906'] = $DZ['ufCrm15SsNomer']; //NOMER_DOGOVORA
                $arr2['PROPERTY_907'] = date('d.m.Y', strtotime($DZ['ufCrm15_1679925201'])); //DATA_DOGOVORA
                $arr2['PROPERTY_905'] = $sumDZ; //SUMMA_SDELKI
            } else {
                $sumDZ = 0;

                $arr2['NAME'] = $company__['result']['TITLE'];    //$arr['NAME_REFERRAL_2'] = $contact['result']['TITLE'];
                $arr2['PROPERTY_895'] = $company__['result']['ID'];
                $arr2['PROPERTY_896'] = $referralINN;
                $arr2['PROPERTY_902'] = $countL; //Количество лидов
                $arr2['PROPERTY_903'] = $company__['result']['UF_CRM_6442676E5B421']; //SCP_KB
                $arr2['PROPERTY_904'] = 0; //SUM_SCP_KB
                $arr2['PROPERTY_898'] = $company__['result']['ASSIGNED_BY_ID'];
                $arr2['PROPERTY_897'] = $resultLeadItem['DATE_CREATE']; //DATE_LEAD
                $arr2['PROPERTY_906'] = "-"; //NOMER_DOGOVORA
                $arr2['PROPERTY_907'] = "-"; //DATA_DOGOVORA
                $arr2['PROPERTY_905'] = $sumDZ; //SUMMA_SDELKI
            }
            AddMessage2Log($arr2,"arr2 LeadItem");




            $paramsSearch = array(
                'IBLOCK_TYPE_ID' => 'lists',
                'IBLOCK_ID' => 166,
                'ELEMENT_CODE' => 'lead_' . $resultLeadItem['ID']
            );
            $params = array(
                'IBLOCK_TYPE_ID' => 'lists',
                'IBLOCK_ID' => 166,
                'ELEMENT_CODE' => 'lead_' . $resultLeadItem['ID'], //символьный код элемента
                'FIELDS' => $arr2
            );

            $resSearch = CRest ::call('lists.element.get', $paramsSearch);

            if ($resSearch['total'])
            {
                $res = CRest ::call('lists.element.update', $params);
            } else
            {
                $res = CRest ::call('lists.element.add', $params);
            }
            $countL++;
        }


        return $resultLeadList1;
    }
   */