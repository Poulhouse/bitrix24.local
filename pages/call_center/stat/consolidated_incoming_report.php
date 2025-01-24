<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Сводные отчёты [Телемаркетинг]");
$APPLICATION->IncludeComponent("whatasoft:requests.statistics.reports",
  ".default",
  array(
    'REPORT_TYPE_CODE' => Whatasoft\Statistic\CallReport::REPORT_TYPE_INCOMING,
  ),
  false
);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>