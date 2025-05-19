<?php

ini_set('display_errors', 'On');
ini_set("memory_limit", "2048M"); // Увеличивает лимит памяти до 2 ГБ
ini_set('max_execution_time', '300'); // Устанавливает время выполнения скрипта в 300 секунд

require_once('/home/bitrix/www/local/reportPlanFact/include/header.php');
require_once('/home/bitrix/www/local/reportPlanFact/include/style.php');
require_once('/home/bitrix/www/local/reportPlanFact/include/classes/Bitrix24DataCollector.php');
require_once('/home/bitrix/www/local/reportPlanFact/include/classes/Bitrix24ReportGenerator.php');

// Подключение Bitrix24 API
use Bitrix\Crm\CCrmDeal;
use Bitrix\Iblock\Elements\ElementProductTable;

// Подключаем CCrmDeal для получения товаров сделки

//region Фильтр
$resUsers = \Bitrix\Main\UserTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'LAST_NAME', 'ACTIVE'],
    'order' => ['LAST_NAME' => 'ASC']
]);

$resSegment = \CIBlockElement ::GetList(
    ['NAME'=>'ASC'],
    ["IBLOCK_ID" => 21],
    false,
    false,
    ['ID','NAME']
);

// Получение продуктов (секции)
$resProduct = \CIBlockSection ::GetList(
    ['NAME'=>'ASC'],
    ["IBLOCK_ID" => 14, "ACTIVE" => "Y"],
    false,
    ["ID", "NAME"]
);

// Получение сегментов продуктов (секции)
$resProductSegment = \CIBlockElement ::GetList(
    ['NAME'=>'ASC'],
    ["IBLOCK_ID" => 24],
    false,
    false,
    ['ID','NAME']
);
$resClassificates = [
    ['ID' => 2097,'NAME'=>'Категория A'],
    ['ID' => 2098,'NAME'=>'Категория B'],
    ['ID' => 2099,'NAME'=>'Категория C']
];

$resContractHaving = [
    ['ID' => 'A','NAME'=>'Без фильтра'],
    ['ID' => '1','NAME'=>'Да'],
    ['ID' => '0','NAME'=>'Нет'],
];
$resEndActive = [
    ['ID' => 'A','NAME'=>'Без фильтра'],
    ['ID' => '1','NAME'=>'Да'],
    ['ID' => '0','NAME'=>'Нет'],
];

echo '<div id="controlPanel" style="margin-bottom: 20px;">';
echo '<button id="filterButton">Фильтрация</button>';

$accessUsers = CIBlockElement::GetList([],["IBLOCK_ID"=>43,'ID'=>11405],false,false,[]);
while ($obj = $accessUsers->GetNextElement()){$properties = $obj->GetProperties();}
$arAccessUsers = $properties['SOTRUDNIKI_S_DOSTUPOM']['VALUE'];

global $USER;
$u = $USER->GetID();
if(in_array($u,$arAccessUsers)) {
    echo '<button id="exportExcelButton">Сохранить в Excel</button>';
    echo '<button id="exportExcelNoGroupButton">Сохранить в Excel(без группировки)</button>';
}
echo '</div>';
//endregion

// Получение данных
$filters = [
    'clientFilter' => isset($_GET['client']) ? trim($_GET['client']) : '',
    'segmentFilter' => $_GET['segment'] ?? [],
    'assignIdsFilter' => $_GET['assignIds'] ?? [],
    'classificateFilter' => $_GET['classificates'] ?? [],
    'endActiveFilter' => $_GET['endActive'] ?? [],
    'contractHavingFilter' => $_GET['contractHaving'] ?? [],
    'productFilter' => $_GET['product'] ?? [],
    'productSegmentFilter' => $_GET['productSegment'] ?? [],
    'dealSignFilter' => $_GET['dealSign'] ?? '',
    'showDealSignDetail' => $_GET['showDealSignDetail'] ?? '',
    'showDealDetail' => $_GET['showDealDetail'] ?? '',
    'reportYear' =>  $_GET['reportYear'] ?? date('Y'), // по умолчанию текущий год
];

$reportYear = $filters['reportYear'];
//echo $reportYear;
$bitrixCollector = new Bitrix24DataCollector();
$bitrixCollector->collectDealsAndProducts($filters);
$bitrixCollector->collectOborudovanies();
$bitrixCollector->collectPotrebs($filters['productFilter'], $filters['productSegmentFilter']);
$data = $bitrixCollector->generateReportData($filters);

