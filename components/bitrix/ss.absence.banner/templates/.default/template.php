<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$this->setFrameMode(true);
$this->SetViewTarget("sidebar", 100);
?>


<div class="b24-app-block b24-app-desktop">
	<div class="b24-app-block-header">Отсутствия</div>
	<div class="b24-app-block-content">
	<table cellpadding="0" cellspacing="0" width="100%" style="border-left-width: 1px; border-right-width: 1px; border-top-width: 1px; ">
		
<?		
$arSelect = Array("ID", "IBLOCK_ID", "NAME", "ACTIVE_FROM", "ACTIVE_TO", "PREVIEW_TEXT", "PROPERTY_*");//IBLOCK_ID и ID обязательно должны быть указаны, см. описание arSelectFields выше
$arFilter = Array("IBLOCK_ID"=>3, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
$res = CIBlockElement::GetList(Array("ACTIVE_TO"=>"ASC"), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement()){ 
 $arFields = $ob->GetFields();
//print_r($arFields);
$arProps = $ob->GetProperties();
//print_r($arProps);
$rsUser = CUser::GetByID($arProps['USER']['VALUE'])->Fetch();
?>


		<span class="task-item-text">
		<tr>
			<td align="left" width="65%">
			<a style="font-size: 95%" href="https://crm.sodeistvie.su/company/personal/user/<?  echo $arProps['USER']['VALUE'] ?>/"><? echo $rsUser['NAME'].' '.$rsUser['LAST_NAME'] ?></a>
			</td>
			
					<a href="<?=$arParams["DETAIL_URL"]?>?EVENT_ID=NEW" class="plus-icon"></a>
			
			
			<td style="font-size: 85%" width="35%" align="right">
			<? echo 'по '.$arFields['ACTIVE_TO']; ?>			
			</td>
		</tr>
		</span>
		
		
<? } ?>		
		
	</table>
		<div style="clear:both">

		</div>
	</div>

</div>