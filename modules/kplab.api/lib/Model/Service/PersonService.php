<?php
namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\Multifield\Collection;
use Bitrix\Crm\Multifield\Type\Email;
use Bitrix\Crm\Multifield\Type\Phone;
use Bitrix\Crm\Multifield\Value;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use KPLab\API\V2\Model\DTO\PersonDTO;
use KPLab\API\V2\Model\DTO\Requisite\PersonRequisiteData;
use Bitrix\Crm\BankDetailTable;
use KPLab\Logs;
use Throwable;

define("LOG_PERSON_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_person.log");
class PersonService
{
    private string $personInn;
    public int $personId;
    public string $partnerName;
    public ?string $rqId;
    private RequisiteService $requisiteService;
    private AddressService $addressService;
    private BankDetailService $bankDetailService;
    private ContactPersonService $contactPersonService;
    private ContactDetailsService $contactDetailsService;

    public function __construct(string $personInn) {
        $this->personInn = $personInn;
        $this->requisiteService = new RequisiteService;
        $this->addressService = new AddressService;
        $this->bankDetailService = new BankDetailService;
        $this->contactPersonService = new ContactPersonService;
        $this->contactDetailsService = new ContactDetailsService;
    }

    /**
     * @throws Throwable
     */
    public function add(PersonDTO $personDTO): static
    {
        try {
            ChangeContext::setSource($this->partnerName);
            $this->createCompany($personDTO);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Logs\File::AddMessage($e->getMessage(), 'validation_error в PersonService::add()', LOG_PERSON_SERVICE);
            throw $e;
        } catch (\Exception|\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'server_error в PersonService::add()', LOG_PERSON_SERVICE);
            throw $e;
        } finally {
            ChangeContext::clear();
        }
        return $this;
    }

    /**
     * @throws Throwable
     */
    public function update($personId, PersonDTO $personDTO): static
    {
        try {
            ChangeContext::setSource($this->partnerName);
            $this->updateCompany($personId, $personDTO);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Logs\File::AddMessage($e->getMessage(), 'validation_error в PersonService::update()', LOG_PERSON_SERVICE);
            throw $e;
        } catch (\Exception|\Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'server_error в PersonService::update()', LOG_PERSON_SERVICE);
            throw $e;
        }
        finally {
            ChangeContext::clear();
        }
        return $this;
    }

    /**
     * @throws Throwable
     */
    private function createCompany(PersonDTO $personDTO): void
    {
        try {
            $factoryCompany = $this->getCompanyFactory();
            // 1) Создаём карточку компании
            $personDTO->toCompanyFields();
            $company = $factoryCompany->createItem($personDTO->toCompanyFields);
            $company->setTitle($personDTO->shortName);

            if ($personDTO->contactDetails) {
                $fm = $company->getFm();
                $fm = (new Tool)->processFM($personDTO, $fm);
                $company->setFm($fm);
            }

            $opAdd = $factoryCompany->getAddOperation($company);
            $opAdd->disableAllChecks();
            $opAdd->launch();

            $this->personId = $company->getId();

            $this->processRequisites($personDTO);
            $this->processContactPersons($personDTO);

            // 3) После реквизитов — обновляем карточку, если нужно
            $opUpd = $factoryCompany->getUpdateOperation($company);
            $opUpd->disableAllChecks();
            $opUpd->launch();

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
    private function updateCompany($personId, PersonDTO $personDTO): void
    {
        try {
            $factoryCompany = $this->getCompanyFactory();
            $this->processRequisites($personDTO);
            $this->processContactPersons($personDTO);

            $fio = $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName;
            $shortFio = $personDTO->lastName . " " . mb_substr($personDTO->firstName, 0, 1) . ". " . mb_substr($personDTO->middleName, 0, 1) . ".";
            $shortName = ($personDTO->regMark == "0") ? $shortFio : 'ИП ' . $shortFio;
            $fullName = ($personDTO->regMark == "0") ? $fio : 'Индивидуальный предприниматель ' . $fio;

            $company = $factoryCompany->getItem($personId);
            $TypeId = null;
            $userFields = \Bitrix\Main\UserFieldTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ENTITY_ID' => 'CRM_COMPANY',
                    'FIELD_NAME' => 'UF_CRM_1684145100226'
                ]
            ]);
            while ($arUserField = $userFields->fetch()) {
                $res = \CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $arUserField['ID'], 'XML_ID' => ($personDTO->regMark == "0") ? 'FL' : 'IP']);
                while ($arUserFieldData = $res->fetch()) {
                    $TypeId = $arUserFieldData['ID'];
                }
            }

            $company->set("UF_CRM_1684145100226", $TypeId);
            $company->set("UF_CRM_COMPANY_SS_ORG", [5]);
            $company->set("UF_CRM_6433DBB98DD53", 17611);
            $company->set("UF_CRM_6433D7C925893", $personDTO->inn);
            $company->set("UF_CRM_COMPANY_SS_AM_ID", $personDTO->guid);
            $company->set("UF_CRM_1697107946", $personDTO->limitSum . "|RUB");
            $company->set("UF_CRM_1595595411835", $fullName);

            if ($personDTO->contactDetails) {
                // Получаем текущие контакты компании
                $fm = $company->getFm();
                // Обрабатываем новые контакты
                $fm = (new Tool)->processFM($personDTO, $fm);
                // Сохраняем изменения
                $company->setFm($fm);
            }
            $company->setTitle($shortName);

            // Обновление карточки
            $operation = $factoryCompany->getUpdateOperation($company);
            $operation->disableAllChecks();
            $operation->launch();
        } catch (Throwable $e) {
            Logs\File::AddMessage($e->getMessage(), 'Ошибка в updateCompany()', LOG_PERSON_SERVICE);
            throw $e;
        }
    }

    //region Вспомогательные методы
    private function findCompanyRQ($personId, $personInn): void
    {
        if(!is_null($personInn)) {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $personId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,"RQ_INN" => $personInn],
                'select' => ['*','UF_*']
            ]);
        }
        else {
            $requisiteResult = \Bitrix\Crm\RequisiteTable::getList([
                'filter'=> ["ENTITY_ID" => $personId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company],
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
    private function mapDocType(int $identDoc): string
    {
        return match ($identDoc) {
            21 => 21907,
            27 => 21908,
            12 => 21944,
            31 => 21909,
        };
    }
    public function setPartnerName($value): void
    {
        $this->partnerName = $value;
    }
    public function find(): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $params = [
            'filter' => [
                'UF_CRM_6433D7C925893' => $this->personInn,
            ],
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
        $itemsCompany = $factoryCompany -> getItems($params);
        if($itemsCompany) {
            foreach ($itemsCompany as $itemCompany)
            {
                $this->personId = $itemCompany->getId();
            }
        } else {
            $this->personId = 0;
        }
    }
    public function getId(): string
    {
        return $this->personId;
    }

    private function getCompanyFactory(): ?\Bitrix\Crm\Service\Factory
    {
        return \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
    }
    //endregion

    //region Реквизиты компании
    private function createRequisite($personId, PersonDTO $personDTO) : void
    {
        $type = ($personDTO->regMark == "0") ? 'FL' : 'IP';
        $docType = $this->mapDocType($personDTO->docType);
        $PRESET_ID = 2;
        $fields = [
                "ENTITY_TYPE_ID" => \CCrmOwnerType::Company,
                "ENTITY_ID" => $personId,
                "PRESET_ID" => $PRESET_ID,
                'AUTOCOMPLETE' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName . ', ИНН ' . $personDTO->inn,
                'TITLE' => $type . " " . $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
                'RQ_FIRST_NAME' => $personDTO->firstName,
                'RQ_LAST_NAME' => $personDTO->lastName,
                'RQ_SECOND_NAME' => $personDTO->middleName,
                'UF_CRM_RQ_TYPE_OF_DOCUMENT' => $docType,
                'RQ_IDENT_DOC_SER' => $personDTO->docSeries,
                'RQ_IDENT_DOC_NUM' => $personDTO->docNumber,
                'RQ_IDENT_DOC_DATE' => $personDTO->docIssueDate,
                'RQ_IDENT_DOC_ISSUED_BY' => $personDTO->docIssuerText,
                'RQ_IDENT_DOC_DEP_CODE' => $personDTO->docDeptCode,
                'UF_CRM_1647929611' => $personDTO->birthPlace,
                'UF_CRM_1684493639' => $personDTO->birthDate,
                'RQ_INN' => $personDTO->inn,
                'RQ_OGRNIP' => $personDTO->regNum,
                'RQ_COMPANY_REG_DATE' => $personDTO->regDate,
                'UF_CRM_1688964741' => $personDTO->regNumOrg,
        ];

        //Logs\File::AddMessage($fields,"params RequisiteTable ADD", LOG_PERSON_SERVICE);

        // Создаём реквизит через REST API и получаем его идентификатор
        $result = \Bitrix\Crm\RequisiteTable::add($fields);

        //$rqResponse = \B24Rest::call('crm.requisite.add', $params);
        $this->rqId = $result['data'];


        //region Send event
        if ($result->isSuccess())
        {
            $event = new \Bitrix\Main\Event('crm', 'OnAfterRequisiteAdd', array('id' => $this->rqId, 'fields' => $fields));
            $event->send();
        }
        //endregion
    }
    private function updateRequisite($rqId, PersonDTO $personDTO): void
    {
        $type = ($personDTO->regMark == "0") ? '' : 'ИП ';
        $docType = $this->mapDocType($personDTO->docType);

        $fields = [
            'TITLE' => $type . $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
            'NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
            'AUTOCOMPLETE' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName . ', ИНН ' . $personDTO->inn,
            'RQ_NAME' => $personDTO->lastName . " " . $personDTO->firstName . " " . $personDTO->middleName,
            'RQ_FIRST_NAME' => $personDTO->firstName,
            'RQ_LAST_NAME' => $personDTO->lastName,
            'RQ_SECOND_NAME' => $personDTO->middleName,
            'UF_CRM_RQ_TYPE_OF_DOCUMENT' => $docType,
            'RQ_IDENT_DOC_SER' => $personDTO->docSeries,
            'RQ_IDENT_DOC_NUM' => $personDTO->docNumber,
            'RQ_IDENT_DOC_DATE' => $personDTO->docIssueDate,
            'RQ_IDENT_DOC_ISSUED_BY' => $personDTO->docIssuerText,
            'RQ_IDENT_DOC_DEP_CODE' => $personDTO->docDeptCode,
            'UF_CRM_1647929611' => $personDTO->birthPlace,
            'UF_CRM_1684493639' => $personDTO->birthDate,
            'RQ_INN' => $personDTO->inn,
            'RQ_OGRNIP' => $personDTO->regNum,
            'RQ_COMPANY_REG_DATE' => $personDTO->regDate,
            'UF_CRM_1688964741' => $personDTO->regNumOrg,
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
    private function processBankDetails(mixed $rqId, PersonDTO $personDTO): void
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

            foreach ($personDTO->bankDetails as $bank) {
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

            foreach ($personDTO->bankNominalDetails as $bank) {
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
            Logs\File::AddMessage($e->getMessage(),"Ошибки добавления банковских реквизитов", LOG_PERSON_SERVICE);
        }

    }
    private function processAddressRequisites(mixed $rqId, int $cardId, PersonDTO $personDTO): void
    {
        if (!isset($rqId) || !isset($personDTO->addressDetails) || !is_array($personDTO->addressDetails)) {
            return;
        }

        $AddressService = new AddressService($personDTO->addressDetails, $rqId, $cardId, 'person');
        $AddressService->init();
    }
    private function processRequisites(PersonDTO $personDTO): void
    {
        $requisiteData = new PersonRequisiteData(
            $this->personId,
            $personDTO
        );
        // единый метод сохраняет или обновляет реквизит + всё связанное
        $this->rqId = $this->requisiteService->save($requisiteData);

        /*$this->findCompanyRQ($this->personId, $personDTO->inn);

        Logs\File::AddMessage($this->rqId,"Найденный реквизит", LOG_PERSON_SERVICE);

        if ($this->rqId == 0) {
            $this->createRequisite($this->personId, $personDTO);
        } else {
            $this->updateRequisite($this->rqId, $personDTO);
        }

        $this->processAddressRequisites($this->rqId, $this->personId, $personDTO);
        $this->processBankDetails($this->rqId, $personDTO);*/
    }

    //endregion

    //region Обработка контактов компании
    private function processFM(PersonDTO $personDTO, Collection $existingCollection = null): Collection
    {
        $collection = $existingCollection ?? new Collection();

        foreach ($personDTO->contactDetails as $contact) {
            // Определяем тип поля
            switch ($contact['typeId']) {
                case 1:
                    $typeId = Phone::ID;
                    $valueType = $this->mapPhoneValueType($contact['valueType']);
                    break;
                case 2:
                    $typeId = Email::ID;
                    $valueType = $this->mapEmailValueType($contact['valueType']);
                    break;
                default:
                    continue 2; // Пропускаем неизвестные типы
            }

            // Создаем временный Value для проверки
            $tempValue = (new Value())
                ->setTypeId($typeId)
                ->setValueType($valueType)
                ->setValue($contact['valueText']);

            // 1. Проверяем полное совпадение
            if ($collection->has($tempValue)) {
                continue;
            }

            // 2. Проверяем дубли по значению (без учета типа)
            $existingValues = $this->findSimilarValues($collection, $typeId, $tempValue->getValue());

            if (!empty($existingValues)) {
                // Обновляем существующую запись
                $this->updateExistingValue($existingValues[0], $valueType);
                continue;
            }

            // 3. Добавляем как новое значение
            $collection->add($tempValue);


        }
        Logs\File::AddMessage($collection->toArray(), "fm processContacts", LOG_LEGAL_SERVICE);

        return $collection;
    }
    private function findSimilarValues(Collection $collection, string $typeId, string $value): array
    {
        $similar = [];
        foreach ($collection->filterByType($typeId) as $existingValue) {
            if ($typeId === Phone::ID) {
                // Для телефонов нормализуем перед сравнением
                if (normalizePhone($existingValue->getValue()) === normalizePhone($value)) {
                    $similar[] = $existingValue;
                }
            } else {
                // Для email и других типов сравниваем как есть (с точным соответствием)
                if ($existingValue->getValue() === $value) {
                    $similar[] = $existingValue;
                }
            }
        }
        return $similar;
    }
    private function updateExistingValue(Value $value, string $newValueType): void
    {
        // Обновляем только если тип изменился
        if ($value->getValueType() !== $newValueType) {
            $value->setValueType($newValueType);
        }
    }
    private function mapPhoneValueType(int $inputType): string
    {
        $map = [
            1 => Phone::VALUE_TYPE_MOBILE,
            2 => Phone::VALUE_TYPE_WORK,
            3 => Phone::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Phone::VALUE_TYPE_WORK;
    }
    private function mapEmailValueType(int $inputType): string
    {
        $map = [
            1 => Email::VALUE_TYPE_HOME,
            2 => Email::VALUE_TYPE_WORK,
            3 => Email::VALUE_TYPE_HOME,
        ];
        return $map[$inputType] ?? Email::VALUE_TYPE_WORK;
    }
    //endregion

    private function processContactPersons(PersonDTO $personDTO): void
    {
        $this->contactPersonService->set($this->personId,$personDTO->contactPersonDetails,"CO_");
    }
}