if($_REQUEST['PLACEMENT'] == 'CRM_COMPANY_DETAIL_TAB') {
    $placementOptions = json_decode($_REQUEST['PLACEMENT_OPTIONS'],true);
    //print_r($placementOptions);
    // Обрабатываем параметры POST
    $companyId = isset($placementOptions['ID']) ? $placementOptions['ID'] : null;

    if ($companyId) {
        $newData[$companyId] = $data[$companyId];
        // Генерация отчета
        $reportGenerator = new Bitrix24ReportGenerator();
        $reportGenerator->generateReport($newData, $filters);
        ?>

        <div id="overlay"></div>
        <div id="filterPopup">
            <form id="filterForm" action="" method="get">
                <input type="hidden" name="PLACEMENT" value='<?=$_REQUEST['PLACEMENT'];?>'>
                <input type="hidden" name="PLACEMENT_OPTIONS" value='{"ID":"<?=$companyId;?>"}'>
                <label for="productFilter">Продукт:</label>
                <select id="productFilter" name="product[]" multiple>
                    <?php
                    // Получение продуктов (секции)
                    while ($product = $resProduct->Fetch()) {
                        echo "<option ";
                        if(isset($_GET['product'])){
                            if(in_array($product['ID'],$_GET['product'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$product['ID']}'>{$product['NAME']}</option>";
                    }
                    unset($resProduct);
                    ?>
                </select><br><br>

                <label for="productSegmentFilter">Группа продуктов:</label>
                <select id="productSegmentFilter" name="productSegment[]" multiple>
                    <?php
                    // Получение сегментов продуктов (секции)
                    while ($productSegment = $resProductSegment->Fetch()) {
                        echo "<option ";
                        if(isset($_GET['productSegment'])){
                            if(in_array($productSegment['ID'],$_GET['productSegment'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$productSegment['ID']}'>{$productSegment['NAME']}</option>";
                    }
                    unset($resProductSegment);
                    ?>
                </select><br><br>

                <label for="dealSignFilter">Признак сделки:</label>
                <select id="dealSignFilter" name="dealSign">
                    <option value=''>--</option>
                    <?php
                    $dealSigns = [];

                    // Получение признаков сделок
                    $res = \CUserFieldEnum::GetList([], ["USER_FIELD_NAME" => "UF_CRM_1685703119"]);
                    while ($dealSign = $res->Fetch()) {
                        $dealSigns[$dealSign['ID']] = $dealSign['VALUE'];
                        echo "<option ";
                        if(isset($_GET['dealSign'])){
                            if($dealSign['ID'] == $_GET['dealSign']){
                                echo "selected";
                            }
                        }
                        echo " value='{$dealSign['ID']}'>{$dealSign['VALUE']}</option>";
                    }
                    ?>
                </select><br><br>

                <label for="showDealSignDetail">Показать детализацию по признаку сделки:</label>
                <input type="checkbox" id="showDealSignDetail" name="showDealSignDetail" value="1" <?php if(isset($_GET['showDealSignDetail']))
                {echo "checked";} ?>><br><br>

                <label for="showDealDetail">Показать детализацию по сделкам:</label>
                <input type="checkbox" id="showDealDetail" name="showDealDetail" value="1" <?php if(isset($_GET['showDealDetail']))
                {echo "checked";} ?>><br><br>

                <label for="reportYear">Год отчетного периода:</label>
                <select id="reportYear" name="reportYear">
                    <!-- Значения годов -->
                    <?php
                    for ($year = 2023; $year <= 2040; $year++) {
                        $years[] = $year;
                    }

                    foreach ($years as $year) {
                        echo "<option ";
                        if(isset($reportYear)){
                            if($year == $reportYear){
                                echo "selected";
                            }
                        }
                        echo " value='{$year}'>{$year}</option>";
                    }
                    ?>
                </select><br><br>

                <input type="submit" value='Применить фильтры' id="applyFilterButton" />
                <button type="button" id="closePopupButton">Закрыть</button>
            </form>
        </div>

        <?php
    }
}
else {
    // Генерация отчета
    $reportGenerator = new Bitrix24ReportGenerator();
    $reportGenerator->generateReport($data, $filters);
    ?>

    <div id="overlay"></div>
    <div id="filterPopup">
        <form id="filterForm" action="" method="get">
            <div class="leftColumn">
                <label for="clientFilter">Клиент:</label>
                <input type="text" id="clientFilter" name="client" <?php if(isset($_GET['client']))
                {echo "value='{$_GET['client']}'";}?>><br><br>

                <label for="segmentFilter">Сегмент клиента:</label>
                <select id="segmentFilter" name="segment[]" multiple>
                    <?php
                    // Получение сегментов клиентов
                    while ($segment = $resSegment->Fetch()) {
                        echo "<option ";
                        if(isset($_GET['segment'])){
                            if(in_array($segment['ID'],$_GET['segment'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$segment['ID']}'>{$segment['NAME']}</option>";
                    }
                    unset($resSegment);
                    ?>
                </select><br><br>

                <label for="endActiveFilter">Прекратил деятельность:</label>
                <select id="endActiveFilter" name="endActive">
                    <?php
                    // Получение Прекратил деятельность
                    foreach($resEndActive as $endActive){
                        echo "<option ";
                        if(isset($_GET['endActive'])){
                            if($endActive['ID'] == $_GET['endActive']){
                                echo "selected";
                            }
                        }
                        echo " value='{$endActive['ID']}'>{$endActive['NAME']}</option>";
                    }
                    unset($resEndActive);
                    ?>
                </select><br><br>

                <label for="contractHavingFilter">Контрактодержатель:</label>
                <select id="contractHavingFilter" name="contractHaving">
                    <?php
                    // Получение Контрактодержателей
                    foreach($resContractHaving as $contractHaving){
                        echo "<option ";
                        if(isset($_GET['contractHaving'])){
                            if($contractHaving['ID'] == $_GET['contractHaving']){
                                echo "selected";
                            }
                        }
                        echo " value='{$contractHaving['ID']}'>{$contractHaving['NAME']}</option>";
                    }
                    unset($resContractHaving);
                    ?>
                </select><br><br>

                <label for="classificateFilter">Категория ABC:</label>
                <select id="classificateFilter" name="classificates[]" multiple>
                    <?php
                    // Получение классификаций
                    foreach($resClassificates as $classificate){
                        echo "<option ";
                        if(isset($_GET['classificates'])){
                            if(in_array($classificate['ID'],$_GET['classificates'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$classificate['ID']}'>{$classificate['NAME']}</option>";
                    }
                    unset($resClassificates);
                    ?>
                </select><br><br>

                <label for="assignIdsFilter">Менеджер:</label>
                <select id="assignIdsFilter" name="assignIds[]" multiple>
                    <?php
                    // Получение сегментов клиентов
                    while ($assignedUser = $resUsers->fetch()) {
                        echo "<option ";
                        if(isset($_GET['assignIds'])){
                            if(in_array($assignedUser['ID'],$_GET['assignIds'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$assignedUser['ID']}'>{$assignedUser['NAME']} {$assignedUser['LAST_NAME']}</option>";
                    }
                    unset($resUsers);
                    ?>
                </select><br><br>


            </div>
            <div class="rightColumn">
                <label for="productFilter">Продукт:</label>
                <select id="productFilter" name="product[]" multiple>
                    <?php
                    // Получение продуктов (секции)
                    while ($product = $resProduct->Fetch()) {
                        echo "<option ";
                        if(isset($_GET['product'])){
                            if(in_array($product['ID'],$_GET['product'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$product['ID']}'>{$product['NAME']}</option>";
                    }
                    unset($resProduct);
                    ?>
                </select><br><br>
                <label for="productSegmentFilter">Группа продуктов:</label>
                <select id="productSegmentFilter" name="productSegment[]" multiple>
                    <?php
                    // Получение сегментов продуктов (секции)
                    while ($productSegment = $resProductSegment->Fetch()) {
                        echo "<option ";
                        if(isset($_GET['productSegment'])){
                            if(in_array($productSegment['ID'],$_GET['productSegment'])){
                                echo "selected";
                            }
                        }
                        echo " value='{$productSegment['ID']}'>{$productSegment['NAME']}</option>";
                    }
                    unset($resProductSegment);
                    ?>
                </select><br><br>

                <label for="dealSignFilter">Признак сделки:</label>
                <select id="dealSignFilter" name="dealSign">
                    <option value=''>Без фильтра</option>
                    <?php
                    $dealSigns = [];

                    // Получение признаков сделок
                    $res = \CUserFieldEnum::GetList([], ["USER_FIELD_NAME" => "UF_CRM_1685703119"]);
                    while ($dealSign = $res->Fetch()) {
                        $dealSigns[$dealSign['ID']] = $dealSign['VALUE'];
                        echo "<option ";
                        if(isset($_GET['dealSign'])){
                            if($dealSign['ID'] == $_GET['dealSign']){
                                echo "selected";
                            }
                        }
                        echo " value='{$dealSign['ID']}'>{$dealSign['VALUE']}</option>";
                    }
                    ?>
                </select><br><br>

                <label for="showDealSignDetail">Показать детализацию по признаку сделки:</label>
                <input type="checkbox" id="showDealSignDetail" name="showDealSignDetail" value="1" <?php if(isset($_GET['showDealSignDetail']))
                {echo "checked";} ?>><br><br>

                <label for="showDealDetail">Показать детализацию по сделкам:</label>
                <input type="checkbox" id="showDealDetail" name="showDealDetail" value="1" <?php if(isset($_GET['showDealDetail']))
                {echo "checked";} ?>><br><br>

                <label for="reportYear">Год отчетного периода:</label>
                <select id="reportYear" name="reportYear">
                    <!-- Значения годов -->
                    <?php
                    for ($year = 2023; $year <= 2040; $year++) {
                        $years[] = $year;
                    }

                    foreach ($years as $year) {
                        echo "<option ";
                        if(isset($reportYear)){
                            if($year == $reportYear){
                                echo "selected";
                            }
                        }
                        echo " value='{$year}'>{$year}</option>";
                    }
                    ?>
                </select><br><br>
            </div>
            <input type="submit" value='Применить фильтры' id="applyFilterButton" />
            <button type="button" id="closePopupButton">Закрыть</button>
        </form>
    </div>

    <?php
}
?>


<?php require_once('/home/bitrix/www/local/reportPlanFact/include/footer.php');