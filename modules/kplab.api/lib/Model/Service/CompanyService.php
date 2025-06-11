<?php
namespace KPLab\API\V2\Model\Service;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\HttpClientFactory;
use KPLab\API\V2\Infrastructure\Http\Auth\CustomAuthHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Interfaces\Http\Auth\AuthScheme;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\Logs;
use Throwable;

define("LOG_COMPANY_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_company.log");
class CompanyService
{
    public const TYPE_FL = 'FL';
    public const TYPE_IP = 'IP';
    public const TYPE_ORG = 'ORG';
    private LegalService   $legalService;
    protected const DA_DATA_TOKEN = '7b8345cb77b326d4c5ab5eaee24bc9574adf5924';
    private HttpClientInterface $http;
    private string $method;
    private string $endpoint;
    private DadataService $dadata;
    private BeneficialOwnerService $beneficialOwnerService;
    private RequisiteService $requisiteService;
    private AddressService $addressService;
    private BankDetailService $bankDetailService;
    private ContactPersonService $contactPersonService;
    private ContactDetailsService $contactDetailsService;

    public function __construct(
        HttpClientInterface $httpClient,
        string $method,
        string $endpoint = 'https://ak.seller-capital.ru/api/hs/api/update'
    ) {
        $this->http     = $httpClient;
        $this->method = $method;
        $this->endpoint = $endpoint;

        $base = new BitrixHttpClientAdapter();
        $auth = new CustomAuthHttpClientDecorator(
            $base,
            new AuthScheme(self::DA_DATA_TOKEN, 'Token'),
            'Authorization'
        );
        $httpClientDadata = HttpClientFactory::build(baseClient: $auth, logging: false);
        $this->dadata = new DadataService($httpClientDadata);

        $this->beneficialOwnerService = new BeneficialOwnerService();
        $this->requisiteService = new RequisiteService;
        $this->addressService = new AddressService;
        $this->bankDetailService = new BankDetailService;
        $this->contactPersonService = new ContactPersonService;
        $this->contactDetailsService = new ContactDetailsService;
    }

