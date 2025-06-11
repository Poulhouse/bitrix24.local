<?php namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\BankDetailTable;
use Bitrix\Location\Entity\Address;
use Bitrix\Location\Entity\Address\FieldType;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\HttpClient;
use KPLab\API\V2\HttpClientFactory;
use KPLab\API\V2\Infrastructure\Http\Auth\CustomAuthHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Model\DTO\LegalDTO;
use KPLab\API\V2\Model\DTO\Requisite\LegalRequisiteData;
use KPLab\CRM\AddressTable;
use KPLab\Logs;
use KPLab\API\V2\Interfaces\Http\Auth\AuthScheme;
use Throwable;

define("LOG_LEGAL_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_legal.log");

class LegalService
{
    private string $legalInn;
    public int $legalId;
    public string $partnerName;
    public array $beneficialOwners;
    public ?string $rqId;
    public string $representative;
    private BeneficialOwnerService $beneficialOwnerService;
    private RequisiteService $requisiteService;
    public function __construct(string $legalInn) {
        $this->legalInn = $legalInn;
        $this->beneficialOwnerService = new BeneficialOwnerService();
        $this->requisiteService = new RequisiteService;

    }

    /**
     * @throws Throwable
     */
    public function add(LegalDTO $legalDTO): static
    {
        try {
            ChangeContext::setSource($this->partnerName);
            $this->createCompany($legalDTO);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Logs\File::AddMessage($e->getMessage(), 'validation_error в LegalService::add()', LOG_LEGAL_SERVICE);
            throw $e;
        } catch (\Exception|\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'server_error в LegalService::add()', LOG_LEGAL_SERVICE);
            throw $e;
        } finally {
            ChangeContext::clear();
            return $this;
        }
    }

    /**
     * @throws Throwable
     */
    public function update($legalId, LegalDTO $legalDTO): static
    {
        try {
            ChangeContext::setSource($this->partnerName);
            $this->updateCompany($legalId, $legalDTO);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Logs\File::AddMessage($e->getMessage(), 'validation_error в LegalService::update()', LOG_LEGAL_SERVICE);
            throw $e;
        } catch (\Exception|\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'server_error в LegalService::update()', LOG_LEGAL_SERVICE);
            throw $e;
        } finally {
            ChangeContext::clear();
        }
        return $this;
    }

    /**
     * @throws Throwable
     */
    private function createCompany(LegalDTO $legalDTO): void
    {
        try {
            $factoryCompany = $this->getCompanyFactory();
            $prefix = 'CO_';
            $legalDTO->toCompanyFields($prefix);

            $company = $factoryCompany->createItem($legalDTO->toCompanyFields);

            if($legalDTO->contactDetails) {
                // Получаем текущие контакты компании
                $fm = $company->getFm();
                // Обрабатываем новые контакты
                $fm = (new Tool)->processFM($legalDTO, $fm);
                // Сохраняем изменения
                $company->setFm($fm);
            }
            $company->setTitle($legalDTO->shortName);
            $company->save();

            // Сохранение новой карточки
            $operation = $factoryCompany->getAddOperation($company);
            $operation->disableAllChecks();

            $result = $operation->launch();
            if(!$result->isSuccess()) {
                Logs\File::AddMessage($result->getErrors(),"Ошибки создания компании (getErrors)", LOG_LEGAL_SERVICE);
            }

            $this->legalId = $company->getId();

            $this->processRequisites($legalDTO);
            $this->processContactPersons($legalDTO);
            $this->processBeneficialOwners($legalDTO);

            // Обновление карточки
            $operation = $factoryCompany->getUpdateOperation($company);
            $operation->disableAllChecks();
            $operation->launch();
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'Ошибка в createCompany()', LOG_LEGAL_SERVICE);
            throw $e;
        }
    }

    /**
     * @throws Throwable
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function updateCompany($legalId, LegalDTO $legalDTO): void
    {
        try {
            $factoryCompany = $this->getCompanyFactory();
            $this->processRequisites($legalDTO);
            $this->processContactPersons($legalDTO);
            $this->processBeneficialOwners($legalDTO);

            $prefix = 'CO_';
            $representativeId = $legalDTO->getRepresentative($legalDTO->representativeGuid);

            $company = $factoryCompany->getItem($legalId);
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()) {
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => 'ORG']);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }

            $company->set("UF_CRM_1684145100226", $TypeId);
            $company->set("UF_CRM_COMPANY_SS_ORG", [5]);
            $company->set("UF_CRM_6433DBB98DD53", 17611);
            $company->set("UF_CRM_6433D7C925893", $legalDTO->inn);
            $company->set("UF_CRM_COMPANY_SS_AM_ID", $legalDTO->guid);
            $company->set("UF_CRM_1615200179", $prefix.$representativeId);
            $company->set("UF_CRM_1697107946", $legalDTO->limitSum."|RUB");
            $company->set("UF_CRM_1595595411835", $legalDTO->fullName);

            if($legalDTO->contactDetails) {
                // Получаем текущие контакты компании
                $fm = $company->getFm();
                // Обрабатываем новые контакты
                $fm = (new Tool)->processFM($legalDTO, $fm);
                // Сохраняем изменения
                $company->setFm($fm);
            }

            $company->setTitle($legalDTO->shortName);
            Logs\File::AddMessage($legalDTO->shortName, 'shortName в updateCompany()', LOG_LEGAL_SERVICE);

            // Обновление карточки
            $operation = $factoryCompany->getUpdateOperation($company);
            $operation->disableAllChecks();
            $operation->launch();
        }
        catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'Ошибка в updateCompany()', LOG_LEGAL_SERVICE);
            throw $e;
        }
    }

    //region Вспомогательные методы
    public function getId(): int
    {
        return $this->legalId;
    }
    public function setPartnerName($value): void
    {
        $this->partnerName = $value;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    public function find(): void
    {
        $rq = $this->requisiteService->loadByKey('RQ_INN', $this->legalInn) ?? null;
        if(!empty($rq) && $rq['ENTITY_ID'] !== '') {
            $this->legalId = $rq['ENTITY_ID'];
        } else {
            $this->legalId = 0;
        }

    }
    //endregion

    //region Реквизиты компании
    private function findCompanyRQ($legalId, $legalInn): void
    {
        if(!is_null($legalInn)) {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $legalId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company, "RQ_INN" => $legalInn],
                'select' => ['*','UF_*']
            ]);
        }
        else {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $legalId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
                'select' => ['*','UF_*']
            ]);
        }
        $requisite = $requisiteResult->fetchAll();
        if(isset($requisite[0])) {
            $this->rqId = $requisite[0]['ID'];
        } else {
            $this->rqId = 0;
        }
    }
    private function createRequisite($legalId, LegalDTO $legalDTO) : void
    {
        $PRESET_ID = 1;
        $fields = [
            "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
            "ENTITY_ID" => $legalId,
            "PRESET_ID" => $PRESET_ID,
            'TITLE' => $legalDTO->shortName,
            'NAME' => $legalDTO->shortName,
            'RQ_INN' => $legalDTO->inn,
            'RQ_KPP' => $legalDTO->kpp,
            'RQ_OGRN' => $legalDTO->regNum,
            'RQ_OKPO' => $legalDTO->okpo,
            'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($legalDTO->regDate)),
            'UF_CRM_1688964741' => $legalDTO->regNumOrg,
            'RQ_COMPANY_NAME' => $legalDTO->shortName,
            'RQ_COMPANY_FULL_NAME' => $legalDTO->fullName
        ];

        // Создаём реквизит через REST API и получаем его идентификатор
        $result = \Bitrix\Crm\RequisiteTable::add($fields);
        $this->rqId = $result['data'];

        //region Send event
        if ($result->isSuccess())
        {
            $event = new \Bitrix\Main\Event('crm', 'OnAfterRequisiteAdd', array('id' => $this->rqId, 'fields' => $fields));
            $event->send();
        }
        //endregion
    }
    private function updateRequisite($rqId, LegalDTO $legalDTO): void
    {
        $fields = [
            'TITLE' => $legalDTO->shortName,
            'NAME' => $legalDTO->shortName,
            'RQ_INN' => $legalDTO->inn,
            'RQ_KPP' => $legalDTO->kpp,
            'RQ_OGRN' => $legalDTO->regNum,
            'RQ_OKPO' => $legalDTO->okpo,
            'RQ_COMPANY_REG_DATE' => date('d.m.Y', strtotime($legalDTO->regDate)),
            'UF_CRM_1688964741' => $legalDTO->regNumOrg,
            'RQ_COMPANY_NAME' => $legalDTO->shortName,
            'RQ_COMPANY_FULL_NAME' => $legalDTO->fullName
        ];

        $result = \Bitrix\Crm\RequisiteTable::update($rqId, $fields);

        //region Send event
        if ($result->isSuccess())
        {
            $event = new \Bitrix\Main\Event('crm', 'OnAfterRequisiteUpdate', array('id' => $rqId, 'fields' => $fields));
            $event->send();
        }
        //endregion
    }
    private function processBankDetails(mixed $rqId, LegalDTO $legalDTO): void
    {


        if (!isset($rqId)) {
            return;
        }
        try {
            $fieldsForBank = [];
            // Удаляем все старые банковские реквизиты
            $existingBankDetails = BankDetailTable::getList([
                'filter' => [
                    'ENTITY_ID' => $rqId,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite
                ],
                'select' => ['ID']
            ])->fetchAll();

            foreach ($existingBankDetails as $bankDetail) {
                BankDetailTable::delete($bankDetail['ID']);
            }


            foreach ($legalDTO->bankDetails as $bank) {
                $bankAccountPrimaryMark = ($bank['bankAccountPrimaryMark'] == 1) ? "Да" : "Нет";
                $fieldsForBank[] = [
                    'ENTITY_ID' => $rqId,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                    'NAME' => $bank['bankAccountName'] ?? '',
                    'RQ_ACC_NUM' => $bank['bankAccountId'] ?? '',
                    'RQ_BIK' => $bank['bankId'] ?? '',
                    'UF_CRM_PRIMARY_TXT' => $bankAccountPrimaryMark,
                    'UF_CRM_BD_ACC_TYPE' => 'Расчетный'
                ];
            }

            foreach ($legalDTO->bankNominalDetails as $bank) {
                $bankAccountPrimaryMark = ($bank['bankAccountPrimaryMark'] == 1) ? "Да" : "Нет";
                $fieldsForBank[] = [
                    'ENTITY_ID' => $rqId,
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
                    'NAME' => $bank['bankAccountName'] ?? '',
                    'RQ_ACC_NUM' => $bank['bankAccountId'] ?? '',
                    'RQ_BIK' => $bank['bankId'] ?? '',
                    'UF_CRM_PRIMARY_TXT' => $bankAccountPrimaryMark,
                    'UF_CRM_BD_ACC_TYPE' => 'Номинальный'
                ];
            }


            foreach ($fieldsForBank as $fields) {
                $result = BankDetailTable::add($fields);

                // Отправка события после добавления
                if ($result->isSuccess()) {
                    $event = new \Bitrix\Main\Event('crm', 'OnAfterBankDetailAdd', [
                        'id' => $rqId,
                        'fields' => $fields
                    ]);
                    $event->send();
                }
            }
        } catch (\Exception $e) {
            Logs\File::AddMessage($e->getMessage(),"Ошибки добавления банковских реквизитов", LOG_LEGAL_SERVICE);
        }

    }
    private function processAddressRequisites(mixed $rqId, int $cardId, LegalDTO $legalDTO): void
    {
        if (!isset($rqId) || !isset($legalDTO->addressDetails) || !is_array($legalDTO->addressDetails)) {
            return;
        }

        $AddressService = new AddressService($legalDTO->addressDetails, $rqId, $cardId, 'legal');
        $AddressService->init();
    }
    private function processRequisites(LegalDTO $legalDTO): void
    {

        try {
            $requisiteData = new LegalRequisiteData($this->legalId, $legalDTO);
            $this->rqId = $this->requisiteService->save($requisiteData);

            /*$this->findCompanyRQ($this->legalId, $legalDTO->inn);

            if ($this->rqId == 0) {
                $this->createRequisite($this->legalId, $legalDTO);
            } else {
                $this->updateRequisite($this->rqId, $legalDTO);
            }

            $this->processAddressRequisites($this->rqId, $this->legalId, $legalDTO);
            $this->processBankDetails($this->rqId, $legalDTO);*/
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'Ошибка в processRequisites()', LOG_LEGAL_SERVICE);
        }
    }
    //endregion

    private function processContactPersons(LegalDTO $legalDTO): void
    {
        try {
            \KPLab\OneC\ContactPersons::getDetails(
                $this->legalId,
                $legalDTO->contactPersonDetails,
                "CO_"
            );
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'Ошибка в processContactPersons()', LOG_LEGAL_SERVICE);
        }
    }
    private function processBeneficialOwners(LegalDTO $legalDTO): void
    {
        $this->beneficialOwnerService->syncOwners($this->legalId, $legalDTO->beneficialOwnersDetails);
    }

    private function getCompanyFactory(): ?\Bitrix\Crm\Service\Factory
    {
        return \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
    }

}