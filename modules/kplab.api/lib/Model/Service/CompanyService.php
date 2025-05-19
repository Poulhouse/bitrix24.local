<?php
namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\Logs;

define("LOG_COMPANY_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_company.log");
class CompanyService
{
    private const TYPE_FL = 'FL';
    private const TYPE_IP = 'IP';
    private const TYPE_ORG = 'ORG';
    private const DA_DATA_TOKEN = '440b60bed73f6e0d78a0eb09ca91971f8c079590';
    private HttpClientInterface $http;
    private string $method;
    private string $endpoint;

    public function __construct(
        HttpClientInterface $httpClient,
        string $method,
        string $endpoint = 'https://ak.seller-capital.ru/api/hs/api/update'
    ) {
        $this->http     = $httpClient;
        $this->method = $method;
        $this->endpoint = $endpoint;
    }

    /**
     * Собирает payload и отправляет его на внешний сервис.
     *
     * @param array $companyData — локальные данные компании (id, externalUuid и т.п.)
     * @param string $externalSystem — внешняя система
     * @return array|null — распарсенный ответ от внешнего API
     * @throws LoaderException
     * @throws \DateMalformedStringException
     */
    public function sync(array $companyData, string $externalSystem): ?array
    {
        $companyId = $companyData['ID'];
        $payload = $this->buildPayload($companyData, $externalSystem);
        if ($externalSystem == 'onec') {
            $meta['objectData']['ITEM_TITLE'] = "Отправка карточки компании #{$companyId} в 1С";
            $meta['partnerName'] = "1С:АК-Кредит";
        }
        $meta['objectData']['INIT_OBJECT_URL'] = "https://testcrm.seller-capital.ru/crm/type/4/details/{$companyId}/";
        $meta['methodName'] = __FUNCTION__;
        $meta['controllerName'] = get_class($this);


        Logs\File::AddMessage($meta,"meta", LOG_COMPANY_SERVICE);

        $response = $this->http->request(
            $this->method,
            $this->endpoint,
            [
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'Accept-Charset' => 'UTF-8',
            ],
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $meta
        );

        $body = json_decode($response['body'],true);
        $syncResultCode = $body["syncResultCode"];
        if($syncResultCode !== 200) {
            $response['status'] = $syncResultCode;
        }

        if ($externalSystem == 'onec') {
            $this->saveOnecUUID($companyId, $response);
        }

        return $response;
    }