    /**
     * Собирает payload и отправляет его на внешний сервис.
     *
     * @param array $companyData — локальные данные компании (id, externalUuid и т.п.)
     * @param string $externalSystem — внешняя система
     * @return array|null — распарсенный ответ от внешнего API
     */
    public function sync(array $companyData, string $externalSystem): ?array
    {
        try {
            $companyId = $companyData['ID'];
            $payload = $this->buildPayload($companyData, $externalSystem);
        } catch (Throwable $e) {
            $this->sendToTimeline($companyId, $e->getMessage(), 'Ошибка сборки');
            Logs\File::AddMessage([$companyId, $e->getMessage()], 'Ошибка сборки', LOG_COMPANY_SERVICE);
            return [
                'status' => 500,
                'body' => json_encode(['error' => 'Sync failed', 'message' => $e->getMessage()])
            ];
        }

        try {
            $meta = [
                'objectData' => [
                    'INIT_OBJECT_URL' => "https://testcrm.seller-capital.ru/crm/type/4/details/{$companyId}/",
                ],
                'methodName' => __FUNCTION__,
                'controllerName' => get_class($this)
            ];

            if ($externalSystem == 'onec') {
                $meta['objectData']['ITEM_TITLE'] = "Отправка карточки компании #{$companyId} в 1С";
                $meta['partnerName'] = "1С:АК-Кредит";
            }

            Logs\File::AddMessage($meta, "meta", LOG_COMPANY_SERVICE);


            $response = $this->http->request(
                $this->method,
                $this->endpoint,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Accept-Charset' => 'UTF-8',
                ],
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $meta
            );

            $body = json_decode($response['body'], true);
            $syncResultCode = $body["syncResultCode"] ?? null;

            if ($syncResultCode && $syncResultCode !== 200) {
                $response['status'] = $syncResultCode;
            }

            if ($externalSystem == 'onec') {
                $this->saveOnecUUID($companyId, $response);
            }

            return $response;
        } catch (Throwable $e) {
            $this->sendToTimeline($companyId, $e->getMessage(), 'Ошибка синхрона');
            return [
                'status' => 500,
                'body' => json_encode(['error' => 'Sync failed', 'message' => $e->getMessage()])
            ];
        }
    }

    /**
     * Строит структуру полезной нагрузки по данным компании.
     */
    /**
     * @throws \DateMalformedStringException
     * @throws Throwable
     */
    public function buildSyncObjectsFor1C(array $entityData, string $orgType): array
    {
        $entityId = (int)$entityData['ID'];

        // 1) загрузили реквизиты
        $rq = $this->requisiteService->load($entityId);
        $firstRequisite = $rq[0] ?? [];
        //Logs\File::AddMessage($firstRequisite, "firstRequisite", LOG_COMPANY_SERVICE);

        // 2) адреса
        $addr = $this->addressService->load($entityId);
        //Logs\File::AddMessage($addr, "addr", LOG_COMPANY_SERVICE);

        // 3) банковские
        $banks = $this->bankDetailService->load($entityId);
        //Logs\File::AddMessage($banks, "banks", LOG_COMPANY_SERVICE);

        // 4) Контактные лица
        $contactPersons = $this->contactPersonService->get($entityId);
        //Logs\File::AddMessage($contactPersons, "contactPersons", LOG_COMPANY_SERVICE);

        // 5) Контактные данные (телефоны/Email)
        $contactDetails = $this->contactDetailsService->load($entityId);
        //Logs\File::AddMessage($contactDetails, "contactDetails", LOG_COMPANY_SERVICE);

        // 6) Директор (guid 1С)
        $representativeGuid = $this->getRepresentativeGuid($entityData);
        //Logs\File::AddMessage($representativeGuid, "representativeGuid", LOG_COMPANY_SERVICE);

        // 7) Список бенифициаров
        $owners = $this->beneficialOwnerService->loadOwnersDetails($entityId);
        //Logs\File::AddMessage($owners, "owners", LOG_COMPANY_SERVICE);

        $builder = (new SyncObjectBuilder())
            ->withCompanyData($entityData)
            ->withRequisite($firstRequisite)
            ->withContactDetails($contactDetails)
            ->withAddresses($addr)
            ->withBankDetails($banks)
            ->withContactPersonDetails($contactPersons)
            ->withOrgType($orgType);


        if ($orgType == self::TYPE_ORG) {
            // 4) собрали SyncObject
            $builder->withRepresentative($representativeGuid)
                ->withBeneficialOwners($owners);

        }

        try {
            $syncObject = $builder->validate()->build();

            return [
                'syncFormat'  => 1,
                'syncObjects' => [$syncObject],
            ];
        } catch (\Throwable $e) {
            Logs\File::AddMessage([$entityId, $e->getMessage()], "Валидация данных", LOG_COMPANY_SERVICE);
            throw $e;
        }
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function buildPayload(array $data, string $externalSystem): array
    {
        $payload = [];
        $orgStructure = $this->getOrgStructure($data);

        if ($externalSystem == 'onec') {
            $payload = $this->buildSyncObjectsFor1C($data, $orgStructure);
        }
        return $payload;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function normalizeDate(?string $value): ?string
    {
        return $value ? (new \DateTime($value))->format('d.m.Y') : null;
    }
    private function mapDocType(int $identDoc): int
    {
        return match ($identDoc) {
            21907 => 21,
            21908 => 27,
            21944 => 12,
            21909 => 31,
        };
    }

    /**
     * @throws Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getOrgStructure(array $data)
    {
        try {
            $idClientType = $data['UF_CRM_1684145100226'] ?? null;
            if (!$idClientType) {
                Logs\File::AddMessage($data, "UF_CRM_1684145100226 отсутствует", LOG_COMPANY_SERVICE);
                return self::TYPE_FL;
            }

            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID','USER_TYPE_ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);

            while ($arUserField = $userFields->fetch()){
                if($arUserField['ID'] && $arUserField['USER_TYPE_ID'] == 'enumeration')
                {
                    $arUserFieldList = \CUserFieldEnum::GetList([], [
                        'ID' => $idClientType,
                        'USER_FIELD_ID' => $arUserField['ID']
                    ]);
                    if($arItem = $arUserFieldList->Fetch()) {
                        return $arItem['XML_ID'];
                    }
                }
            }

            Logs\File::AddMessage($idClientType, "Оргструктура не найдена, возвращаю FL", LOG_COMPANY_SERVICE);
            return self::TYPE_FL;
        }
        catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в getOrgStructure()", LOG_COMPANY_SERVICE);
            throw $e;
        }
    }
    private function getRepresentativeGuid($data)
    {
        $representativeCrmId = $data['UF_CRM_1615200179'] ?? null;
        $representativeGuid = '';

        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $factoryContact = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Contact);

        if(!is_null($representativeCrmId)) {
            if (str_contains($representativeCrmId, 'CO_')) {
                $representativeCrmId = str_replace("CO_", '', $representativeCrmId);
                $representativeItem = $factoryCompany->getItem($representativeCrmId);
                $representativeGuid = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                $representativeTitle = $representativeItem->get('TITLE') ?? '';
            }
            elseif(str_contains($representativeCrmId, 'C_')) {
                $representativeCrmId = str_replace("C_", '', $representativeCrmId);
                $representativeItem = $factoryContact->getItem($representativeCrmId);
                $representativeGuid = $representativeItem->get('UF_CRM_CONTACT_SS_FL_AM_ID') ?? '';
                $LAST_NAME = $representativeItem->get('LAST_NAME') ?? '';
                $NAME = $representativeItem->get('NAME') ?? '';
                $SECOND_NAME = $representativeItem->get('SECOND_NAME') ?? '';
                $representativeTitle = $LAST_NAME." ".$NAME." ".$SECOND_NAME;
            }
            else {
                $representativeItem = $factoryCompany->getItem($representativeCrmId);
                $representativeGuid = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                $representativeTitle = $representativeItem->get('TITLE') ?? '';
            }
            if ($representativeGuid == "") {
                $message = "[b]Ошибка при отправке данных[/b]\nРуководитель {$representativeTitle} #{$representativeCrmId} не существует в 1С\nСначала засинхронизируйте,\nпрежде чем будет доступен для отправки в 1С!";
                \CRest::call('crm.timeline.comment.add', [
                    'fields' => [
                        "ENTITY_ID" => $data['ID'],
                        "ENTITY_TYPE" => "COMPANY",
                        "COMMENT" => "{$message}"
                    ]
                ]);
            }
        }
        return $representativeGuid;
    }

    /**
     * @throws Throwable
     * @throws ArgumentException
     */
    private function saveOnecUUID(int $companyId, array $response): void
    {
        try {
            $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
            $item = $factoryCompany->getItem($companyId);

            $uuid = $item->get("UF_CRM_COMPANY_SS_AM_ID");
            if (is_null($uuid)) {
                $body = json_decode($response['body'], true);
                $syncResultCode = $body["syncResultCode"] ?? null;
                Logs\File::AddMessage($syncResultCode, "syncResultCode", LOG_COMPANY_SERVICE);

                if ($syncResultCode === 200) {
                    $syncObjectResult = $body["syncObjectResult"] ?? [];

                    foreach ($syncObjectResult as $syncObject) {
                        if (!empty($syncObject['id'])) {
                            $item->set("UF_CRM_COMPANY_SS_AM_ID", $syncObject['id']);
                            $context = new \Bitrix\Crm\Service\Context();
                            $context->setUserId(33381);
                            $operation = $factoryCompany->getUpdateOperation($item, $context);
                            $operation->disableAllChecks();
                            $operation->launch();
                            $this->sendToTimeline($companyId, "Изменения приняты ({$syncObject['id']})");
                        }
                    }
                }
                else {
                    Logs\File::AddMessage($body, "Ошибка UUID: syncResultCode != 200", LOG_COMPANY_SERVICE);
                }
            }
        }
        catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в saveOnecUUID() для компании #{$companyId}", LOG_COMPANY_SERVICE);
            throw $e;
        }
    }
    private function sendErrorToTimeline(int $entityId, string $errorMessage, string $section = 'Общие данные'):void
    {
        $comment = sprintf(
            "[b]Результат при отправке данных в 1С[/b]\nРаздел: [i]%s[/i]\n%s",
            htmlspecialcharsbx($section),
            htmlspecialcharsbx($errorMessage)
        );
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_TYPE' => 'COMPANY',
                'ENTITY_ID'   => $entityId,
                'COMMENT'     => $comment,
            ],
        ]);
    }
    private function sendToTimeline(int $entityId, string $message, string $section = 'Общие данные'):void
    {
        $comment = sprintf(
            "[b]Результат при отправке данных в 1С[/b]\nРаздел: [i]%s[/i]\n%s",
            htmlspecialcharsbx($section),
            htmlspecialcharsbx($message)
        );
        \CRest::call('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_TYPE' => 'COMPANY',
                'ENTITY_ID'   => $entityId,
                'COMMENT'     => $comment,
            ],
        ]);
    }

    /*
    private function loadRequisite(int $companyId): array
    {
        $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
            'filter'=> ["ENTITY_ID" => $companyId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
            'select' => ['*','UF_*']
        ]);

        return $requisiteResult->fetchAll();
    }
    private function mapAddressData(array $data): array
    {
        return [
            'postCode'      => $data['postal_code'] ?? '',
            'countryCode'   => 643,
            'regStateNum'   => $data['kladr_id'] ?? '',
            'locationCode'  => $data['okato'] ?? '',
            'province'      => $data['region'] ?? '',
            'location'      => $data['city'] ?? ($data['settlement'] ?? ''),
            'street'        => $data['street'] ?? '',
            'house'         => $data['house'] ?? '',
            'block'         => ($data['block_type_full'] ?? '') === 'корпус' ? ($data['block'] ?? '') : '',
            'build'         => ($data['block_type_full'] ?? '') === 'строение' ? ($data['block'] ?? '') : '',
            'apart'         => $data['flat'] ?? '',
        ];
    }
    private function loadAddressDetails(int $companyId): array
    {
        $addressDetails = [];
        try {
            $resAddrList = \CRest::call('crm.address.list', [
                'filter' => ['ANCHOR_ID' => $companyId, 'ANCHOR_TYPE_ID' => \CCrmOwnerType::Company],
                'select' => ['TYPE_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID', 'ANCHOR_ID', 'ANCHOR_TYPE_ID', 'LOC_ADDR_ID']
            ])['result'] ?? [];

            $Address = new \Bitrix\Location\Controller\Address;
            foreach($resAddrList as $i => $addrItem){
                try {
                    $addrItemTypeId = (int) $addrItem['TYPE_ID'];
                    switch ($addrItemTypeId) {
                        case 1:
                            $addressDetails[$i]['addressType'] = 2;
                            break;
                        case 4:
                        case 6:
                            $addressDetails[$i]['addressType'] = 1;
                            break;
                        default:
                            Logs\File::AddMessage([
                                'typeId' => $addrItemTypeId,
                                'item' => $addrItem
                            ], "Необработанный TYPE_ID в loadAddressDetails()", LOG_COMPANY_SERVICE);
                            continue 2;
                    }

                    $LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];
                    $addrId = $Address->findById($LOC_ADDR_ID);
                    if (!$addrId || !isset($addrId['fieldCollection'])) {
                        Logs\File::AddMessage($LOC_ADDR_ID, "Не найден location address по ID", LOG_COMPANY_SERVICE);
                        continue;
                    }
                    $resAddress = $addrId['fieldCollection'];
                    $addressFiasId = $resAddress[900] ?? null;
                    $addressDetails[$i]['fiasId'] = $addressFiasId;

                    if (!$addressFiasId) {
                        Logs\File::AddMessage($addrItem, "LOC_ADDR_ID без FIAS", LOG_COMPANY_SERVICE);
                        continue;
                    }

                    $addressData = $this->dadata->findByFiasId($addressFiasId);

                    if (!$addressData) {
                        Logs\File::AddMessage($addressFiasId, "Dadata не вернула данные", LOG_COMPANY_SERVICE);
                        continue;
                    }

                    $addressDetails[$i] = array_merge(
                        $addressDetails[$i],
                        $this->mapAddressData($addressData)
                    );

                }
                catch (\Throwable $inner) {
                    Logs\File::AddMessage($inner->getMessage(), "Ошибка в обработке адреса компании {$companyId}", LOG_COMPANY_SERVICE);
                    continue;
                }
            }

        }
        catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в loadAddressDetails() для компании {$companyId}", LOG_COMPANY_SERVICE);
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
        try {

            $requisites = $this->loadRequisite($companyId);
            $rqId = $requisites[0]['ID'] ?? null;

            if (!$rqId) {
                Logs\File::AddMessage($companyId, "Нет реквизита для расчетного счёта", LOG_COMPANY_SERVICE);
                return [];
            }

            $BankDetailResult = \Bitrix\Crm\BankDetailTable::getList([
                'filter'=> [
                    "ENTITY_ID" => $rqId,
                    "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                    'UF_CRM_BD_ACC_TYPE' => 'Расчетный'
                ],
                'select' => ['*','UF_*'],
                'order' => ['ID' => 'DESC']
            ]);

            $bankDetails = $this->extractedBankDetails($BankDetailResult, $bankDetails);

        }
        catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в loadBankDetails() для компании {$companyId}", LOG_COMPANY_SERVICE);
        }
        return $bankDetails;
    }
    private function loadBankNominalDetails(int $companyId): array
    {
        $bankNominalDetails = [];
        try {
            $requisites = $this->loadRequisite($companyId);
            $rqId = $requisites[0]['ID'] ?? null;

            if (!$rqId) {
                Logs\File::AddMessage($companyId, "Нет реквизита для номинального счёта", LOG_COMPANY_SERVICE);
                return [];
            }

            $BankDetailResult = \Bitrix\Crm\BankDetailTable::getList([
                'filter'=> [
                    "ENTITY_ID" => $rqId,
                    "ENTITY_TYPE_ID" => \CCrmOwnerType::Requisite,
                    "UF_CRM_BD_ACC_TYPE" => "Номинальный"
                ],
                'select' => ['*','UF_*']
            ]);

            $bankNominalDetails = $this->extractedBankDetails($BankDetailResult, $bankNominalDetails);
        }
        catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в loadBankNominalDetails() для компании {$companyId}", LOG_COMPANY_SERVICE);
        }
        return $bankNominalDetails;
    }

    private function loadContactPersonDetails(int $companyId): array
    {
        $contactPersonDetails = [];

        try {
            \Bitrix\Main\Loader::includeModule('iblock');
            $IBLOCK_ID = 179;
            $prefix = "CO_";
            $arFilter = [
                "IBLOCK_ID" => $IBLOCK_ID,
                "=PROPERTY_1024_VALUE" => $prefix.$companyId,
                "ACTIVE_DATE" => "Y",
                "ACTIVE"=>"Y"
            ];
            $arSelect = ["*", "PROPERTY_*"];
            $res = \CIBlockElement::GetList(['ID' => 'ASC'], $arFilter, false, [], $arSelect);

            if (!$res) {
                Logs\File::AddMessage($companyId, "Не удалось получить контактных лиц", LOG_COMPANY_SERVICE);
                return [];
            }

            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $arProps = $ob->GetProperties();

                $contactPersonDetails[] = [
                    'nameText' => $arFields['NAME'],
                    'birthDate' => $this->normalizeDate($arProps['DATA_ROZHDENIYA']['VALUE'] ?? ''),
                    'phone' => $arProps['TELEFON']['VALUE'],
                    'stateId' => $arProps['STATUS']['VALUE_XML_ID'] ?? '',
                    'commentText' => $arProps['KOMMENTARIY']['VALUE'] ?? '',
                ];
            }

        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в loadContactPersonDetails() для компании {$companyId}", LOG_COMPANY_SERVICE);
        }

        return $contactPersonDetails;
    }
    private function loadBeneficialOwnersDetails(int $companyId): array
    {
        return $this->beneficialOwnerService->loadOwnersDetails($companyId);
    }


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
        try {
            $syncObject = [
                'id'     => $company['UF_CRM_COMPANY_SS_AM_ID'] ?? '',
                'limitSum'     => floatval(str_replace("|RUB", '', $company['UF_CRM_1697107946'] ?? '0')),
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
                        $representativeGuid = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                        $representativeTitle = $representativeItem->get('TITLE') ?? '';
                    }
                    elseif(str_contains($representativeCrmId, 'C_')) {
                        $representativeCrmId = str_replace("C_", '', $representativeCrmId);
                        $representativeItem = $factoryContact->getItem($representativeCrmId);
                        $representativeGuid = $representativeItem->get('UF_CRM_CONTACT_SS_FL_AM_ID') ?? '';
                        $LAST_NAME = $representativeItem->get('LAST_NAME') ?? '';
                        $NAME = $representativeItem->get('NAME') ?? '';
                        $SECOND_NAME = $representativeItem->get('SECOND_NAME') ?? '';
                        $representativeTitle = $LAST_NAME." ".$NAME." ".$SECOND_NAME;
                    }
                    else {
                        $representativeItem = $factoryCompany->getItem($representativeCrmId);
                        $representativeGuid = $representativeItem->get('UF_CRM_COMPANY_SS_AM_ID') ?? '';
                        $representativeTitle = $representativeItem->get('TITLE') ?? '';
                    }
                    if ($representativeGuid == "") {
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
                if(!empty($representativeGuid)) $syncObject += ['representativeGuid' => $representativeGuid];
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
                    'docType'    => $this->mapDocType($rq['UF_CRM_RQ_TYPE_OF_DOCUMENT'] ?? ''),
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
                    'docType'    => $this->mapDocType($rq['UF_CRM_RQ_TYPE_OF_DOCUMENT'] ?? ''),
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

            // универсальные блоки
            if (!empty($addrDetails)) $syncObject['addressDetails'] = $addrDetails;
            if (!empty($contactDetails)) $syncObject['contactDetails'] = $contactDetails;
            if (!empty($contactPersonDetails)) $syncObject['contactPersonDetails'] = $contactPersonDetails;
            if (!empty($bankDetails)) $syncObject['bankDetails'] = $bankDetails;
            if (!empty($bankNominalDetails)) $syncObject['bankNominalDetails'] = $bankNominalDetails;

            return [
                'syncFormat'  => 1,
                'syncObjects' => [$syncObject],
            ];
        } catch (\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), "Ошибка в buildOnecPayload()", LOG_COMPANY_SERVICE);
            return [
                'syncFormat' => 1,
                'syncObjects' => [],
            ];
        }
    }
    */

}
