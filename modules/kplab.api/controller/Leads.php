<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\API\V2\Helpers\HandlerResponse;
use KPLab\Logs;
use Bitrix\Main\Context;

define("LOG_API_LEADS_SYNC_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/logs/LeadsController.log");
define("TOKEN_API_KEY","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1cmwiOiJ0ZXN0Y3JtLnNvZGVpc3R2aWUuc3UiLCJjb250cm9sbGVyIjoiQ2VudGVyT3BlcmF0aW9uRGF0YSJ9.lQO5U4u4PAwQzWoHu9VC_FEJ7eN4fpyGBNHnD1PFtLM");


class Leads extends \Bitrix\Main\Engine\Controller
{

    /**
     *      @OA\Schema(schema="LeadData", type="object",
     *         @OA\Property(property="title", type="string", example="Наименование лида"),
     *         @OA\Property(property="inn", type="string", example="616270066366"),
     *         @OA\Property(property="name", type="string", example="Имя Лида"),
     *         @OA\Property(property="lastName", type="string", example="Фамилия Лида"),
     *         @OA\Property(property="secondName", type="string", example="Отчество Лида (если есть)"),
     *         @OA\Property(property="phone", type="string", example="+71234567890"),
     *         @OA\Property(property="email", type="string", example="mail@mail.ru"),
     *         @OA\Property(property="requestedAmount", type="number", format="float", example=1000000.0),
     *          @OA\Property(property="moneyTurnoverM", type="number", format="float", example=500000.00),
     *          @OA\Property(property="availableLimit", type="number", format="float", example=250000.00),
     *          @OA\Property(property="status", type="boolean", example=false),
     *          @OA\Property(property="term", type="number", format="integer", example=12),
     *         @OA\Property(property="internalId", type="string", example="af01e391-e8ca-4fb7-a899-29a25d9527c5"),
     *      )
     */

    public function getDefaultPreFilters()
    {
        return [
            new ActionFilter\Authentication(),
        ];
    }

    public function getLeadsAction(array $params = []): ?array {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $point = "COD_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $QUERY_STRING = $server['QUERY_STRING'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $serverArray = $server->toArray();
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }
        //Logs\File::AddMessage($serverArray,"serverArray",LOG_API_SYNC_CONTROLLER);
        $recieve = json_decode($request->getInput(),true);

        parse_str($QUERY_STRING, $queryArray);

        if(isset($queryArray['startDate'])) {
            $startDate = date('Y-m-d', strtotime($queryArray['startDate']));
        } else {
            $startDate = null;
        }
        if(isset($queryArray['endDate'])) {
            $endDate = date('Y-m-d', strtotime($queryArray['endDate']));
        } else {
            $endDate = date('Y-m-d', strtotime('now'));
        }
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

        $objectData['ITEM_TITLE'] = "Запрос Лидов: {$startDate} - {$endDate}";