    /**
     * Строит структуру полезной нагрузки по данным компании.
     * @throws \DateMalformedStringException
     * @throws LoaderException
     */
    private function buildPayload(array $data, string $externalSystem): array
    {
        $orgStructure = $this->getOrgStructure($data);
        Logs\File::AddMessage($orgStructure,"ОргСтруктура", LOG_COMPANY_SERVICE);

        $requisite = $this->loadRequisite($data['ID']);
        Logs\File::AddMessage($requisite,"Реквизиты", LOG_COMPANY_SERVICE);

        $addressDetails = $this->loadAddressDetails($data['ID']);
        Logs\File::AddMessage($addressDetails,"Адреса", LOG_COMPANY_SERVICE);

        $contactDetails = $this->loadContactDetails($data['ID']);
        Logs\File::AddMessage($contactDetails,"Контактные данные", LOG_COMPANY_SERVICE);

        $contactPersonDetails = $this->loadContactPersonDetails($data['ID']);
        Logs\File::AddMessage($contactPersonDetails,"Контактные лица", LOG_COMPANY_SERVICE);

        $bankDetails = $this->loadBankDetails($data['ID']);
        Logs\File::AddMessage($bankDetails,"Расчетные счета", LOG_COMPANY_SERVICE);

        $bankNominalDetails = $this->loadBankNominalDetails($data['ID']);
        Logs\File::AddMessage($bankNominalDetails,"Номинальные счета", LOG_COMPANY_SERVICE);

        $beneficialOwnersDetails = $this->loadBeneficialOwnersDetails($data['ID']);
        Logs\File::AddMessage($beneficialOwnersDetails,"Бенефициарные владельцы", LOG_COMPANY_SERVICE);

        $payload = [];

        if ($externalSystem == 'onec') {
            $payload = $this->buildOnecPayload(
                $orgStructure,
                $data,
                $requisite[0]??[],
                $addressDetails,
                $contactDetails,
                $contactPersonDetails,
                $bankDetails,
                $bankNominalDetails,
                $beneficialOwnersDetails
            );

            Logs\File::AddMessage($payload,"buildOnecPayload", LOG_COMPANY_SERVICE);
        }

        return $payload;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function buildOnecPayload(
        string $org,
        array  $company,
        array  $rq,
        array  $addrDetails,
        array  $contactDetails,
        array  $contactPersonDetails,
        array  $bankDetails,
        array  $bankNominalDetails,
        array  $beneficialOwnersDetails
    ): array
    {
        $syncObject = [
            'id'     => $company['UF_CRM_COMPANY_SS_AM_ID'] ?? '',
            'limitSum'     => floatval(str_replace("|RUB", '', $company['UF_CRM_1697107946'])) ?? floatval(0),
            'taxNum' => $rq['RQ_INN'] ?? '',
        ];
        if ($org === self::TYPE_ORG) {
            $representativeCrmId = $company['UF_CRM_1615200179'];

            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);

            if(!is_null($representativeCrmId)) {
                if (str_contains($representativeCrmId, 'CO_')) {
                    $representativeCrmId = str_replace("CO_", '', $representativeCrmId);
                    $representativeItem = $factoryCompany->getItem($representativeCrmId);
                    $representative = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                }
                elseif(str_contains($representativeCrmId, 'C_')) {
                    $representativeCrmId = str_replace("C_", '', $representativeCrmId);
                    $representativeItem = $factoryContact->getItem($representativeCrmId);
                    $representative = $representativeItem->get('UF_CRM_CONTACT_SS_FL_AM_ID') ?? '';
                }
                else {
                    $representativeItem = $factoryCompany->getItem($representativeCrmId);
                    $representative = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                }

                $representativeTitle = $representativeItem->get('TITLE') ?? '';
                if ($representative == "") {
                    $message = "[b]Ошибка при отправке данных[/b]\nРуководитель {$representativeTitle} #{$representativeCrmId} не существует в 1С\nСначала засинхронизируйте,\nпрежде чем будет доступен для отправки в 1С!";
                    \CRest::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $company['ID'],
                            "ENTITY_TYPE" => "COMPANY",
                            "COMMENT" => "{$message}"
                        ]
                    ]);
                }
            }

            $syncObject += [
                'typeId' => 2,
                'name' => $rq['RQ_COMPANY_NAME'],
                'fullName' => $rq['RQ_COMPANY_FULL_NAME'],
                'shortName' => $rq['RQ_COMPANY_NAME'],
                'regNum' => $rq['RQ_OGRN'],
                'regDate' => $this->normalizeDate($rq['RQ_COMPANY_REG_DATE'] ?? ''),
                'regNumOrg' => $rq['UF_CRM_1688964741'] ?? '',
                'kpp' => $rq['RQ_KPP'] ?? '',
                'okpo' => $rq['RQ_OKPO'] ?? ''
            ];
            if(!empty($representative)) $syncObject += ['representative' => $representative];
            if(!empty($beneficialOwnersDetails)) $syncObject += ['beneficialOwnersDetails' => $beneficialOwnersDetails];
        }
        elseif ($org === self::TYPE_FL) {
            $syncObject += [
                'typeId' => 1,
                'lastName'   => $rq['RQ_LAST_NAME']      ?? '',
                'firstName'  => $rq['RQ_FIRST_NAME']     ?? '',
                'middleName' => $rq['RQ_SECOND_NAME']    ?? '',
                'birthDate'  => $this->normalizeDate($rq['UF_CRM_1684493639'] ?? ''),
                'birthPlace' => $rq['UF_CRM_1647929611'] ?? '',
                'socialNum'  => $rq['UF_CRM_1684476607'] ?? '',
                'docType'    => $this->mapDocType($rq['RQ_IDENT_DOC'] ?? ''),
                'docSeries'  => $rq['RQ_IDENT_DOC_SER']  ?? '',
                'docNumber'  => $rq['RQ_IDENT_DOC_NUM']  ?? '',
                'docIssueDate'  => $this->normalizeDate($rq['RQ_IDENT_DOC_DATE'] ?? ''),
                'docIssuerText' => $rq['RQ_IDENT_DOC_ISSUED_BY'] ?? '',
                'docDeptCode'   => $rq['RQ_IDENT_DOC_DEP_CODE']  ?? '',
                'regMark' => 0,
                'regNum' => ''
            ];
        }
        elseif ($org === self::TYPE_IP) {
            $syncObject += [
                'typeId' => 1,
                'lastName'   => $rq['RQ_LAST_NAME']      ?? '',
                'firstName'  => $rq['RQ_FIRST_NAME']     ?? '',
                'middleName' => $rq['RQ_SECOND_NAME']    ?? '',
                'birthDate'  => $this->normalizeDate($rq['UF_CRM_1684493639'] ?? ''),
                'birthPlace' => $rq['UF_CRM_1647929611'] ?? '',
                'socialNum'  => $rq['UF_CRM_1684476607'] ?? '',
                'docType'    => $this->mapDocType($rq['RQ_IDENT_DOC'] ?? ''),
                'docSeries'  => $rq['RQ_IDENT_DOC_SER']  ?? '',
                'docNumber'  => $rq['RQ_IDENT_DOC_NUM']  ?? '',
                'docIssueDate'  => $this->normalizeDate($rq['RQ_IDENT_DOC_DATE'] ?? ''),
                'docIssuerText' => $rq['RQ_IDENT_DOC_ISSUED_BY'] ?? '',
                'docDeptCode'   => $rq['RQ_IDENT_DOC_DEP_CODE']  ?? '',
                'regMark' => 1,
                'regNum' => $rq['RQ_OGRNIP'] ?? '',
                'regDate' => $this->normalizeDate($rq['RQ_COMPANY_REG_DATE'] ?? ''),
                'regNumOrg' => $rq['UF_CRM_1688964741'] ?? ''
            ];
        }

        if(!empty($addrDetails)) $syncObject += ['addressDetails' => $addrDetails];
        if(!empty($contactDetails)) $syncObject += ['contactDetails' => $contactDetails];
        if(!empty($contactPersonDetails)) $syncObject += ['contactPersonDetails' => $contactPersonDetails];
        if(!empty($bankDetails)) $syncObject += ['bankDetails' => $bankDetails];
        if(!empty($bankNominalDetails)) $syncObject += ['bankNominalDetails' => $bankNominalDetails];

        return [
            'syncFormat'  => 1,
            'syncObjects' => [$syncObject],
        ];
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function normalizeDate(?string $value): ?string
    {
        return $value ? (new \DateTime($value))->format('d.m.Y') : null;
    }
    private function mapDocType(string $identDoc): int
    {
        return match ($identDoc) {
            'Паспорт гражданина Российской Федерации' => 21,
            'Свидетельство о рождении гражданина Российской Федерации' => 27,
            default => 31,
        };
    }
    private function getOrgStructure(array $data)
    {
        $idClientType = $data['UF_CRM_1684145100226'];
        $ar_UserFieldList = [];
        $userFields = \Bitrix\Main\UserFieldTable::getList([
            'select' => ['*'],
            'filter' => [
                '=ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => 'UF_CRM_1684145100226'
            ]
        ]);
        while ($arUserField = $userFields->fetch()){
            if($arUserField['USER_TYPE_ID'] == 'enumeration')
            {
                $arUserFieldList = \CUserFieldEnum::GetList([], ['ID' => $idClientType, 'USER_FIELD_ID' => $arUserField['ID']]);
                if($arUserFieldItem = $arUserFieldList->Fetch()) {
                    return $arUserFieldItem['XML_ID'];
                }
            }
        }
        return 'FL';
    }
    private function loadRequisite(int $companyId): array
    {
        $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
            'filter'=> ["ENTITY_ID" => $companyId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
            'select' => ['*','UF_*']
        ]);

        return $requisiteResult->fetchAll();
    }
    private function loadAddressDetails(int $companyId): array
    {
        $resAddrList = \CRest::call('crm.address.list', array(
            'filter' => array('ANCHOR_ID' => $companyId, 'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company),
            'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','ANCHOR_TYPE_ID','LOC_ADDR_ID')
        ))['result'];

        $Address = new \Bitrix\Location\Controller\Address;
        $addressDetails = [];

        foreach($resAddrList as $i => $addrItem){

            $addrItemTypeId = $addrItem['TYPE_ID'];
            //Адрес регистрации
            if($addrItemTypeId == 4) $addressDetails[$i]['addressType'] = 1;

            //Адрес фактического места жительства/Адрес фактический
            if($addrItemTypeId == 1) $addressDetails[$i]['addressType'] = 2;

            //Адрес юридический
            if($addrItemTypeId == 6) $addressDetails[$i]['addressType'] = 1;

            $LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];

            $addrId = $Address->findById($LOC_ADDR_ID);
            $resAddress[$i] = $addrId['fieldCollection'];
            $addressFiasId = $resAddress[$i][900];

            $http = new HttpClient();
            $http->setHeader('Content-Type', 'application/json');
            $http->setHeader('Accept', 'application/json');
            $http->setHeader('Authorization', 'Token 440b60bed73f6e0d78a0eb09ca91971f8c079590');
            $requestBody = ['query' => $addressFiasId];
            $http->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address", json_encode($requestBody));

            $responseJson = $http->getResult();
            $responseArray = json_decode($responseJson, true);
            $addressData = $responseArray['suggestions'][0]['data'];

            $addressKLADRID      = $addressData['kladr_id'] ?? '';
            $addressCity      = $addressData['city'] ?? ($addressData['settlement'] ?? '');
            $addressFlat      = $addressData['flat'] ?? '';
            $addressHouse     = $addressData['house'] ?? '';
            $addressRegion    = $addressData['region'] ?? '';
            $addressDistrict  = $addressData['city_district'] ?? '';
            $addressStreet    = $addressData['street'] ?? '';
            $block_type_full  = $addressData['block_type_full'] ?? '';
            $block  = $addressData['block'] ?? '';
            $addressCountry   = $addressData['country'] ?? '';
            $addressPostalCode= $addressData['postal_code'] ?? '';
            $addressLocationCode = $addressData['okato'] ?? '';
            $addressBlock = "";
            $addressBuild = "";

            // Формируем массив адреса для добавления в реквизиты

            if($block_type_full == 'корпус') {
                $addressBlock  = $block;
            }
            if($block_type_full == 'строение') {
                $addressBuild  = $block;
            }


            $addressDetails[$i]['postCode'] = $addressPostalCode;
            $addressDetails[$i]['countryCode'] = 643;
            $addressDetails[$i]['regStateNum'] = $addressKLADRID;
            $addressDetails[$i]['locationCode'] = $addressLocationCode;
            $addressDetails[$i]['province'] = $addressRegion; //Область / Регион
            $addressDetails[$i]['location'] = $addressCity; //Город / Населенный пункт
            $addressDetails[$i]['street'] = $addressStreet;
            $addressDetails[$i]['house'] = $addressHouse;
            $addressDetails[$i]['block'] = $addressBlock;
            $addressDetails[$i]['build'] = $addressBuild;
            $addressDetails[$i]['apart'] = $addressFlat;
        }
        return $addressDetails;
    }
    private function loadContactDetails(int $companyId): array
    {
        $contactDetails = [];

        $resFieldMulti = \CCrmFieldMulti::GetListEx(
            [],
            [
                'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Company),
                'ELEMENT_ID' => $companyId,
                'TYPE_ID' => ['PHONE', 'EMAIL'] // Фильтруем только нужные типы
            ]
        );

        $valueTypeMap = [
            'MOBILE' => 1,
            'WORK' => 2,
            'HOME' => 3
        ];

        $typeIdMap = [
            'PHONE' => 1,
            'EMAIL' => 2
        ];

        while ($multifaceted = $resFieldMulti->fetch()) {
            if (!isset($valueTypeMap[$multifaceted['VALUE_TYPE']]) || !isset($typeIdMap[$multifaceted['TYPE_ID']])) {
                continue;
            }

            $contactDetails[] = [
                'typeId' => $typeIdMap[$multifaceted['TYPE_ID']],
                'valueType' => $valueTypeMap[$multifaceted['VALUE_TYPE']],
                'valueText' => $multifaceted['VALUE'],
                'commentText' => $multifaceted['ENTITY_ID'] . '_' . $multifaceted['ELEMENT_ID']
            ];
        }

        return $contactDetails;
    }
    private function loadBankDetails(int $companyId): array
    {
        $bankDetails = [];

        $rqId = $this->loadRequisite($companyId)[0]['ID'];

        $BankDetailResult = \Bitrix\Crm\BankDetailTable::getList([
            'filter'=> [
                "ENTITY_ID" => $rqId,
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                'NAME' => 'Расчетный счет'
            ],
            'select' => ['*','UF_*'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1
        ]);

        $bankDetails = $this->extractedBankDetails($BankDetailResult, $bankDetails);
        Logs\File::AddMessage($bankDetails,"bankDetails", LOG_COMPANY_SERVICE);

        return $bankDetails;
    }
    private function loadBankNominalDetails(int $companyId): array
    {
        $bankNominalDetails = [];

        $rqId = $this->loadRequisite($companyId)[0]['ID'];

        $BankDetailResult = \Bitrix\Crm\BankDetailTable::getList([
            'filter'=> [
                "ENTITY_ID" => $rqId,
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                "NAME" => "Номинальный счет"
            ],
            'select' => ['*','UF_*'],
            'limit' => 1
        ]);

        $bankNominalDetails = $this->extractedBankDetails($BankDetailResult, $bankNominalDetails);
        Logs\File::AddMessage($bankNominalDetails,"bankNominalDetails", LOG_COMPANY_SERVICE);

        return $bankNominalDetails;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws LoaderException
     */
    private function loadContactPersonDetails(int $companyId): array
    {
        $contactPersonDetails = [];

        Loader::includeModule('iblock');
        $IBLOCK_ID = 179;
        $prefix = "CO_";
        $arFilter = ["IBLOCK_ID" => $IBLOCK_ID,	"=PROPERTY_1024_VALUE" => $prefix.$companyId, "ACTIVE_DATE" => "Y", "ACTIVE"=>"Y"];
        $arSelect = ["*","PROPERTY_*"];
        $res = \CIBlockElement::GetList(['ID' => 'ASC'], $arFilter, false, [], $arSelect);

        while($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            $arProps = $ob->GetProperties();

            $contactPersonDetails[] = [  // собираем массив того, что нам нужно
                'nameText' => $arFields['NAME'],
                'birthDate' => $this->normalizeDate($arProps['DATA_ROZHDENIYA']['VALUE'] ?? ''),
                'phone' => $arProps['TELEFON']['VALUE'],
                'stateId' => $arProps['STATUS']['VALUE_XML_ID'],
                'commentText' => $arProps['KOMMENTARIY']['VALUE'],
            ];
        }
        return $contactPersonDetails;
    }
    private function loadBeneficialOwnersDetails(int $companyId): array
    {
        $beneficialOwnersDetails = [];
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);
        $companyItem = $factoryCompany->getItem($companyId);
        $mapUFOwners = [
            'UF_CRM_1702272911' => 'UF_CRM_1702274893', //owner 1 => proportion 1
            'UF_CRM_1702272991' => 'UF_CRM_1702274922', //owner 2 => proportion 2
            'UF_CRM_1702273016' => 'UF_CRM_1702274951', //owner 3 => proportion 3
            'UF_CRM_1702273043' => 'UF_CRM_1702274976', //owner 4 => proportion 4
            'UF_CRM_1702273072' => 'UF_CRM_1702275014', //owner 4 => proportion 4
        ];
        foreach($mapUFOwners as $ownerCompany => $ownerProportion) {
            $ownerID = $companyItem->get($ownerCompany);
            if($ownerID) {
                if (str_contains($ownerID, 'CO_')) {
                    $ownerID = str_replace("CO_", '', $ownerID);
                    $ownerItem = $factoryCompany->getItem($ownerID);
                    $ownerUUID = $ownerItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                    $ownerTitle = $ownerItem->get('TITLE') ?? '';
                }
                elseif(str_contains($ownerID, 'C_')) {
                    $ownerID = str_replace("C_", '', $ownerID);
                    $ownerItem = $factoryContact->getItem($ownerID);
                    $ownerUUID = $ownerItem->get('UF_CRM_CONTACT_SS_FL_AM_ID') ?? '';
                    $ownerTitle = $ownerItem->get('TITLE') ?? '';
                }
                else {
                    $ownerItem = $factoryCompany->getItem($ownerID);
                    $ownerUUID = $ownerItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                    $ownerTitle = $ownerItem->get('TITLE') ?? '';
                }

                if($ownerUUID) {
                    $beneficialOwnersDetails[] = [
                        'id' => $ownerUUID,
                        'proportion' => $companyItem->get($ownerProportion) ?? '',
                    ];
                } else {
                    $message = "[b]Ошибка при отправке данных[/b]\nБенефициар {$ownerTitle} #{$ownerID} не существует в 1С\nСначала засинхронизируйте,\nпрежде чем будет доступен для отправки в 1С!";
                    \CRest::call('crm.timeline.comment.add', [
                        'fields' => [
                            "ENTITY_ID" => $companyId,
                            "ENTITY_TYPE" => "COMPANY",
                            "COMMENT" => "{$message}"
                        ]
                    ]);
                }
            }
        }
        return $beneficialOwnersDetails;
    }
    private function saveOnecUUID(int $companyId, array $response): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $item = $factoryCompany->getItem($companyId);
        $uuid = $item->get("UF_CRM_COMPANY_SS_AM_ID");
        if (is_null($uuid)) {
            $body = json_decode($response['body'], true);
            $syncResultCode = $body["syncResultCode"];
            if ($syncResultCode == 200) {
                $syncObjectResult = $body["syncObjectResult"];
                if ($syncObjectResult) {
                    foreach ($syncObjectResult as $key => $syncObject) {
                        $item->set("UF_CRM_COMPANY_SS_AM_ID", $syncObject['id']);
                        $operation = $factoryCompany->getUpdateOperation($item);
                        $operation->disableAllChecks();
                        $operation->launch();
                    }
                }
            }
        }
    }

    /**
     * @param $BankDetailResult
     * @param array $bankDetails
     * @return array
     */
    private function extractedBankDetails($BankDetailResult, array $bankDetails): array
    {
        $bankDetailsCount = $BankDetailResult->getSelectedRowsCount();
        $bankDetailsRows = $BankDetailResult->fetchAll();

        ///if ($bankDetailsCount > 0) {
            foreach ($bankDetailsRows as $i => $bankDetailRow) {
                $bankDetails[$i]['bankId'] = $bankDetailRow['RQ_BIK'];
                $bankDetails[$i]['bankAccountId'] = $bankDetailRow['RQ_ACC_NUM'];
                $bankDetails[$i]['bankAccountName'] = $bankDetailRow['NAME'];
                $bankDetails[$i]['bankAccountPrimaryMark'] = 1;
            }
        //}

        return $bankDetails;
    }

}
