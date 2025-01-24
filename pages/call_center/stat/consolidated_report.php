<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Сводные отчёты");
$APPLICATION->IncludeComponent("whatasoft:requests.statistics.reports",
  ".default",
  array(
    'REPORT_TYPE_CODE' => Whatasoft\Statistic\CallReport::REPORT_TYPE_INTERNET_APPLICATIONS,
  ),
  false
);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>