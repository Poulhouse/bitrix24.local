<?php

// Подключение Bitrix24 API

use Bitrix\Crm\CCrmDeal;
use Bitrix\Crm\CompanyTable;
use Bitrix\Iblock\Elements\ElementProductTable;
use Bitrix\Main\Loader;

class Bitrix24DataCollector
{
    private $companiesCache = [];
    private $productsCache = [];
    private $batchSize = 100; // Размер пакета

    public $companiesIds = [];
    private $productsData = [];
    public $dealsData = [];
    private $oborudovanies = []; // Массив для хранения данных по оборудованию
    private $potrebs = []; // Массив для хранения данных по потребностям

    public function __construct()
    {
        // Загружаем необходимые модули Битрикса
        Loader::includeModule('crm');
        Loader::includeModule('iblock');

        $this->companyFactory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $this->potrebFactory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(137);
        $this->dealFactory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
        $this->oborudFactory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(178);
        $this->companiesIds = [];
    }

    /**
     * Создает фильтр для получения сделок.
     */
    private function buildDealFilter($dealSignFilter, $previousYear, $reportYear, $clientFilter): array
    {
        $dealFilter = [
            [
                "LOGIC" => "OR",
                ["=STAGE_ID" => "UC_5JZP8K"],
                ["=STAGE_ID" => "WON"],
                ["=STAGE_ID" => "C2:3"],
                ["=STAGE_ID" => "C2:WON"],
                ["=STAGE_ID" => "C1:1"],
                ["=STAGE_ID" => "C1:WON"],
                ["=STAGE_ID" => "C4:1"],
                ["=STAGE_ID" => "C4:WON"],
                ["=STAGE_ID" => "C6:3"],
                ["=STAGE_ID" => "C6:WON"],
                ["=STAGE_ID" => "C3:1"],
                ["=STAGE_ID" => "C3:WON"],
                ["=STAGE_ID" => "C7:3"],
                ["=STAGE_ID" => "C7:WON"],
                ["=STAGE_ID" => "C8:3"],
                ["=STAGE_ID" => "C8:WON"],
                ["=STAGE_ID" => "C9:3"],
                ["=STAGE_ID" => "C9:WON"],
                ["=STAGE_ID" => "C10:3"],
                ["=STAGE_ID" => "C10:WON"]
            ],
            [
                "LOGIC" => "AND",
                ['!=COMPANY_ID' => 'NULL']
            ]
        ];

        // Применяем фильтр по признаку сделки
        if (!empty($dealSignFilter)) {
            $dealFilter[] = ["UF_CRM_1685703119" => $dealSignFilter];
        }

        if (!empty($clientFilter)) {
            $filter['%TITLE'] = $clientFilter;
            $companies = CompanyTable::getList([
                'filter' => $filter,
                'select' => ['ID']
            ]);
            while ($company = $companies->fetch()) {
                $filteredCompanyId = $company['ID'];
            }
            $dealFilter[] = ["=COMPANY_ID" => $filteredCompanyId];
        }

        // Применяем фильтр по году закрытия сделки
        if (!empty($previousYear)) {
            $startOfYear = new \Bitrix\Main\Type\DateTime("{$previousYear}-01-01 00:00:00", 'Y-m-d H:i:s');
            $endOfYear = new \Bitrix\Main\Type\DateTime("{$reportYear}-12-31 23:59:59", 'Y-m-d H:i:s');
            $dealFilter[] = [
                "LOGIC" => "AND",
                [
                    ">=CLOSEDATE" => $startOfYear,
                    "<=CLOSEDATE" => $endOfYear
                ]
            ];
        }

        return $dealFilter;
    }

    /**
     * Получает список сделок по заданному фильтру.
     */
    private function fetchDeals(array $dealFilter): array
    {
        $dbDeals = $this->dealFactory->getItems([
            'filter' => $dealFilter,
            'select' => ["ID", "TITLE", "STAGE_ID", "COMPANY_ID", "PRODUCT_ROWS", "PRODUCT_ID", "CLOSEDATE", "OPPORTUNITY", "UF_CRM_1685703119"]
        ]);

        $dealsArray = [];
        foreach ($dbDeals as $item) {
            $dealsArray[] = $item->getData();
        }

        return $dealsArray;
    }

