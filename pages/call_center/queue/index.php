<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Интернет-заявки");
?>
<?$APPLICATION->IncludeComponent("whatasoft:requests.queue",
  "",
  Array(
    "NIGHT" => $_GET['night'],
  ),
  false
);?>


<link href="https://cdn.jsdelivr.net/npm/suggestions-jquery@20.3.0/dist/css/suggestions.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/suggestions-jquery@20.3.0/dist/js/jquery.suggestions.min.js"></script>


<script type='text/javascript'>
	function checkDeduple(id){
		var name = $('input[name="contact[NAME]"]').val();
		var last_name = $('input[name="contact[LAST_NAME]"]').val();
		$.post('/local/src/Whatasoft/mergerer.php', {'id' :id, 'search' : '1', 'NAME' : name, 'LAST_NAME' : last_name}, function(data){
			$('#mergerer').html(data);
		})
	}

</script>



<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>