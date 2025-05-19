<?php
// Подключение Bitrix24 API

use Bitrix\Crm\CCrmDeal;
use Bitrix\Iblock\Elements\ElementProductTable;

// Подключаем CCrmDeal для получения товаров сделки

class Bitrix24ReportGenerator
{
    public function __construct()
    {
        //$this -> companiesData = $companiesData;
    }

    public function generateReport($companiesData, $filters): void
    {
        $showDealSignDetail = $filters['showDealSignDetail'];
        $showDealDetail = $filters['showDealDetail'];
        $reportYear = $filters['reportYear'];

        echo '<table id="myTableOtchet" border="1">';
        $this->generateTableHeaders($showDealSignDetail, $showDealDetail);
        foreach ($companiesData as $companyId => $company) {
            foreach ($company as $detailInfo) {
                $this->generateTableRow($detailInfo, $companyId, $reportYear, $showDealSignDetail, $showDealDetail);
            }
        }
        echo '</table>';
    }

    /**
     * Генерирует заголовки таблицы.
     */
    private function generateTableHeaders(bool $showDealSignDetail, bool $showDealDetail): void
    {
        echo '<tr>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Сегмент контрагента</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Прекратил деятельность</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Контрактодержатель</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Компания</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Категория АВС</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Менеджер</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Продукт</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Сегмент товара</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Годовая потребность (расчетная величина)</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Факт в тн. Год</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">План текущего года</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Факт в тн. Прошлый год</th>';
        echo '<th class="el-komp" bgcolor="#1e90ff">Процент выполнения плана</th>';

        if ($showDealSignDetail) {
            echo '<th class="el-priznakDeal" bgcolor="#f8cbad">Факт в тн. Год</th>';
            echo '<th class="el-priznakDeal" bgcolor="#f8cbad">Признак сделки</th>';
        }

        if ($showDealDetail) {
            echo '<th class="el-deal" bgcolor="#8fbc8f">Факт в тн. Год</th>';
            echo '<th class="el-deal" bgcolor="#8fbc8f">Признак сделки</th>';
            echo '<th class="el-deal" bgcolor="#8fbc8f">Сделка</th>';
        }

        echo '</tr>';
    }

    /**
     * Генерирует строку таблицы для одной записи.
     */
    private function generateTableRow(array $detailInfo, int $companyId, int $reportYear, bool $showDealSignDetail, bool $showDealDetail): void
    {
        echo '<tr>';
        echo '<td class="segment" height="35">' . htmlspecialchars($detailInfo['CLIENT_SEGMENT']) . '</td>';
        echo '<td class="endActive" height="35">' . htmlspecialchars($detailInfo['END_ACTIVE']) . '</td>';
        echo '<td class="contractHaving" height="35">' . htmlspecialchars($detailInfo['CONTRACT_HAVING']) . '</td>';
        echo '<td class="company" height="35">'
            . '<a target="_blank" href="https://bt.rosma.ru/crm/company/details/' . htmlspecialchars($companyId) . '/">'
            . htmlspecialchars($detailInfo['COMPANY_TITLE'])
            . '</a>'
            . '</td>';
        echo '<td class="classificate" height="35">' . htmlspecialchars($detailInfo['CLASSIFICATE']) . '</td>';
        echo '<td class="assigned" height="35">' . htmlspecialchars($detailInfo['ASSIGNED']) . '</td>';
        echo '<td class="product" height="35">' . htmlspecialchars($detailInfo['SECTION_NAME']) . '</td>';
        echo '<td class="product-segment" height="35">' . htmlspecialchars($detailInfo['SEGMENT_NAME']) . '</td>';
        echo '<td class="annual-need" height="35">' . $this->formatValue($detailInfo['YEAR_REQUIREMENT']) . '</td>';
        echo '<td class="p_productsCurrentTons" height="35">' . $this->formatValue($detailInfo['FACT_BY_SECTION_ID']) . '</td>';
        echo '<td class="planCurrentYear" height="35">' . $this->formatValue($detailInfo['PLAN_FOR_CURRENT_YEAR']) . '</td>';
        echo '<td class="p_productsPrevTons" height="35">' . $this->formatValue($detailInfo['FACT_FOR_LAST_YEAR_BY_SECTION_ID']) . '</td>';
        echo '<td class="procentPlana" height="35">' . $this->formatValue($detailInfo['PERCENT_PLAN_COMPLETION']) . '</td>';

        if ($showDealSignDetail) {
            echo '<td class="pd_productsCurrentTons" height="35">' . $this->formatValue($detailInfo['FACT_BY_DEAL_SIGN']) . '</td>';
            echo '<td class="priznakDealGroup" height="35">' . htmlspecialchars($detailInfo['DEAL_SIGN']) . '</td>';
        }
        if ($showDealDetail) {
            echo '<td class="d_productsCurrentTons" height="35">' . $this->formatValue($detailInfo['FACT_BY_DEAL']) . '</td>';
            echo '<td class="priznakDeal" height="35">' . htmlspecialchars($detailInfo['DEAL_SIGN']) . '</td>';
            echo '<td class="deal" height="35">' . '<a target="_blank" href="https://bt.rosma.ru/crm/deal/details/' . htmlspecialchars($detailInfo['DEAL_ID']) . '/">' . htmlspecialchars($detailInfo['DEAL_TITLE']) . '</a></td>';
        }

        echo '</tr>';
    }

    /**
     * Форматирует числовое значение для вывода.
     */
    private function formatValue($value): string
    {
        if (is_numeric($value)) {
            return number_format(floatval($value), 3, ',', '');
        }
        return htmlspecialchars($value);
    }
}