<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}
\Bitrix\Main\UI\Extension::load([
	"calendar.util"
]);

$this->setFrameMode(true);
$this->SetViewTarget("sidebar", 50);
$frame = $this->createFrame()->begin();
$this->addExternalCss(SITE_TEMPLATE_PATH."/css/sidebar.css");
?>



	<table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#47E4C2">
		<tr>
			<td>
					
					<div class="sidebar-widget-top-title">Отсутствия</div>

			
			</td>
		</tr>
	</table>




<?		
$arSelect = Array("ID", "IBLOCK_ID", "NAME", "ACTIVE_FROM", "ACTIVE_TO", "PREVIEW_TEXT", "PROPERTY_*");
$arFilter = Array("IBLOCK_ID"=>3, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
$res = CIBlockElement::GetList(Array("ACTIVE_TO"=>"ASC"), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement())
//начало цикла
{
$arFields = $ob->GetFields();
	//print_r($arFields);
$arProps = $ob->GetProperties();
//print_r($arProps);
$rsUser = CUser::GetByID($arProps['USER']['VALUE'])->Fetch();
?>

<?
$uId=$arProps['USER']['VALUE'];
$uDBInfo = CUser::GetByID($uId);
$arWaterMark = Array(
            array(
        "name" => "watermark",
		"position" => "center",
        "type" => "image",
		"size" => "real",
		"file" => $_SERVER["DOCUMENT_ROOT"].'/services_sodeistvie/images/WaterMark/user50.png',
		"fill" => "resize",
		//"fill" => "repeat",
		//"coefficient" => 100,
            )
        );

if ($uInfo = $uDBInfo->GetNext())
{
            if ($uInfo['PERSONAL_PHOTO'])
            {
                $file = CFile::ResizeImageGet($uInfo['PERSONAL_PHOTO'], array('width'=>50, 'height'=>50), BX_RESIZE_IMAGE_EXACT, true, $arWaterMark);                
                $img = '<img src="'.$file['src'].'"/>';
                $uInfo['PERSONAL_PHOTO'] = $img;
                $uName = $uInfo['NAME'].' '.$uInfo['LAST_NAME'];
                
				
            }
	//$arResult['ITEMS'][$k]['USER_INFO'] = $uInfo;
}

//echo $img;
//echo '<pre>';var_dump($file);echo '</pre>';
//echo '<pre>';var_dump($uDBInfo);echo '</pre>';
///https://crm.sodeistvie.su/company/personal/user/483/
?>

<table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#FFFFFF" style="border-collapse: collapse">
		<tr>
			<td colspan="3"></td>
		</tr>
		<tr>
			<td rowspan="2" width="90">
			<p align="center">
<? echo $img; ?>		
			</td>
			<td rowspan="2"></td>
			<td>
<b>
<a href="https://crm.sodeistvie.su/company/personal/user/<?  echo $arProps['USER']['VALUE'] ?>/" bx-tooltip-user-id="<?  echo $arProps['USER']['VALUE'] ?>"><? echo $rsUser['NAME'].' '.$rsUser['LAST_NAME'] ?></a> 
</b>
<br>
<font color="#808080">
<? echo 'по '.date('d.m.Y', strtotime($arFields['ACTIVE_TO'])); ?>
</font>
			</td>
		</tr>
		<tr>
			<td valign="top">
			</td>
		</tr>
		<tr>
			<td colspan="3"></td>
		</tr>
	</table>








	


<? } ?>

	<table border="0" cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td>&nbsp;</td>
		</tr>
	</table>

<?
$frame->end();
$this->EndViewTarget();
?>