        if(strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must more `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` not must more 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arLeads = [];
        if($startDate === NULL) {
            $strCountLeadsSQL = "SELECT COUNT(*) FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			WHERE DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC;";

            $strLeadSQL = "SELECT * FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			WHERE DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
        } else {
            $strCountLeadsSQL = "SELECT COUNT(*) FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			WHERE (b_crm_lead.DATE_CREATE BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_lead.ID ASC;";

            $strLeadSQL = "SELECT * FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			WHERE (b_crm_lead.DATE_CREATE BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY b_crm_lead.ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
        }

        $resCountLeadsQuery = $DB->query($strCountLeadsSQL);
        while($resCountLeads = $resCountLeadsQuery->Fetch()) {
            $totalLeads = $resCountLeads['COUNT(*)'];
        }

        $arLeads['object'] = (string) "lead";
        $resLeadQuery = $DB->query($strLeadSQL);
        while($resLead = $resLeadQuery->Fetch()) {
            $idLead = (integer) $resLead['ID'];
            $titleLead = $resLead['TITLE'] != "" ? (string) $resLead['TITLE'] : null;
            $nameLead = ($resLead['NAME'] != "") ? (string) $resLead['NAME'] : null;
            $lastNameLead = ($resLead['LAST_NAME'] != "") ? (string) $resLead['LAST_NAME'] : null;
            $secondNameLead = ($resLead['SECOND_NAME'] != "") ? (string) $resLead['SECOND_NAME'] : null;
            $companyTitleLead = ($resLead['COMPANY_TITLE'] != "") ? (string) $resLead['COMPANY_TITLE'] : null;

            $dateLead = date('Y-m-d\TH:i:s.msp', strtotime($resLead['DATE_CREATE']));
            $updateDateLead = date('Y-m-d\TH:i:s.msp', strtotime($resLead['DATE_MODIFY']));

            //region $sourceLead
            $sourceLeadID = $resLead['SOURCE_ID'];
            $strSourceLeadSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID='SOURCE' AND STATUS_ID='{$sourceLeadID}';";
            $resSourceLeadQuery = $DB->query($strSourceLeadSQL);
            while($resSourceLead = $resSourceLeadQuery->Fetch()) {
                $sourceLeadValue = $resSourceLead["NAME"];
            }

            $sourceLead = ["id" => (string) $sourceLeadID,"value" => (string) $sourceLeadValue];
            //endregion

            //region $statusLead
            $statusLeadID = $resLead['STATUS_ID'];
            $strStatusLeadSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID='STATUS' AND STATUS_ID='{$statusLeadID}';";
            $resStatusLeadQuery = $DB->query($strStatusLeadSQL);
            while($resStatusLead = $resStatusLeadQuery->Fetch()) {
                $statusLeadValue = $resStatusLead["NAME"];
            }
            $statusLead = ["id" => (string) $statusLeadID,"value" => (string) $statusLeadValue];
            //endregion

            //region $assignedByLead
            $assignedByLeadID = $resLead['ASSIGNED_BY_ID'];
            $strAssignedByLeadSQL = "SELECT * FROM b_user WHERE ID='{$assignedByLeadID}';";
            $resAssignedByLeadQuery = $DB->query($strAssignedByLeadSQL);
            while($resAssignedByLead = $resAssignedByLeadQuery->Fetch()) {
                //Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
                $assignedByLeadValue = $resAssignedByLead["LAST_NAME"] . " " . $resAssignedByLead["NAME"];
            }
            $assignedByLead = ["id" => (string) $assignedByLeadID,"value" => (string) $assignedByLeadValue];
            //endregion

            $limit = $resLead['UF_CRM_1682140471'];
            $successBuffer = $resLead['UF_CRM_1694261803'];
            $inComingLead = $resLead['UF_CRM_1689230879'];
            $outComingLead = $resLead['UF_CRM_1680996287580'];
            $scpLead = $resLead['UF_CRM_1712636553'];
            $scpLeadDate = $resLead['UF_CRM_1712815273'] === NULL ? NULL : (string) $resLead['UF_CRM_1712815273'];

            //region $scoringResultLead
            $scoringResultLeadID = $resLead['UF_CRM_1681241444'];
            if($scoringResultLeadID !== null) {
                $oUserFieldEnum = new \CUserFieldEnum();
                $rsGender = $oUserFieldEnum::GetList(array(), array(
                    "ID" => $scoringResultLeadID,
                ));
                if($arGender = $rsGender->GetNext()) {
                    $scoringResultValue = $arGender["VALUE"];
                    $scoringResultXML = $arGender["XML_ID"];
                }
                $scoringResultLead = ["id" => (string) $scoringResultXML,"value" => (string) $scoringResultValue];
            } else {
                $scoringResultLead = null;
            }
            //endregion

            $resUF['id'] = $idLead;
            $resUF['title'] = $titleLead;
            $resUF['source'] = (array) $sourceLead;
            $resUF['status'] = (array) $statusLead;
            $resUF['assigned_by'] = (array) $assignedByLead;
            $resUF['name'] = $nameLead;
            $resUF['lastName'] = $lastNameLead;
            $resUF['secondName'] = $secondNameLead;
            $resUF['companyTitle'] = $companyTitleLead;
            $resUF['date'] = (string) $dateLead;
            $resUF['updateDate'] = (string) $updateDateLead;
            $resUF['inComing'] = (bool) $inComingLead;
            $resUF['outComing'] = (bool) $outComingLead;
            $resUF['scp'] = (bool) $scpLead;
            $resUF['scpLeadDate'] = $scpLeadDate;
            $resUF['scoringResult'] = (string) $scoringResultXML;
            $resUF['successBuffer'] = (bool) $successBuffer;
            $resUF['limit'] = (float) $limit;
            //$resUF['target'] = (bool) $resUTSLead['UF_CRM_1689239901741'];
            $arLeads['results'][] = $resUF;

        }

        $totalPages = ceil($totalLeads / $qty);
        $arLeads['total'] = (integer) $totalLeads;
        $arLeads['total_pages'] = (integer) $totalPages;

        if ($offset + $qty >= $totalLeads) {
            $arLeads['has_more'] = false;
        } else {
            $arLeads['has_more'] = true;
        }

        $jsonRes['success'] = $arLeads;
        $jsonRes['error'] = "";
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }

