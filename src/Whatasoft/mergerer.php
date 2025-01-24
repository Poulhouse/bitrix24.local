<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
\Bitrix\Main\Loader::IncludeModule('crm');
if (isset($_POST['search']))
{
    $arFilter = array(
        "NAME" => trim($_POST['NAME']),
        "LAST_NAME" => trim($_POST['LAST_NAME']),
		//        "SECOND_NAME" => $_POST['second_name'],
        'CHECK_PERMISSIONS' => 'N'
    );

    $res = CCrmContact::GetList($arOrder, $arFilter, $arSelect);
    $dubles = [];
    while ($arContact = $res->fetch())
    {

        $arFilter = ['ENTITY_ID' => 'CONTACT', 'ELEMENT_ID' => $arContact['ID'], 'TYPE_ID' => 'PHONE'];
        $resPhones = \CCrmFieldMulti::GetListEx([], $arFilter, false, ['nTopCount' => 5], ['VALUE']);
        $ph = "";
        while ($arPhone = $resPhones->fetch())
        {
            $ph .= $arPhone['VALUE'] . ' ';
        }
        $dubles[$arContact['ID']] = $ph;
    }
    if (count($dubles))
    {
		?>
		<strong>Подозрение на дубли</strong>
<table style="width:100%">
	<tr style="background-color:#fafafa;"><td width="80%">Телефон</td><td width="*"></td></tr>
			<? foreach ($dubles as $id => $d)
				{ ?>
					<tr>
						<td><?=$ph?></td>
						<td><a href="#" onclick="mergedubles(<?=$id?>, <?=$_POST['id']?>); return false" style="border-bottom:1px dashed blue">Объединить</a></td>
					</tr>
				<?}?>
		</table>
		<script type="text/javascript">
			function mergedubles(source, dest){
				$.post('/local/src/Whatasoft/mergerer.php', {'source' : source, 'dest' : dest,  'merge' : 1, 'last_name' : '<?=$_POST['last_name']?>'});
			}
		</script>
<?
    }
}
elseif (isset($_POST['merge']))
{
	$source = intval($_POST['source']);
	$dest = intval($_POST['dest']);
	global $USER;
	$merger = new \Bitrix\Crm\Merger\ContactMerger($USER->GetID());
	$criteria = new \Bitrix\Crm\Integrity\DuplicatePersonCriterion($_POST['last_name']);
	$merger->merge($source, $dest, $criteria);

}

