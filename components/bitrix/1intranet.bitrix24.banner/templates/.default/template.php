<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$this->setFrameMode(true);
$this->SetViewTarget("sidebar", 500);
?>
<div class="b24-app-block b24-app-mobile">
	<div class="b24-app-block-header">Оформлено займов сегодня</div>
	<div class="b24-app-block-content">
	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-left-width: 1px; border-right-width: 1px; border-top-width: 1px; border-bottom-style: solid; border-bottom-width: 1px">
		<tr>
			<td align="left" width="20%">
			<a href="https://crm.sodeistvie.su/crm/company/details/5/?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER">
			МКК</a></td>
			<td width="80%" align="right">
			<? echo ss_mkkIssuedCount(date( 'Y-m-d 00:00:00' ))." шт.  | ".round(ss_mkkIssuedSum(date( 'Y-m-d 00:00:00' )))." т.р.";?>
			
			
			</td>
		</tr>
	</table>
		<div style="clear:both">
		<font size="1" color="#C0C0C0">Данные доступны пользователям ГО+ЦО</font>
		</div>
	</div>

</div>