    public function getLeadsByPartnerInnAction(array $params = []): ?array
    {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $point = "PARTNER_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $QUERY_STRING = $server['QUERY_STRING'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $serverArray = $server->toArray();
        foreach ($headers as $key => $header) {
            $headersValues[$header['name']] = $header['values'][0];
        }
        //Logs\File::AddMessage($serverArray,"serverArray",LOG_API_SYNC_CONTROLLER);
        $recieve = json_decode($request->getInput(),true);

        parse_str($QUERY_STRING, $queryArray);

        if(isset($queryArray['startDate'])) {
            $startDate = date('Y-m-d', strtotime($queryArray['startDate']));
        } else {
            $startDate = null;
        }
        if(isset($queryArray['endDate'])) {
            $endDate = date('Y-m-d', strtotime($queryArray['endDate']));
        } else {
            $endDate = date('Y-m-d', strtotime('now'));
        }
        if(isset($queryArray['qty'])) {
            $qty = $queryArray['qty'];
        } else {
            $qty = 50;
        }
        if(isset($queryArray['partnerInn'])) {
            $partnerInn = $queryArray['partnerInn'];
        } else {
            $partnerInn = "";
        }
        if(isset($queryArray['page'])) {
            $offset = ($queryArray['page'] - 1) * $qty;
        } else {
            $offset = 0;
        }

        $objectData['ITEM_TITLE'] = "Запрос Лидов по ИНН партнера : {$startDate} - {$endDate}";

        if(strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must more `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` not must more 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if($partnerInn == "") {
            $errorMessage = "400 Bad Request | `partnerInn` not must empty!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes['success'] = null;
            $jsonRes['error'] = $errorMessage;
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arLeads = [];
        if(is_null($startDate)) {
            $strCountLeadsSQL = "SELECT COUNT(*) FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			INNER JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID
			WHERE b_crm_utm.ENTITY_TYPE_ID = '1' AND b_crm_utm.CODE='UTM_CONTENT' AND b_crm_utm.VALUE='{$partnerInn}' AND DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC;";

            $strLeadSQL = "SELECT * FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			INNER JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID
			WHERE b_crm_utm.ENTITY_TYPE_ID = '1' AND b_crm_utm.CODE='UTM_CONTENT' AND b_crm_utm.VALUE='{$partnerInn}' AND DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
        }
        else {
            $strCountLeadsSQL = "SELECT COUNT(*) FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			INNER JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID
			WHERE b_crm_utm.ENTITY_TYPE_ID = '1' AND b_crm_utm.CODE='UTM_CONTENT' AND b_crm_utm.VALUE='{$partnerInn}' AND DATE(b_crm_lead.DATE_CREATE) >= '{$startDate}' AND DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC;";

            $strLeadSQL = "SELECT * FROM b_crm_lead
			INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID
			INNER JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID
			WHERE b_crm_utm.ENTITY_TYPE_ID = '1' AND b_crm_utm.CODE='UTM_CONTENT' AND b_crm_utm.VALUE='{$partnerInn}' AND DATE(b_crm_lead.DATE_CREATE) >= '{$startDate}' AND DATE(b_crm_lead.DATE_CREATE) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC LIMIT ".$qty." OFFSET ". $offset .";";
        }

        $resCountLeadsQuery = $DB->query($strCountLeadsSQL);
        while($resCountLeads = $resCountLeadsQuery->Fetch()) {
            $totalLeads = $resCountLeads['COUNT(*)'];
        }

        $arLeads['object'] = (string) "lead";
        $resLeadQuery = $DB->query($strLeadSQL);
        while($resLead = $resLeadQuery->Fetch()) {
            $idLead = (integer) $resLead['ID'];
            $internalIdLead = ($resLead['UF_OUT_INTERNALID'] != "") ? (string) $resLead['UF_OUT_INTERNALID'] : null;
            $titleLead = $resLead['TITLE'] != "" ? (string) $resLead['TITLE'] : null;
            $nameLead = ($resLead['NAME'] != "") ? (string) $resLead['NAME'] : null;
            $lastNameLead = ($resLead['LAST_NAME'] != "") ? (string) $resLead['LAST_NAME'] : null;
            $secondNameLead = ($resLead['SECOND_NAME'] != "") ? (string) $resLead['SECOND_NAME'] : null;
            $companyTitleLead = ($resLead['COMPANY_TITLE'] != "") ? (string) $resLead['COMPANY_TITLE'] : null;

            $dateLead = date('Y-m-d\TH:i:s.msp', strtotime($resLead['DATE_CREATE']));
            //$date = new \DateTime(strtotime($resLead['DATE_MODIFY']), new \DateTimeZone('utc'));
            //$updateDateLead = $date->format(\DateTime::ATOM); //Форматируем по ISO 8601
            $updateDateLead = date('Y-m-d\TH:i:s.msp', strtotime($resLead['DATE_MODIFY']));

            //region $sourceLead
            $sourceLeadID = $resLead['SOURCE_ID'];
            $strSourceLeadSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID='SOURCE' AND STATUS_ID='{$sourceLeadID}';";
            $resSourceLeadQuery = $DB->query($strSourceLeadSQL);
            while($resSourceLead = $resSourceLeadQuery->Fetch()) {
                $sourceLeadValue = $resSourceLead["NAME"];
            }

            $sourceLead = ["id" => (string) $sourceLeadID,"value" => (string) $sourceLeadValue];
            //endregion

            //region $statusLeadList
            $statusLeadID = $resLead['UF_CRM_LEAD_STATUS_FOR_PARTNER'];
            $oUserFieldEnum = new \CUserFieldEnum();
            $rsGender = $oUserFieldEnum::GetList(array(), array(
                "ID" => $statusLeadID,
            ));
            if($arGender = $rsGender->GetNext()) {
                $statusLeadValue = $arGender["VALUE"];
                $statusLeadXML = $arGender["XML_ID"];
            }

            if($statusLeadXML == "LEAD_STATUS_0" ) {
                $statusLeadListID = 0;
            }
            elseif($statusLeadXML == "LEAD_STATUS_1") {
                $statusLeadListID = 1;
            }
            elseif ($statusLeadXML == "LEAD_STATUS_2") {
                $statusLeadListID = 2;
            }
            elseif ($statusLeadXML == "LEAD_STATUS_3") {
                $statusLeadListID = 3;
            }

            $statusLeadList = ["id" => (int) $statusLeadListID,"value" => (string) $statusLeadValue];
            //endregion

            //region $assignedByLead
            $assignedByLeadID = $resLead['ASSIGNED_BY_ID'];
            $strAssignedByLeadSQL = "SELECT * FROM b_user WHERE ID='{$assignedByLeadID}';";
            $resAssignedByLeadQuery = $DB->query($strAssignedByLeadSQL);
            while($resAssignedByLead = $resAssignedByLeadQuery->Fetch()) {
                //Logs\File::AddMessage($resAssignedByLead,"resAssignedByLead",LOG_API_SYNC_CONTROLLER);
                $assignedByLeadValue = $resAssignedByLead["LAST_NAME"] . " " . $resAssignedByLead["NAME"];
            }
            $assignedByLead = ["id" => (string) $assignedByLeadID,"value" => (string) $assignedByLeadValue];
            //endregion

            $limit = $resLead['UF_CRM_1682140471'];
            $successBuffer = $resLead['UF_CRM_1694261803'];
            $inComingLead = $resLead['UF_CRM_1689230879'];
            $outComingLead = $resLead['UF_CRM_1680996287580'];
            $scpLead = $resLead['UF_CRM_1712636553'];
            $scpLeadDate = $resLead['UF_CRM_1712815273'] === NULL ? NULL : (string) $resLead['UF_CRM_1712815273'];

            //region $scoringResultLead
            $scoringResultLeadID = $resLead['UF_CRM_1681241444'];
            if($scoringResultLeadID !== null) {
                $oUserFieldEnum = new \CUserFieldEnum();
                $rsGender = $oUserFieldEnum::GetList(array(), array(
                    "ID" => $scoringResultLeadID,
                ));
                if($arGender = $rsGender->GetNext()) {
                    $scoringResultValue = $arGender["VALUE"];
                    $scoringResultXML = $arGender["XML_ID"];
                }
                $scoringResultLead = ["id" => (string) $scoringResultXML,"value" => (string) $scoringResultValue];
            } else {
                $scoringResultLead = null;
            }
            //endregion

            $resUF['id'] = $idLead;
            $resUF['title'] = $titleLead;
            $resUF['lightStatus'] = (array) $statusLeadList;
            $resUF['name'] = $nameLead;
            $resUF['lastName'] = $lastNameLead;
            $resUF['secondName'] = $secondNameLead;
            $resUF['companyTitle'] = $companyTitleLead;
            $resUF['date'] = (string) $dateLead;
            $resUF['updateDate'] = (string) $updateDateLead;
            $resUF['internalId'] = $internalIdLead;
            $arLeads['results'][] = $resUF;

        }

        $totalPages = ceil($totalLeads / $qty);

        // Определяем текущую страницу
        $current_page = isset($queryArray['page']) ? (int) $queryArray['page'] : 1;

        // Определяем количество страниц до и после текущей страницы
        $pages_before = max(0, $current_page - 1);
        $pages_after = max(0, $totalPages - $current_page);

        $arLeads['total'] = (integer) $totalLeads;
        $arLeads['total_pages'] = (integer) $totalPages;
        $arLeads['current_page'] = $current_page;
        $arLeads['pages_before'] = $pages_before;
        $arLeads['pages_after'] = $pages_after;

        if ($offset + $qty >= $totalLeads) {
            $arLeads['has_more'] = false;
        } else {
            $arLeads['has_more'] = true;
        }

        $jsonRes['success'] = $arLeads;
        $jsonRes['error'] = "";
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }

    /**
     *  Добавление Лидов
     */
    /**
     * @OA\Post(path="/leads/",
     *       tags={"Leads"},
     *       summary="Добавление Лидов",
     *       operationId="setLeads",
     *       @OA\Response(
     *           response=200,
     *           description="Успешный ответ"
     *       ),
     *       @OA\RequestBody(ref="#/components/requestBodies/setLeads")
     * )
     *
     */
    /**
     *  @OA\RequestBody(request="setLeads",
     *     description="Новые лиды",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="leads", type="array", @OA\Items(ref="#/components/schemas/LeadData")),
     *           @OA\Property(property="partnerInn", type="string", example="616270066365")
     *        )
     *     )
     *  )
     */
    public function setLeadsAction(array $params = []) {
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
        $this->CURLObjectData['ITEM_TITLE'] = "Результат добавления Лида: ";
        $objectData = $this->CURLObjectData;
        $objectData = [
            'ITEM_ID' => "",
            'ITEM_TITLE' => "Результат добавления Лидов: ",
            'ITEM_TYPE_ID' => \CCrmOwnerType::Lead,
            'INIT_OBJECT_URL' => "",
            'METHOD' => $requestMethod
        ];

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

        Logs\File::AddMessage($requestArray,"setLeads requestArray",LOG_API_LEADS_SYNC_CONTROLLER);
        //region Обработка ошибок
        if($requestArray == NULL) {
            $errorMessage = 'Тело запроса не удалось декодировать как JSON.';
            $objectData['ITEM_TITLE'] = $objectData['ITEM_TITLE'] . $errorMessage;

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }
        if(empty($requestArray['leads'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `leads`';
            $objectData['ITEM_TITLE'] = $objectData['ITEM_TITLE'] . $errorMessage;

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion


        $leadStage = 'UC_102CQU';
        Logs\File::AddMessage($leadStage,"leadStage",LOG_API_LEADS_SYNC_CONTROLLER);
        $this->addLeadToStage($this, $leadStage, $requestArray);
        return $HandlerResponse->handleSuccess(["Лиды успешно добавились"], $objectData);
    }

    public function setLeadsBotAction(array $params = []): string|EventResult {
        $timeData = Logs\TimeData::start();
        $context = Application ::getInstance() -> getContext();
        $request = $context -> getRequest();
        $server = $context -> getServer();
        $serverArray = $server->toArray();
        $serverName = $serverArray['SERVER_NAME'];

        \Bitrix\Main\Loader ::IncludeModule('crm');

        $requestArray = json_decode($request->getInput(),true);

        Logs\File::AddMessage($requestArray,"requestArray",LOG_API_SYNC_CONTROLLER);

        if($requestArray == NULL) {
            Context::getCurrent()->getResponse()->setStatus(400);
            $this -> addError(new Error('Тело запроса не удалось декодировать как JSON.', "invalid_json"));
            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        return "success";
    }

    public function addLeadToStage($controller, $stageId, $requestArray): ?EventResult {
        $entityTypeId = \CCrmOwnerType::Lead;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        foreach ($requestArray['leads'] as $lead) {
            $newItem = $factory->createItem();

            if ($lead['status'] == false){ //не целевой
                $status = 9248;
            }
            else if ($lead['status'] == true){
                $status = 9247;
            }
            else if ($lead['status'] == null){//нет данных
                $status = 11024;
            }

            $fields = [
                'TITLE' => $lead['title'], //Название Лида
                'UF_CRM_1675341358002' => $lead['inn'], //ИНН
                'NAME' => $lead['name'], //Имя Лида
                'LAST_NAME' => $lead['lastName'], //Фамилия Лида
                'SECOND_NAME' => $lead['secondName'], //Отчество Лида (если есть)
                'UF_CRM_1595501723401' => $lead['requestedAmount'], //Запрашиваемая сумма
                'UF_CRM_1681799695541' => $lead['moneyTurnoverM'], //Ежемесячный оборот
                "UF_CRM_1682140471" => $lead['availableLimit'], //Лимит по прескорингу
                "UF_CRM_1681241444" => $status,
                "UF_OUT_INTERNALID" => $lead['internalId'],
                'UTM_CONTENT' => $requestArray['partnerInn']
            ];
            $newItem->setFromCompatibleData($fields);
            $newItem->setStageId($stageId);
            $context = new \Bitrix\Crm\Service\Context();
            $context->setUserId(1);
            $operation = $factory->getAddOperation($newItem, $context);
            $result = $operation->launch();

            if($result->isSuccess()){
                $newId = $newItem->getId();
                if($newId){
                    $MF = new \CCrmFieldMulti;
                    $MF->Add([
                        'ENTITY_ID' => 'LEAD',
                        'ELEMENT_ID' => $newId,
                        'VALUE' => $lead['email'],
                        'TYPE_ID' => 'EMAIL', //PHONE - телефон
                        'VALUE_TYPE' => 'WORK',
                    ]);
                    $MF->Add([
                        'ENTITY_ID' => 'LEAD',
                        'ELEMENT_ID' => $newId,
                        'VALUE' => $lead['phone'],
                        'TYPE_ID' => 'PHONE', //EMAIL - телефон
                        'VALUE_TYPE' => 'WORK',
                    ]);
                }
            }
        }
        return null;
    }

    /**
     *  Добавление Прескоринга Лида
     */

    /**
     * @OA\Post(path="/leads/setPreScoringInfo/",
     *       tags={"Leads"},
     *       summary="Добавление Прескоринга Лида",
     *       operationId="setPreScoringInfo",
     *       @OA\Response(
     *           response=200,
     *           description="Успешный ответ"
     *       ),
     *        @OA\RequestBody(ref="#/components/requestBodies/setPreScoringInfo")
     * )
     *
     */
    /**
     *  @OA\RequestBody(request="setPreScoringInfo",
     *     description="Информация по прескорингу",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *           type="object",
     *           @OA\Property(property="inn", type="string", example="616270066365"),
     *           @OA\Property(property="availableLimit", type="number", format="decimal", example="123456.50"),
     *           @OA\Property(property="status", type="integer", enum={0,1,2}, example=2)
     *        )
     *     )
     *  )
     */
    public function setPreScoringInfoAction(array $params = [])
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
        $this->CURLObjectData['ITEM_TITLE'] = "Результат прескоринга Лида: ";
        $objectData = $this->CURLObjectData;
        $objectData = [
            'ITEM_ID' => "",
            'ITEM_TITLE' => "Результат прескоринга Лида: ",
            'ITEM_TYPE_ID' => \CCrmOwnerType::Lead,
            'INIT_OBJECT_URL' => "",
            'METHOD' => $requestMethod
        ];

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
            $objectData['ITEM_TITLE'] = $objectData['ITEM_TITLE'] . $errorMessage;

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_json", $objectData);
        }
        if(empty($requestArray['inn'])) {
            $errorMessage = 'Этот запрос не поддерживается. Пустой `inn`';
            $objectData['ITEM_TITLE'] = $objectData['ITEM_TITLE'] . $errorMessage;

            return $HandlerResponse->handleError(400, $errorMessage, "invalid_request", $objectData);
        }
        //endregion

        $this->updateLead($this, $requestArray);
        return $HandlerResponse->handleSuccess(["Информация по прескорингу успешно добавилась"], $objectData);
    }
    private function updateLead($controller, $requestArray): void
    {
        $entityTypeId = \CCrmOwnerType::Lead;
        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);

        $filter = [
            'UF_CRM_1732254792' => $requestArray['inn']
        ];

        $itemsLead = $factory->getItems(['filter'=>$filter]);

        foreach ($itemsLead as $item) {
            $item->set('UF_CRM_1682140471',$requestArray['availableLimit']);
            if($requestArray['status'] == 0) $statusId = 9247;
            if($requestArray['status'] == 1) $statusId = 9248;
            if($requestArray['status'] == 2) $statusId = 11024;
            $item->set('UF_CRM_1681241444',$statusId);
            $context = new \Bitrix\Crm\Service\Context();
            $context->setUserId(1);
            $operation = $factory->getUpdateOperation($item, $context);
            $result = $operation->launch();
            if($result->isSuccess()){
                ss_startBp($entityTypeId, $item->getId(), 1587);
            }
        }
    }
}