    /**
     * Применяет фильтры к компаниям и возвращает массив ID компаний.
     */
    private function filterCompanies($clientFilter, $segmentFilter, $assignIdsFilter, $classificateFilter, $contractHavingFilter, $endActiveFilter): array
    {
        $filter = [];

        if (!empty($clientFilter)) {
            $filter['%TITLE'] = $clientFilter;
        }

        if (!empty($segmentFilter)) {
            $filter['UF_CRM_1695990672'] = $segmentFilter;
        }
        if (!empty($assignIdsFilter)) {
            $filter['ASSIGNED_BY_ID'] = $assignIdsFilter;
        }
        if (!empty($classificateFilter)) {
            $filter['UF_CRM_1723192691'] = $classificateFilter;
        }
        if (!empty($contractHavingFilter) && $contractHavingFilter !== "A") {
            $filter['UF_CRM_1729599807'] = $contractHavingFilter;
        }
        if (!empty($endActiveFilter) && $endActiveFilter !== "A") {
            $filter['UF_CRM_1726667216'] = $endActiveFilter;
        }

        $companies = CompanyTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'UF_CRM_1695990672', 'UF_CRM_1723192691', 'UF_CRM_1729599807', 'UF_CRM_1726667216']
        ]);

        $filteredCompanyIds = [];
        while ($company = $companies->fetch()) {
            $filteredCompanyIds[] = $company['ID'];
        }
        $this->companiesIds = $filteredCompanyIds;
        return $filteredCompanyIds;
    }

    /**
     * Обрабатывает товары сделки и добавляет их в данные отчета.
     */
    private function processDealProducts(array $deal, $productFilter, $productSegmentFilter): void
    {
        // Получаем товары сделки
        $productRows = \CCrmDeal::LoadProductRows($deal['ID']);

        $dealProducts = []; // Массив для хранения информации о всех продуктах данной сделки
        $hasValidProduct = false; // Флаг наличия подходящего продукта

        if (!empty($productRows)) {
            foreach ($productRows as $product) {
                $productRowId = $product['ID'];
                $productId = $product['PRODUCT_ID'];

                // Получаем данные о товаре
                $productData = ElementProductTable::getList([
                    'filter' => [
                        'ID' => $productId,
                        'IBLOCK_ID' => 14
                    ],
                    'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'SEGMENT_PROD']
                ])->fetch();
                if ($productData) {
                    // Получаем данные о секции (разделе товара)
                    $sectionId = $productData['IBLOCK_SECTION_ID'];
                    $section = \CIBlockSection::GetByID($sectionId)->Fetch();
                    $sectionName = $section['NAME'];

                    // Получаем данные о сегменте продукта
                    $segmentId = $productData['IBLOCK_ELEMENTS_ELEMENT_PRODUCT_SEGMENT_PROD_IBLOCK_GENERIC_VALUE'];
                    if (!empty($segmentId)) {
                        $SEGMENT_PROD = \CIBlockElement::GetList(array(), array(
                            "IBLOCK_ID" => 24,
                            '=ID' => $segmentId,
                        ), false, false, ['ID', 'NAME'])->fetch();
                    } else {
                        $SEGMENT_PROD['NAME'] = "";
                    }

                    // Проверка на фильтр по разделам продуктов
                    if (!empty($productFilter) && !in_array($productData['IBLOCK_SECTION_ID'], $productFilter, true)) {
                        // Пропускаем этот продукт, так как он не соответствует фильтру по разделу продуктов
                        continue; // Нашли хотя бы один подходящий продукт
                    }

                    // Проверка на фильтр по группе продуктов (SEGMENT_ID)
                    if (!empty($productSegmentFilter) && !in_array($segmentId, $productSegmentFilter, true)) {
                        // Пропускаем этот продукт, так как он не соответствует фильтру по сегменту
                        continue;
                    }


                    // Сохраняем данные о продукте в массиве для данной сделки
                    $dealProducts[] = [
                        'DEAL_ID' => $deal['ID'],
                        'PRODUCT_ID' => $productId,
                        'PRODUCT_ROW_ID' => $productRowId,
                        'PRODUCT_NAME' => $productData['NAME'],
                        'SECTION_NAME' => $sectionName,
                        'SECTION_ID' => $sectionId,
                        'SEGMENT_ID' => $SEGMENT_PROD['ID'],
                        'SEGMENT_NAME' => $SEGMENT_PROD['NAME'],
                        'QUANTITY' => $product['QUANTITY'], // Добавляем количество товара
                        //'PLAN_FOR_CURRENT_YEAR' => $planForCurrentYear // Добавляем план за текукущий год
                    ];

                    $hasValidProduct = true;
                }

                unset($SEGMENT_PROD);
            }
        }

        // Если фильтры пустые, выводим все сделки без фильтрации
        if (empty($productFilter) && empty($productSegmentFilter)) {
            $hasValidProduct = true;
        }

        // Если хотя бы один продукт в сделке соответствует фильтру, добавляем сделку в результат
        if ($hasValidProduct) {
            // Сохраняем всю информацию о продуктах в отфильтрованную сделку
            $this->dealProductsData[$deal['ID']] = $dealProducts;
            $this->dealsData[$deal['ID']] = $deal;
        }
    }

    /**
     * Этап 1: Получаем сделки и товары.
     */
    public function collectDealsAndProducts($filters)
    {

        $clientFilter = $filters['clientFilter'];
        $productFilter = $filters['productFilter'];
        $productSegmentFilter = $filters['productSegmentFilter'];
        $dealSignFilter = $filters['dealSignFilter'];
        $reportYear = $filters['reportYear'];
        $segmentFilter = $filters['segmentFilter'];
        $assignIdsFilter = $filters['assignIdsFilter'];
        $classificateFilter = $filters['classificateFilter'];
        $contractHavingFilter = $filters['contractHavingFilter'];
        $endActiveFilter = $filters['endActiveFilter'];


        // Шаг 1: Определяем годы для расчетов
        $previousYear = (string)($reportYear - 1);

        // Шаг 2: Формируем фильтр для сделок
        $dealFilter = $this->buildDealFilter($dealSignFilter, $previousYear, $reportYear, $clientFilter);

        // Шаг 3: Получаем сделки
        $dealsArray = $this->fetchDeals($dealFilter);

        // Шаг 4: Применяем фильтры к компаниям
        $filteredCompanyIds = $this->filterCompanies($clientFilter, $segmentFilter, $assignIdsFilter, $classificateFilter, $contractHavingFilter, $endActiveFilter);

        // Шаг 5: Обрабатываем каждую сделку
        foreach ($dealsArray as $deal) {
            if (!in_array($deal['COMPANY_ID'], $filteredCompanyIds)) {
                continue; // Пропускаем сделки, не соответствующие фильтру компаний
            }

            // Добавляем COMPANY_ID в список обработанных компаний
            if (!in_array($deal['COMPANY_ID'], $this->companiesIds)) {
                $this->companiesIds[] = $deal['COMPANY_ID'];
            }

            // Обрабатываем товары сделки
            $this->processDealProducts($deal, $productFilter, $productSegmentFilter);
        }
        unset($dealsArray);
    }

    /**
     * Получаем данные по оборудованию.
     */
    public function collectOborudovanies()
    {

        $oborudFilter = ['!=COMPANY_ID' => 'NULL'];

        /*if (!empty($productSegmentFilter)) {
            $oborudFilter['UF_CRM_7_1695914677'] = $productSegmentFilter;
        }*/

        // Получаем данные по оборудованию
        $oborudovanies = $this->oborudFactory->getItems([
            "filter" => $oborudFilter,
            "select" => ['*', "UF_*"]
        ]);

        foreach ($oborudovanies as $oborud) {
            $oborudStageId = $oborud['STAGE_ID'];
            if (str_contains($oborudStageId, 'NEW')) {
                $this->oborudovanies[$oborud->getId()] = $oborud->getData();
            }
        }
        unset($oborudovanies);
    }

    /**
     * Получаем данные по потребностям.
     */
    public function collectPotrebs($productFilter = [], $productSegmentFilter = [])
    {
        $potrebFilter = ['!=COMPANY_ID' => 'NULL'];

        if (!empty($productFilter)) {
            $potrebFilter = [
                '!=COMPANY_ID' => 'NULL',
                'UF_CRM_6_1695989715' => $productFilter
            ];
        }
        if (!empty($productSegmentFilter)) {
            $potrebFilter = [
                '!=COMPANY_ID' => 'NULL',
                'UF_CRM_6_1697461540' => $productSegmentFilter
            ];
        }

        if (!empty($productFilter) && !empty($productSegmentFilter)) {
            $potrebFilter = [
                '!=COMPANY_ID' => 'NULL',
                'UF_CRM_6_1695989715' => $productFilter,
                'UF_CRM_6_1697461540' => $productSegmentFilter
            ];
        }

        // Получаем данные по потребностям
        $potrebs = $this->potrebFactory->getItems([
            'filter' => $potrebFilter,
            'select' => ['*', "UF_*", "PARENT_ID_178"]
        ]);

        foreach ($potrebs as $potreb) {
            $this->potrebs[$potreb->getId()] = $potreb->getData();
        }
        unset($potrebs);
    }

    /**
     * Этап 2: Получаем данные о компаниях и формируем отчет.
     */
    public function generateReportData($filters)
    {
        $clientFilter = $filters['clientFilter'];
        $segmentFilter = $filters['segmentFilter'];
        $reportYear = $filters['reportYear'];
        $assignIdsFilter = $filters['assignIdsFilter'];
        $classificateFilter = $filters['classificateFilter'];
        $contractHavingFilter = $filters['contractHavingFilter'];
        $endActiveFilter = $filters['endActiveFilter'];
        $productFilter = $filters['productFilter'];
        $productSegmentFilter = $filters['productSegmentFilter'];


        $reportData = [];
        $dealSigns = [];

        // Получение признаков сделок
        $res = \CUserFieldEnum::GetList([], ["USER_FIELD_NAME" => "UF_CRM_1685703119"]);
        while ($dealSign = $res->Fetch()) {
            $dealSigns[$dealSign['ID']] = $dealSign['VALUE'];
        }

        // Формирование фильтра для получения компаний
        $filter = ['ID' => $this->companiesIds];
        /*if (!empty($clientFilter)) {
            $filter['%TITLE'] = $clientFilter;
        }*/
        /*if (!empty($segmentFilter)) {
            $filter['UF_CRM_1695990672'] = $segmentFilter;
        }
        if (!empty($assignIdsFilter)) {
            $filter['ASSIGNED_BY_ID'] = $assignIdsFilter;
        }
        if (!empty($classificateFilter)) {
            $filter['UF_CRM_1723192691'] = $classificateFilter;
        }
        if (!empty($contractHavingFilter) && $contractHavingFilter !== "A") {
            $filter['UF_CRM_1729599807'] = $contractHavingFilter;
        }
        if (!empty($endActiveFilter) && $endActiveFilter !== "A") {
            $filter['UF_CRM_1726667216'] = $endActiveFilter;
        }*/

        // Получение списка компаний
        $companies = CompanyTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'UF_CRM_1695990672', 'UF_CRM_1723192691', 'UF_CRM_1729599807', 'UF_CRM_1726667216']
        ]);


        // Определяем годы для расчетов
        $previousYear = $reportYear - 1;

        // Инициализация массива компаний
        $arCompaniesId = [];
        $potrebCompaniesId = [];

        while ($company = $companies->fetch()) {
            $companyId = $company['ID'];
            $companyTitle = $company['TITLE'];
            $companyAssignId = $company['ASSIGNED_BY_ID'];
            $companyClassificateValue = "Нет категории";
            $companyClassificateId = $company['UF_CRM_1723192691'];
            $companyContractHavingValue = "Нет";
            $companyContractHaving = $company['UF_CRM_1729599807'];
            $companyEndActiveValue = "Нет";
            $companyEndActive = $company['UF_CRM_1726667216'];
            $clientSegment = "";
            $clientSegmentId = $company['UF_CRM_1695990672'];

            // Применение фильтров на уровне генерации отчета
            if (!empty($clientFilter) && strpos($companyTitle, $clientFilter) === false) {
                continue;
            }

            // Получение имени клиента
            if (isset($clientSegmentId)) {
                $enumValue = CIBlockElement::GetList(
                    [],
                    ["IBLOCK_ID" => 21, 'ID' => $clientSegmentId],
                    false,
                    false,
                    ['NAME']
                )->fetch();
                $clientSegment = $enumValue['NAME'] ?? '';
                unset($enumValue); // Освобождаем память после использования
            }

            // Классификация компании
            if (isset($companyClassificateId)) {
                if ($companyClassificateId == 2097) $companyClassificateValue = 'Категория A';
                elseif ($companyClassificateId == 2098) $companyClassificateValue = 'Категория B';
                elseif ($companyClassificateId == 2099) $companyClassificateValue = 'Категория C';
                else  $companyClassificateValue = 'Нет категории';
            }

            // Обработка поля "Прекратил деятельность"
            if (isset($companyEndActive)) {
                if ($companyEndActive === true || $companyEndActive == '1') {
                    $companyEndActiveValue = 'Да';
                } elseif ($companyEndActive === false || $companyEndActive == '0') {
                    $companyEndActiveValue = 'Нет';
                } else {
                    $companyEndActiveValue = 'Нет';
                }
            }

            // Обработка поля "Контрактодержатели"
            if (isset($companyContractHaving)) {
                if ($companyContractHaving === true || $companyContractHaving == '1') {
                    $companyContractHavingValue = 'Да';
                } elseif ($companyContractHaving === false || $companyContractHaving == '0') {
                    $companyContractHavingValue = 'Нет';
                } else {
                    $companyContractHavingValue = 'Нет';
                }
            }

            // Назначенный пользователь
            $assignedUser = \Bitrix\Main\UserTable::getList([
                'filter' => ['ID' => $companyAssignId],
                'select' => ['ID', 'NAME', 'LAST_NAME']
            ])->fetch();
            $assigned = $assignedUser ? ($assignedUser['NAME'] . ' ' . $assignedUser['LAST_NAME']) : '';
            unset($assignedUser); // Освобождаем память после использования

            // Обработка оборудования компании
            $oborudovaniesForCompany = array_filter($this->oborudovanies, function ($oborud) use ($companyId) {
                return $oborud['COMPANY_ID'] == $companyId;
            });

            // Обработка потребностей компании
            $potrebsForCompany = array_filter($this->potrebs, function ($potreb) use ($companyId) {

                if ($potreb['COMPANY_ID'] == $companyId) {
                    return true;
                } else {
                    return false;
                }
            });

            // Инициализация переменных
            $factByDeal = [];
            $factByDealSign = [];
            $factByProductGroup = [];
            $factForLastYear = [];
            $factForLastYearByProductGroup = [];
            $factBySectionIdTotal = [];
            $factForLastYearBySectionIdTotal = [];
            $planForCurrentYear = 0;
            $yearRequirement = 0;
            $potrebId = 0;

            // Обработка сделок
            foreach ($this->dealsData as $dealId => $deal) {
                if ($deal['COMPANY_ID'] == $companyId) {

                    $arCompaniesId[] = $companyId; // Добавляем ID компании в список обработанных
                    $dealTitle = $deal['TITLE'];
                    $dealCloseDate = $deal['CLOSEDATE'];
                    $dealStageId = $deal['STAGE_ID'];
                    $dealSign = ($dealSigns[$deal['UF_CRM_1685703119']] ?? '-'); // Если значение не найдено, то прочерк
                    if (isset($this->dealProductsData[$dealId])) {
                        foreach ($this->dealProductsData[$dealId] as $productInfo) {
                            $sectionId = $productInfo['SECTION_ID'];
                            $productId = $productInfo['PRODUCT_ID'];
                            $productRowId = $productInfo['PRODUCT_ROW_ID'];
                            $quantityInTons = round($productInfo['QUANTITY'] / 1000, 6); // Переводим количество из кг в тонны

                            // Фильтруем только сделки, завершенные в предыдущем году
                            if ((string)$dealCloseDate->format('Y') == (string)$previousYear) {
                                $factForLastYear[$dealId][$productId][$productRowId] = ($factForLastYear[$dealId][$productId][$productRowId] ?? 0) + $quantityInTons;
                                if (!isset($factForLastYearBySectionIdTotal[$sectionId])) {
                                    $factForLastYearBySectionIdTotal[$sectionId] = 0;
                                }
                                $factForLastYearBySectionIdTotal[$sectionId] += $quantityInTons;
                            }

                            // Фильтруем только сделки, завершенные в текущем году
                            if ((string)$dealCloseDate->format('Y') == (string)$reportYear) {
                                $factByDeal[$dealId][$productId][$productRowId] = ($factByDeal[$dealId][$productId][$productRowId] ?? 0) + $quantityInTons;
                                if (!isset($factBySectionIdTotal[$sectionId])) {
                                    $factBySectionIdTotal[$sectionId] = 0;
                                }
                                $factBySectionIdTotal[$sectionId] += $quantityInTons;
                            }

                        }

                        foreach ($this->dealProductsData[$dealId] as $productInfo) {
                            $productId = $productInfo['PRODUCT_ID'];
                            $sectionId = $productInfo['SECTION_ID'];
                            $segmentId = $productInfo['SEGMENT_ID'];
                            $productRowId = $productInfo['PRODUCT_ROW_ID'];
                            $planForCurrentYear = round($this->getPlanForCurrentYear($sectionId, $companyId, $reportYear) / 1000, 3);

                            // Расчет годовой потребности
                            $yearRequirement = 0;
                            foreach ($potrebsForCompany as $potreb) {
                                if ($potreb['UF_CRM_6_1695989715'] == $sectionId) {
                                    $consumptionPeriod = (float)$potreb['UF_CRM_6_1695825921'] + (float)$potreb['UF_CRM_6_1695826233']; // Потребление + доливка
                                    $fullFillingPeriod = $potreb['UF_CRM_6_1695826059']; // Период полной заливки
                                    foreach ($oborudovaniesForCompany as $oborud) {
                                        if ($potreb['PARENT_ID_178'] == $oborud['ID'] && str_contains($oborud['STAGE_ID'], 'NEW')) {
                                            if ($fullFillingPeriod > 0) {
                                                $yearRequirement += round($oborud['UF_CRM_7_1695914677'] * $consumptionPeriod * (1 / $fullFillingPeriod / 1000), 7);
                                            } else {
                                                $yearRequirement += 0;
                                            }
                                        }
                                    }
                                }
                            }

                            // Добавление данных в отчет
                            $reportData[$companyId][] = [
                                'COMPANY_TITLE' => $companyTitle,
                                'CLIENT_SEGMENT' => $clientSegment,
                                'CLASSIFICATE' => $companyClassificateValue,
                                'END_ACTIVE' => $companyEndActiveValue,
                                'CONTRACT_HAVING' => $companyContractHavingValue,
                                'ASSIGNED' => $assigned,
                                'PRODUCT_NAME' => $productInfo['PRODUCT_NAME'],
                                'SECTION_ID' => $sectionId,
                                'SECTION_NAME' => $productInfo['SECTION_NAME'],
                                'SEGMENT_ID' => $productInfo['SEGMENT_ID'],
                                'SEGMENT_NAME' => $productInfo['SEGMENT_NAME'],
                                'DEAL_ID' => $dealId,
                                'DEAL_CLOSEDATE' => $dealCloseDate->format('Y'),
                                'DEAL_TITLE' => $dealTitle,
                                'DEAL_SIGN' => $dealSign,
                                'YEAR_REQUIREMENT' => number_format(round($yearRequirement, 3), 3, ',', ''),
                                'FACT_BY_DEAL' => $factByDeal[$dealId][$productId][$productRowId] ?? 0,
                                'FACT_BY_DEAL_SIGN' => $factByDealSign[$dealSign] ?? 0,
                                'FACT_BY_SECTION_ID' => $factBySectionIdTotal[$sectionId] ?? 0,
                                'FACT_FOR_LAST_YEAR' => $factForLastYear[$dealId][$productId][$productRowId] ?? 0,
                                'FACT_FOR_LAST_YEAR_BY_SECTION_ID' => $factForLastYearBySectionIdTotal[$sectionId] ?? 0,
                                'PLAN_FOR_CURRENT_YEAR' => $planForCurrentYear,
                                'PERCENT_PLAN_COMPLETION' => 0,
                            ];
                        }
                    }
                }
            }
            foreach ($potrebsForCompany as $potreb) {
                $potrebCompaniesId[] = $potreb['COMPANY_ID'];
            }


            if (!in_array($companyId, $arCompaniesId)) {
                if (in_array($companyId, $potrebCompaniesId)) {
                    // Расчет годовой потребности
                    $yearRequirement = 0;
                    foreach ($potrebsForCompany as $potreb) {
                        $potrebId = $potreb['ID'];
                        if ($potreb['UF_CRM_6_1695989715'] == $sectionId) {
                            $consumptionPeriod = (float)$potreb['UF_CRM_6_1695825921'] + (float)$potreb['UF_CRM_6_1695826233']; // Потребление + доливка
                            $fullFillingPeriod = $potreb['UF_CRM_6_1695826059']; // Период полной заливки
                            foreach ($oborudovaniesForCompany as $oborud) {
                                if ($potreb['PARENT_ID_178'] == $oborud['ID'] && str_contains($oborud['STAGE_ID'], 'NEW')) {
                                    if ($fullFillingPeriod > 0) {
                                        $yearRequirement += round($oborud['UF_CRM_7_1695914677'] * $consumptionPeriod * (1 / $fullFillingPeriod / 1000), 7);
                                    } else {
                                        $yearRequirement += 0;
                                    }
                                }
                            }
                        }
                    }
                    if ($potrebId > 0) {
                        // Добавление данных в отчет для компаний без сделок
                        $reportData[$companyId][] = [
                            'COMPANY_TITLE' => $companyTitle,
                            'CLIENT_SEGMENT' => $clientSegment,
                            'CLASSIFICATE' => $companyClassificateValue,
                            'END_ACTIVE' => $companyEndActiveValue,
                            'CONTRACT_HAVING' => $companyContractHavingValue,
                            'ASSIGNED' => $assigned,
                            'PRODUCT_NAME' => '',
                            'SECTION_ID' => $sectionId ?? 0,
                            'SECTION_NAME' => '',
                            'SEGMENT_ID' => '',
                            'SEGMENT_NAME' => '',
                            'DEAL_ID' => 0,
                            'DEAL_CLOSEDATE' => '',
                            'DEAL_TITLE' => '',
                            'DEAL_SIGN' => '',
                            'YEAR_REQUIREMENT' => number_format(round($yearRequirement, 3), 3, ',', ''),
                            'FACT_BY_DEAL' => 0,
                            'FACT_BY_DEAL_SIGN' => 0,
                            'FACT_BY_SECTION_ID' => 0,
                            'FACT_FOR_LAST_YEAR' => 0,
                            'FACT_FOR_LAST_YEAR_BY_SECTION_ID' => 0,
                            'PLAN_FOR_CURRENT_YEAR' => $planForCurrentYear,
                            'PERCENT_PLAN_COMPLETION' => 0,
                            'POTREB_ID' => $potrebId,
                        ];
                    }
                }
            }
        }

        $reportData = $this->addNewProductsWithoutDeals($reportData, $productFilter, $productSegmentFilter, $reportYear);
        $reportData = $this->sortBySectionNameProduct($reportData);
        $reportData = $this->setFactBySectionId($reportData);
        $reportData = $this->setFactByDealSign($reportData);
        $reportData = $this->applyFilter($reportData, $productFilter, $productSegmentFilter);


        return $reportData;
    }

    /**
     * Получает план на текущий год для компании из кэшированных данных.
     *
     * @param int $companyId ID компании.
     * @return float План на текущий год.
     */
    public function getPlanForCurrentYear($potrebsForSectionId, $companyId, $reportYear)
    {
        global $DB;
        //echo date('Y');

        //if($reportYear == date('Y')) {
        $fieldLabel = "План " . $reportYear . "г, кг";
        /*} else {
            $fieldLabel = "Проект " . $reportYear."г.";
        }*/

        //echo $fieldLabel;

        $planValue[$potrebsForSectionId] = 0;

        // Выполнение SQL-запроса для поиска пользовательского поля
        $sql = "SELECT UF.FIELD_NAME
	                FROM b_user_field UF
	                INNER JOIN b_user_field_lang UFL ON UFL.USER_FIELD_ID = UF.ID
	                WHERE UF.ENTITY_ID = 'CRM_6' AND UFL.LANGUAGE_ID = 'ru' AND UFL.EDIT_FORM_LABEL LIKE '%{$fieldLabel}%'";

        $res = $DB->Query($sql);
        if ($field = $res->Fetch()) {
            $planFieldId = $field['FIELD_NAME']; // Поле FIELD_NAME содержит имя пользовательского поля (например, UF_CRM_XXXXX)
            if ($planFieldId) {
                // Используем кэшированные данные компании
                $potrebForSection = array_filter($this->potrebs, function ($potreb) use ($potrebsForSectionId, $companyId) {
                    return $potreb['UF_CRM_6_1695989715'] == $potrebsForSectionId && $potreb['COMPANY_ID'] ==
                        $companyId;
                });


                foreach ($potrebForSection as $_potrebId => $_potreb) {
                    if (isset($_potreb[$planFieldId])) {
                        $planValue[$potrebsForSectionId] += floatval($_potreb[$planFieldId]); // Преобразуем
                        // значение в float
                    }
                }

            }
        }
        unset($res, $sql); // Освобождаем память после использования

        return $planValue[$potrebsForSectionId];
    }

    public function sortBySectionNameProduct($reportData)
    {

        foreach ($reportData as $companyId => &$companyData) {
            usort($companyData, function ($a, $b) {
                return strcmp($a['SECTION_NAME'], $b['SECTION_NAME']);
            });
        }
        unset($companyData); // Чистим ссылку на последний элемент для избежания багов

        return $reportData;
    }

    public function addNewProductsWithoutDeals($reportData, $productFilter, $productSegmentFilter, $reportYear)
    {

        foreach ($reportData as $companyId => &$companyData) {


            // Обработка оборудования компании
            $oborudovaniesForCompany = array_filter($this->oborudovanies, function ($oborud) use ($companyId) {
                return $oborud['COMPANY_ID'] == $companyId;
            });

            // Обработка потребностей компании
            $potrebsForCompany = array_filter($this->potrebs, function ($potreb) use ($companyId) {
                return $potreb['COMPANY_ID'] == $companyId;
            });

            $matchingPotreb = [];
            $uniqueDeals = [];
            $potrebsIds = [];

            // 2. Проходим по каждой сделке компании
            foreach ($companyData as $dealIndex => $deal) {
                if (!in_array($deal['SECTION_ID'], $potrebsIds)) {
                    $potrebsIds[] = $deal['SECTION_ID'];
                    $uniqueDeals[] = $deal;
                }
            }

            // Функция сортировки по SECTION_ID
            usort($uniqueDeals, function ($a, $b) {
                return $a['SECTION_ID'] <=> $b['SECTION_ID'];
            });

            $uniquePotrebs = [];
            $sectionPotrebsIds = [];

            /*foreach ($potrebsForCompany as $potreb) {
                if (!in_array($potreb['UF_CRM_6_1695989715'], $sectionPotrebsIds))
                {
                    $sectionPotrebsIds[] = $potreb['UF_CRM_6_1695989715'];
                    $uniquePotrebs[] = $potreb;
                }
            }*/
            usort($potrebsForCompany, function ($a, $b) {
                return $a['UF_CRM_6_1695989715'] <=> $b['UF_CRM_6_1695989715'];
            });

            // Сравнение отсортированных массивов
            $matchingPotreb = [];
            $dealIndex = 0;
            $potrebIndex = 0;


            while ($dealIndex < count($uniqueDeals) && $potrebIndex < count($potrebsForCompany)) {
                $deal = $uniqueDeals[$dealIndex];
                $potreb = $potrebsForCompany[$potrebIndex];

                //echo"1 <pre>";print_r($deal);echo"</pre>";

                if (isset($deal['POTREB_ID']) && (string)$deal['POTREB_ID'] === (string)$potreb['ID']) {
                    //echo "<pre>". $deal['SECTION_ID'] . " Есть POTREB_ID добавляем в matchingPotreb </pre>";
                    $matchingPotreb[] = $potreb;
                    $potrebIndex++;
                } // Если SECTION_ID сделки совпадает с UF_CRM_6_1695989715 потребности
                elseif ((string)$deal['SECTION_ID'] === (string)$potreb['UF_CRM_6_1695989715']) {
                    //echo "<pre>". $deal['SECTION_ID'] . " Пропускаем эту потребность, так как она не должна быть в matchingPotreb </pre>";
                    // Пропускаем эту потребность, так как она не должна быть в matchingPotreb
                    $potrebIndex++;
                } elseif ((string)$deal['SECTION_ID'] < (string)$potreb['UF_CRM_6_1695989715']) {
                    //echo "<pre>". $deal['SECTION_ID'] . " Двигаемся по массиву сделок </pre>";
                    $dealIndex++;
                } else {
                    //echo "<pre>". $deal['SECTION_ID'] . " Двигаемся по массиву потребностей и добавляем потребность, если она не совпала </pre>";
                    // Двигаемся по массиву потребностей и добавляем потребность, если она не совпала
                    $matchingPotreb[] = $potreb;
                    $potrebIndex++;
                }

            }

            // Добавляем оставшиеся потребности, которые не были сопоставлены
            while ($potrebIndex < count($potrebsForCompany)) {
                $matchingPotreb[] = $potrebsForCompany[$potrebIndex];
                $potrebIndex++;
            }


            // 4. Если потребность не найдена, добавляем её в массив сделок как новую потребность
            foreach ($matchingPotreb as $key => &$potreb) {


                $potrebsForCompany[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] = 0;

                $consumptionPeriod = (float)$potreb['UF_CRM_6_1695825921'] + (float)$potreb['UF_CRM_6_1695826233']; // Потребление + доливка
                $fullFillingPeriod = $potreb['UF_CRM_6_1695826059']; // Период полной заливки
                foreach ($oborudovaniesForCompany as $oborud) {
                    // Учитываем только оборудование стадии 'NEW'
                    if ($potreb['PARENT_ID_178'] == $oborud['ID'] && str_contains($oborud['STAGE_ID'], 'NEW')) {
                        if ($fullFillingPeriod > 0) {
                            $potreb['YEAR_REQUIREMENT'] = $potreb['YEAR_REQUIREMENT'] + round($oborud['UF_CRM_7_1695914677'] * $consumptionPeriod * (1 / $fullFillingPeriod / 1000), 7); // Расчет годовой потребности в тоннах
                            $potrebsForCompany[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] = $potrebsForCompany[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] + round($oborud['UF_CRM_7_1695914677'] * $consumptionPeriod * (1 / $fullFillingPeriod / 1000), 7); // Расчет годовой потребности в тоннах

                        } else {
                            $potreb['YEAR_REQUIREMENT'] = $potreb['YEAR_REQUIREMENT'] + 0; // Или другое значение по умолчанию, если период заполнения равен 0
                            $potrebsForCompany[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] = $potrebsForCompany[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] + 0; // Или другое значение по умолчанию, если период заполнения равен 0
                        }
                    }

                }
                if ($companyId == 6401) {
                    $ar[$potreb['UF_CRM_6_1695989715']]['TITLE'] = $potreb['TITLE'];
                    $ar[$potreb['UF_CRM_6_1695989715']]['SECTION_ID'] = $potreb['UF_CRM_6_1695989715'];
                    $ar[$potreb['UF_CRM_6_1695989715']]['_YEAR_REQUIREMENT'] = $potreb['YEAR_REQUIREMENT'];
                    $ar[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] = $ar[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'] + $potreb['YEAR_REQUIREMENT'];

                }


                /* echo "<pre>";
                 print_r($uniquePotrebs);
                 echo "</pre>";*/
            }
            if ($companyId == 6401) {
                //$ar['__YEAR_REQUIREMENT'] = $uniquePotrebs[$potreb['UF_CRM_6_1695989715']]['YEAR_REQUIREMENT'];

                /* echo "<pre>";
                 print_r($ar);
                 echo "</pre>";*/
            }

            unset($potreb);
            foreach ($matchingPotreb as $key => $potreb) {

                $sectionId = $potrebSectionId = $potreb['UF_CRM_6_1695989715'];
                $segmentId = 0;
                $SEGMENT_PROD['NAME'] = "";

                /*if($companyId == 6509) {
                    echo $potreb['TITLE'] . "<br>";
                    echo $potrebSectionId . "<br>";
                    echo "<br>";
                }*/
                //foreach ($uniquePotrebs as $potrebSectionId => $uniquePotreb) {
                $planForCurrentYear = round($this->getPlanForCurrentYear($potrebSectionId, $companyId, $reportYear) / 1000, 3);
                // Получаем данные о секции (разделе товара)
                $section = \CIBlockSection::GetByID($potrebSectionId)->Fetch();
                $sectionName = $section['NAME'];

                // Получаем данные о товаре
                $productData = ElementProductTable::getList([
                    'filter' => [
                        'IBLOCK_SECTION_ID' => $potrebSectionId,
                        'IBLOCK_ID' => 14
                    ],
                    'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'SEGMENT_PROD']
                ])->fetch();

                if ($productData) {
                    $hasValidProduct = true;
                    // Получаем данные о сегменте продукта
                    $segmentId = $productData['IBLOCK_ELEMENTS_ELEMENT_PRODUCT_SEGMENT_PROD_IBLOCK_GENERIC_VALUE'];
                    if (!empty($segmentId)) {
                        $SEGMENT_PROD = \CIBlockElement::GetList(array(), array(
                            "IBLOCK_ID" => 24,
                            '=ID' => $segmentId,
                        ), false, false, ['ID', 'NAME'])->fetch();
                    }

                    // Проверка на фильтр по разделам продуктов
                    if (!empty($productFilter) && !in_array($sectionId, $productFilter, true)) {
                        // Пропускаем этот продукт, так как он не соответствует фильтру по разделу продуктов
                        continue; // Нашли хотя бы один подходящий продукт
                    }

                    // Проверка на фильтр по группе продуктов (SEGMENT_ID)
                    if (!empty($productSegmentFilter) && !in_array($segmentId, $productSegmentFilter, true)) {
                        // Пропускаем этот продукт, так как он не соответствует фильтру по сегменту
                        continue;
                    }

                    $newDeal = [
                        'COMPANY_TITLE' => $deal['COMPANY_TITLE'],
                        'CLIENT_SEGMENT' => $deal['CLIENT_SEGMENT'],
                        'CLIENT_SEGMENT_ID' => $deal['CLIENT_SEGMENT_ID'],
                        'CLASSIFICATE' => $deal['CLASSIFICATE'],
                        'END_ACTIVE' => $deal['END_ACTIVE'],
                        'CONTRACT_HAVING' => $deal['CONTRACT_HAVING'],
                        'ASSIGNED' => $deal['ASSIGNED'],
                        'PRODUCT_NAME' => $potreb['TITLE'], // Можем оставить пустым или заполнить из потребности
                        'SECTION_ID' => $potrebSectionId,
                        'SECTION_NAME' => $sectionName,
                        "SEGMENT_ID" => $segmentId,
                        "SEGMENT_NAME" => $SEGMENT_PROD['NAME'],
                        'DEAL_ID' => 0, // Поскольку это не сделка, можем оставить пустым
                        'DEAL_CLOSEDATE' => intval(date('Y')),
                        'DEAL_TITLE' => 'Сделок нет', // Помечаем, что это потребность
                        'DEAL_SIGN' => '-', // Или любое другое значение
                        'YEAR_REQUIREMENT' => number_format(round($ar[$potrebSectionId]['YEAR_REQUIREMENT'], 3), 3, ',', ''), //
                        // Используем данные потребности
                        'FACT_BY_DEAL' => 0, // Устанавливаем в 0
                        'PLAN_FOR_CURRENT_YEAR' => $planForCurrentYear ?? 0,
                        'PERCENT_PLAN_COMPLETION' => 0,
                        // Добавляем любые другие поля, которые требуются
                    ];

                    // 5. Добавляем новую потребность в массив сделок
                    $companyData[] = $newDeal;

                    /*$countCompanyData = count($companyData);
                    if ($companyId == 6400) {
                        echo "Продукт: " . $potreb['TITLE'] . " - " . $countCompanyData . "<br>";
                    }
                    if ($countCompanyData > 1) {
                        if(isset($companyData[0]['POTREB_ID'])) {
                            $companyData = array_splice($companyData, 2, $countCompanyData);
                        } else {
                            $companyData = array_splice($companyData, 1, $countCompanyData);
                        }
                    }*/
                } else {
                    $hasValidProduct = false;
                    $newDeal = [
                        'COMPANY_TITLE' => $deal['COMPANY_TITLE'],
                        'CLIENT_SEGMENT' => $deal['CLIENT_SEGMENT'],
                        'CLIENT_SEGMENT_ID' => $deal['CLIENT_SEGMENT_ID'],
                        'CLASSIFICATE' => $deal['CLASSIFICATE'],
                        'END_ACTIVE' => $deal['END_ACTIVE'],
                        'CONTRACT_HAVING' => $deal['CONTRACT_HAVING'],
                        'ASSIGNED' => $deal['ASSIGNED'],
                        'PRODUCT_NAME' => $potreb['TITLE'], // Можем оставить пустым или заполнить из потребности
                        'SECTION_ID' => $potrebSectionId,
                        'SECTION_NAME' => $sectionName,
                        "SEGMENT_ID" => $segmentId,
                        "SEGMENT_NAME" => $SEGMENT_PROD['NAME'],
                        'DEAL_ID' => 0, // Поскольку это не сделка, можем оставить пустым
                        'DEAL_CLOSEDATE' => intval(date('Y')),
                        'DEAL_TITLE' => 'Сделок нет', // Помечаем, что это потребность
                        'DEAL_SIGN' => '-', // Или любое другое значение
                        'YEAR_REQUIREMENT' => number_format(round($ar[$potrebSectionId]['YEAR_REQUIREMENT'], 3), 3, ',', ''), //
                        // Используем данные потребности
                        'FACT_BY_DEAL' => 0, // Устанавливаем в 0
                        'PLAN_FOR_CURRENT_YEAR' => $planForCurrentYear ?? 0,
                        'PERCENT_PLAN_COMPLETION' => 0,
                        // Добавляем любые другие поля, которые требуются
                    ];


                    // 5. Добавляем новую потребность в массив сделок
                    $companyData[0] = $newDeal;
                }
                //}
            }


            unset($matchingPotreb);
            unset($oborudovaniesForCompany);
            if (isset($companyData[0]['POTREB_ID'])) unset($companyData[0]);
        }

        unset($companyData); // Чистим ссылку на последний элемент для избежания багов
        return $reportData;
    }

    public function setFactBySectionId($reportData)
    {
        foreach ($reportData as $companyId => &$companyData) {
            // Переменные для отслеживания текущего SECTION_ID и суммы по нему
            $currentSectionId = null;
            $sumFactBySection = 0;
            $sumPlanBySection = 0;  // Для корректного расчета процента выполнения плана
            $sumFactForLastYear = 0;
            $sectionDealsCount = 0;

            foreach ($companyData as &$deal) {
                $SECTION_ID = $deal['SECTION_ID'];

                // Проверяем, изменился ли SECTION_ID
                if ($currentSectionId !== null && $SECTION_ID !== $currentSectionId) {

                    // Применяем накопленные значения к предыдущей группе сделок
                    foreach ($companyData as &$prevDeal) {
                        if ($prevDeal['SECTION_ID'] === $currentSectionId) {
                            $prevDeal['FACT_BY_SECTION_ID'] = $sumFactBySection;
                            $prevDeal['FACT_FOR_LAST_YEAR_BY_SECTION_ID'] = $sumFactForLastYear;
                            $prevDeal['PLAN_FOR_CURRENT_YEAR'] = $sumPlanBySection;
                            // Рассчитываем процент выполнения плана
                            $prevDeal['PERCENT_PLAN_COMPLETION'] = ($sumPlanBySection > 0) ?
                                round(($sumFactBySection / $sumPlanBySection) * 100, 2) : 0;
                        }
                    }

                    // Сбрасываем суммы и начинаем накопление для нового SECTION_ID
                    $sumFactBySection = 0;
                    $sumPlanBySection = 0;  // Сброс плана для новой группы
                    $sumFactForLastYear = 0;
                    $sectionDealsCount = 0;
                }

                // Накопление значений для текущего SECTION_ID
                $sumFactBySection += $deal['FACT_BY_DEAL'];
                $sumPlanBySection = $deal['PLAN_FOR_CURRENT_YEAR'];  // Используем PLAN_FOR_CURRENT_YEAR для накопления плана
                $sumFactForLastYear += $deal['FACT_FOR_LAST_YEAR'];
                $sectionDealsCount++;


                // Обновляем текущий SECTION_ID
                $currentSectionId = $SECTION_ID;
            }

            // Применяем накопленные значения для последней группы сделок
            if ($currentSectionId !== null) {
                foreach ($companyData as &$deal) {
                    if ($deal['SECTION_ID'] === $currentSectionId) {
                        $deal['FACT_BY_SECTION_ID'] = $sumFactBySection;
                        $deal['FACT_FOR_LAST_YEAR_BY_SECTION_ID'] = $sumFactForLastYear;
                        $deal['PLAN_FOR_CURRENT_YEAR'] = $sumPlanBySection;
                        // Рассчитываем процент выполнения плана для последней группы
                        $deal['PERCENT_PLAN_COMPLETION'] = ($sumPlanBySection > 0) ? round(($sumFactBySection / $sumPlanBySection) * 100, 2) : 0;

                    }
                }
            }


        }

        unset($companyData); // Чистим ссылку на последний элемент для избежания багов
        return $reportData;
    }

    public function setFactByDealSign($reportData)
    {
        foreach ($reportData as $companyId => &$companyData) {
            // Переменные для отслеживания текущего DEAL_SIGN и суммы по нему
            $currentDealSign = null;
            $currentSectionId = null;
            $sumFactByDealSign = 0;
            $dealSignDealsCount = 0;  // Счетчик сделок для подсчета среднего процента выполнения плана

            foreach ($companyData as &$deal) {
                $DEAL_SIGN = $deal['DEAL_SIGN'];
                $SECTION_ID = $deal['SECTION_ID'];

                // Логика изменения как DEAL_SIGN, так и SECTION_ID
                // Если либо DEAL_SIGN, либо SECTION_ID изменились, сохраняем накопленные данные
                if (($currentSectionId !== null && $SECTION_ID !== $currentSectionId) || ($currentDealSign !== null && $DEAL_SIGN !== $currentDealSign)) {
                    // Применяем накопленные значения к предыдущей группе сделок
                    foreach ($companyData as &$prevDeal) {
                        if ($prevDeal['SECTION_ID'] === $currentSectionId && $prevDeal['DEAL_SIGN'] === $currentDealSign) {
                            $prevDeal['FACT_BY_DEAL_SIGN'] = $sumFactByDealSign;
                        }
                    }

                    // Сбрасываем суммы и начинаем накопление для новой группы (по DEAL_SIGN и SECTION_ID)
                    $sumFactByDealSign = 0;
                    $dealSignDealsCount = 0;
                }
                // Накопление значений для текущего DEAL_SIGN и SECTION_ID
                $sumFactByDealSign += $deal['FACT_BY_DEAL'];
                $dealSignDealsCount++;

                // Обновляем текущие значения DEAL_SIGN и SECTION_ID
                $currentDealSign = $DEAL_SIGN;
                $currentSectionId = $SECTION_ID;
            }

            // Применяем накопленные значения для последней группы сделок
            if ($currentSectionId !== null && $currentDealSign !== null) {
                foreach ($companyData as &$deal) {
                    if ($deal['SECTION_ID'] === $currentSectionId && $deal['DEAL_SIGN'] === $currentDealSign) {
                        $deal['FACT_BY_DEAL_SIGN'] = $sumFactByDealSign;
                    }
                }
            }

        }

        return $reportData;
    }

    public function applyFilter($reportData, $productFilter, $productGroupFilter)
    {
        $reportDataNew = [];
        foreach ($reportData as $companyId => &$companyData) {

            foreach ($companyData as $dealIndex => $deal) {
                $hasValidProduct = false;
                // Проверка на фильтр по разделам продуктов
                if (!empty($productFilter) && !in_array($deal['SECTION_ID'], $productFilter, true)) {
                    // Пропускаем этот продукт, так как он не соответствует фильтру по разделу продуктов
                    continue; // Нашли хотя бы один подходящий продукт
                }

                // Проверка на фильтр по группе продуктов (SEGMENT_ID)
                if (!empty($productGroupFilter) && !in_array($deal['SEGMENT_ID'], $productGroupFilter, true)) {
                    // Пропускаем этот продукт, так как он не соответствует фильтру по сегменту
                    continue;
                }
                $hasValidProduct = true;


                $companyData[$dealIndex] = $deal;

            }


        }
        unset($companyData);

        return $reportData;

    }
}