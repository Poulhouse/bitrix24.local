<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Телемаркетинг");
?>
<?/*$APPLICATION->IncludeComponent("whatasoft:requests.queue",
  "",
  Array(
    "NIGHT" => $_GET['night'],
    "TYPE" => 'telemarketing'
  ),
  false
);*/?>
<?$APPLICATION->IncludeComponent("whatasoft:requests.queue.telemarketing",
  "",
  Array(
    "NIGHT" => $_GET['night'],
    "TYPE" => 'telemarketing'
  ),
  false
);?>

<link href="https://cdn.jsdelivr.net/npm/suggestions-jquery@20.3.0/dist/css/suggestions.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/suggestions-jquery@20.3.0/dist/js/jquery.suggestions.min.js"></script>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>